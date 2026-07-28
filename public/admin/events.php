<?php

/**
 * イベント管理画面（ログイン中テナント専用）。
 * ログインした主催者が自分のイベントを登録・編集・削除できる。
 * イベントは DB（events テーブル）にテナント単位で保存する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

$events = tenant_events($tenant['id']);

// 編集対象（?edit=ID）。新規のときは空のひな形。他テナントのものは編集不可。
$editId = (string) ($_GET['edit'] ?? '');
$editing = $editId !== '' ? find_event($editId) : null;
if ($editing !== null && $editing['tenant_id'] !== $tenant['id']) {
    $editing = null;
}
$form = $editing ?? [
    'id' => '', 'name' => '', 'description' => '', 'date' => '',
    'place' => '', 'amount' => '', 'currency' => 'jpy', 'capacity' => '',
    'amount_onsite' => '', 'allow_prepay' => true, 'allow_onsite' => false,
    'tiers' => [], 'custom_fields' => [],
];

$flash = (string) ($_GET['msg'] ?? '');
$flashType = (string) ($_GET['type'] ?? '');
$token = csrf_token();

$pageTitle = 'イベント管理';
require __DIR__ . '/_app_header.php';
?>
<?php if (!stripe_ready_for_tenant($tenant)): ?>
    <div class="flash flash--ng">⚠️ Stripe 未設定です。クレジットカード決済（事前決済）を使うには <a href="stripe.php">Stripe設定</a> から鍵を登録してください（当日支払い・現金のみなら設定不要）。</div>
<?php endif; ?>

<?php if ($flash !== ''): ?>
    <div class="flash <?= $flashType === 'ok' ? 'flash--ok' : 'flash--ng' ?>"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__title"><?= $editing ? 'イベントを編集' : 'イベントを新規登録' ?></div>
    <form method="post" action="event_save.php">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">

        <label>イベント名 <span class="req">必須</span></label>
        <input type="text" name="name" required maxlength="100" value="<?= e((string) $form['name']) ?>" placeholder="夏のBBQ大会 2026">

        <label>説明</label>
        <textarea name="description" maxlength="500" placeholder="食材・ドリンク込み。雨天時は..."><?= e((string) $form['description']) ?></textarea>

        <div class="row">
            <div>
                <label>日時 <span class="req">必須</span></label>
                <input type="datetime-local" name="date" required value="<?= e(datetime_local_value((string) $form['date'])) ?>">
            </div>
            <div>
                <label>場所 <span class="req">必須</span></label>
                <input type="text" name="place" required maxlength="100" value="<?= e((string) $form['place']) ?>" placeholder="多摩川河川敷">
            </div>
        </div>

        <div class="row">
            <div>
                <label>事前決済の参加費（1名・円） <span class="req">必須</span></label>
                <input type="number" name="amount" required min="0" step="1" value="<?= e((string) $form['amount']) ?>" placeholder="3000">
            </div>
            <div>
                <label>当日支払いの参加費（1名）<span class="hint">空欄なら事前と同額</span></label>
                <input type="number" name="amount_onsite" min="0" step="1" value="<?= e((string) $form['amount_onsite']) ?>" placeholder="4000">
            </div>
        </div>

        <div class="row">
            <div>
                <label>通貨</label>
                <input type="text" value="日本円（JPY）" readonly>
            </div>
            <div>
                <label>定員目安（申込人数の上限にも使用）</label>
                <input type="number" name="capacity" min="0" step="1" value="<?= e((string) $form['capacity']) ?>" placeholder="20">
            </div>
        </div>

        <label>受け付ける支払い方法</label>
        <div style="display:flex; gap:20px; margin-top:4px;">
            <label style="font-weight:400; margin:0;"><input type="checkbox" name="allow_prepay" value="1" <?= !empty($form['allow_prepay']) ? 'checked' : '' ?> style="width:auto;"> 事前決済（クレジットカードで前払い）</label>
            <label style="font-weight:400; margin:0;"><input type="checkbox" name="allow_onsite" value="1" <?= !empty($form['allow_onsite']) ? 'checked' : '' ?> style="width:auto;"> 当日支払い（現地で集金）</label>
        </div>

        <label style="margin-top:18px;">料金区分（男性／女性など・任意）</label>
        <p class="hint" style="margin-top:0;">区分を1つ以上登録すると、申込画面は「区分を選ぶ」形になり、<strong>選んだ区分の金額で決済</strong>します（1申込＝1名）。空欄のままなら上の「参加費」を使う通常の申込です。金額は上の参加費より優先されます。</p>
        <div id="tierList">
            <?php foreach (($form['tiers'] ?? []) as $t): ?>
                <div class="tier-row" style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                    <input type="text" name="tier_label[]" maxlength="40" placeholder="例: 男性" value="<?= e((string) $t['label']) ?>" style="flex:2;">
                    <input type="number" name="tier_amount[]" min="0" step="1" placeholder="金額(円)" value="<?= e((string) $t['amount']) ?>" style="flex:1;">
                    <button type="button" class="btn btn--ghost tier-del">削除</button>
                </div>
            <?php endforeach; ?>
        </div>
        <p><button type="button" class="btn btn--ghost" id="tierAdd">＋ 区分を追加</button></p>

        <label style="margin-top:18px;">追加の入力項目（名前・年齢など・任意）</label>
        <p class="hint" style="margin-top:0;">1つ以上登録すると、申込画面は「メールアドレス」「性別（料金区分を設定した場合）」＋<strong>ここで定義した項目だけ</strong>になります（氏名・電話・人数・備考などの標準項目は表示されません）。空欄のままなら従来の標準項目を使います。</p>
        <?php $cfTypeLabels = ['text' => '文字', 'number' => '数値', 'tel' => '電話番号', 'textarea' => '長文']; ?>
        <div id="cfList">
            <?php foreach (($form['custom_fields'] ?? []) as $f): ?>
                <div class="cf-row" style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                    <input type="text" name="cf_label[]" maxlength="40" placeholder="例: 年齢" value="<?= e((string) $f['label']) ?>" style="flex:2;">
                    <select name="cf_type[]" style="flex:1;">
                        <?php foreach ($cfTypeLabels as $tv => $tl): ?>
                            <option value="<?= e($tv) ?>" <?= ($f['type'] ?? 'text') === $tv ? 'selected' : '' ?>><?= e($tl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="cf_required[]" style="flex:1;">
                        <option value="1" <?= !empty($f['required']) ? 'selected' : '' ?>>必須</option>
                        <option value="0" <?= empty($f['required']) ? 'selected' : '' ?>>任意</option>
                    </select>
                    <button type="button" class="btn btn--ghost cf-del">削除</button>
                </div>
            <?php endforeach; ?>
        </div>
        <p><button type="button" class="btn btn--ghost" id="cfAdd">＋ 入力項目を追加</button></p>

        <p style="margin-top:18px;">
            <button type="submit" class="btn"><?= $editing ? '更新する' : '登録する' ?></button>
            <?php if ($editing): ?><a class="btn btn--ghost" href="events.php">新規登録に切り替え</a><?php endif; ?>
        </p>
    </form>
</div>

<div class="card">
    <div class="card__title">登録済みイベント（<?= count($events) ?>件）</div>
    <?php if (empty($events)): ?>
        <p class="muted">まだイベントがありません。上のフォームから登録してください。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>イベント名</th><th>日時</th><th>場所</th><th>参加費</th><th>申込リンク</th><th>操作</th></tr></thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                        <?php $applyUrl = base_url() . '/apply.php?event_id=' . urlencode($ev['id']); ?>
                        <tr>
                            <td><strong><?= e($ev['name'] ?? '') ?></strong></td>
                            <td class="muted"><?= e($ev['date'] ?? '') ?></td>
                            <td class="muted"><?= e($ev['place'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($ev['tiers'])): ?>
                                    <?php foreach ($ev['tiers'] as $t): ?>
                                        <?= e($t['label']) ?> <?= e(format_amount((int) $t['amount'], $ev['currency'] ?? 'jpy')) ?><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    事前 <?= e(format_amount((int) ($ev['amount'] ?? 0), $ev['currency'] ?? 'jpy')) ?>
                                    <?php if (!empty($ev['allow_onsite'])): ?><br><span class="muted">当日 <?= e(format_amount((int) ($ev['amount_onsite'] ?? 0), $ev['currency'] ?? 'jpy')) ?></span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><input type="text" class="js-select" readonly value="<?= e($applyUrl) ?>" style="width:200px; font-size:.8rem; padding:5px 8px;"></td>
                            <td>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <a class="btn btn--ghost" href="events.php?edit=<?= e($ev['id']) ?>">編集</a>
                                    <form method="post" action="event_delete.php"
                                          data-confirm="「<?= e($ev['name'] ?? '') ?>」を削除します。よろしいですか？（過去の申込・決済データは Stripe に残ります）">
                                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                        <input type="hidden" name="id" value="<?= e($ev['id']) ?>">
                                        <button type="submit" class="btn btn--danger">削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    var list = document.getElementById('tierList');
    var add = document.getElementById('tierAdd');
    if (!list || !add) { return; }
    function wireDel(btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.tier-row');
            if (row) { row.remove(); }
        });
    }
    add.addEventListener('click', function () {
        var row = document.createElement('div');
        row.className = 'tier-row';
        row.style.cssText = 'display:flex; gap:8px; margin-bottom:8px; align-items:center;';
        var l = document.createElement('input');
        l.type = 'text'; l.name = 'tier_label[]'; l.maxLength = 40; l.placeholder = '例: 女性'; l.style.flex = '2';
        var a = document.createElement('input');
        a.type = 'number'; a.name = 'tier_amount[]'; a.min = '0'; a.step = '1'; a.placeholder = '金額(円)'; a.style.flex = '1';
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'btn btn--ghost tier-del'; b.textContent = '削除';
        row.appendChild(l); row.appendChild(a); row.appendChild(b);
        list.appendChild(row);
        wireDel(b);
    });
    document.querySelectorAll('#tierList .tier-del').forEach(wireDel);
})();
(function () {
    var list = document.getElementById('cfList');
    var add = document.getElementById('cfAdd');
    if (!list || !add) { return; }
    function wireDel(btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.cf-row');
            if (row) { row.remove(); }
        });
    }
    add.addEventListener('click', function () {
        var row = document.createElement('div');
        row.className = 'cf-row';
        row.style.cssText = 'display:flex; gap:8px; margin-bottom:8px; align-items:center;';
        var l = document.createElement('input');
        l.type = 'text'; l.name = 'cf_label[]'; l.maxLength = 40; l.placeholder = '例: 年齢'; l.style.flex = '2';
        var ty = document.createElement('select');
        ty.name = 'cf_type[]'; ty.style.flex = '1';
        [['text', '文字'], ['number', '数値'], ['tel', '電話番号'], ['textarea', '長文']].forEach(function (o) {
            var op = document.createElement('option'); op.value = o[0]; op.textContent = o[1]; ty.appendChild(op);
        });
        var rq = document.createElement('select');
        rq.name = 'cf_required[]'; rq.style.flex = '1';
        [['1', '必須'], ['0', '任意']].forEach(function (o) {
            var op = document.createElement('option'); op.value = o[0]; op.textContent = o[1]; rq.appendChild(op);
        });
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'btn btn--ghost cf-del'; b.textContent = '削除';
        row.appendChild(l); row.appendChild(ty); row.appendChild(rq); row.appendChild(b);
        list.appendChild(row);
        wireDel(b);
    });
    document.querySelectorAll('#cfList .cf-del').forEach(wireDel);
})();
</script>
<?php require __DIR__ . '/_app_footer.php'; ?>
