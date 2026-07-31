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

// 料金タイプ判定（区分があれば「男女別」、無ければ「一律」）。男性/女性の既存額を拾う。
$formTiers = $form['tiers'] ?? [];
$isGenderPricing = !empty($formTiers);
$gMale = null;
$gFemale = null;
foreach ($formTiers as $t) {
    if ($t['label'] === '男性') {
        $gMale = $t;
    } elseif ($t['label'] === '女性') {
        $gFemale = $t;
    }
}

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

        <label style="margin-top:6px;">料金タイプ <span class="req">必須</span></label>
        <div style="display:flex; gap:20px; margin-top:4px;">
            <label style="font-weight:400; margin:0;"><input type="radio" name="pricing_mode" value="flat" <?= $isGenderPricing ? '' : 'checked' ?> class="js-pricing-mode" style="width:auto;"> 一律（全員同じ料金）</label>
            <label style="font-weight:400; margin:0;"><input type="radio" name="pricing_mode" value="gender" <?= $isGenderPricing ? 'checked' : '' ?> class="js-pricing-mode" style="width:auto;"> 男女別（性別で料金を変える）</label>
        </div>

        <div id="flatPricing" style="<?= $isGenderPricing ? 'display:none;' : '' ?>">
            <div class="row">
                <div>
                    <label>事前決済の参加費（1名・円）</label>
                    <input type="number" name="amount" min="0" step="1" value="<?= e((string) $form['amount']) ?>" placeholder="3000">
                </div>
                <div>
                    <label>当日支払いの参加費（1名）<span class="hint">空欄なら事前と同額</span></label>
                    <input type="number" name="amount_onsite" min="0" step="1" value="<?= e((string) $form['amount_onsite']) ?>" placeholder="4000">
                </div>
            </div>
        </div>

        <div id="genderPricing" style="<?= $isGenderPricing ? '' : 'display:none;' ?>">
            <p class="hint" style="margin:6px 0;">性別ごとに、事前決済・当日支払いの金額を設定します（1申込＝1名）。当日を空欄にすると事前と同額になります。</p>
            <div class="row">
                <div><label>男性・事前決済（円）</label><input type="number" name="male_prepay" min="0" step="1" value="<?= $gMale ? e((string) $gMale['amount']) : '' ?>" placeholder="5000"></div>
                <div><label>男性・当日支払い（円）</label><input type="number" name="male_onsite" min="0" step="1" value="<?= $gMale ? e((string) $gMale['amount_onsite']) : '' ?>" placeholder="5000"></div>
            </div>
            <div class="row">
                <div><label>女性・事前決済（円）</label><input type="number" name="female_prepay" min="0" step="1" value="<?= $gFemale ? e((string) $gFemale['amount']) : '' ?>" placeholder="3000"></div>
                <div><label>女性・当日支払い（円）</label><input type="number" name="female_onsite" min="0" step="1" value="<?= $gFemale ? e((string) $gFemale['amount_onsite']) : '' ?>" placeholder="3000"></div>
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

        <label style="margin-top:18px;">入力項目（タグで選択）</label>
        <p class="hint" style="margin-top:0;">申込フォームに追加する項目をタグで選びます。表示順は <strong>氏名 → 氏名フリガナ → 年齢 →（性別）→ メールアドレス → 紹介者</strong> に固定。<strong>メールアドレスは常に必須</strong>、<strong>性別は「男女別」料金のとき</strong>に表示されます。選んだ項目は必須になります。全部外すと「性別・メール」のみになります。</p>
        <?php
        $selectedLabels = array_column($form['custom_fields'] ?? [], 'label');
        $knownLabels = array_column(known_field_catalog(), 'label');
        // 保存済みのうち、カタログに無いもの＝自由項目（末尾に表示される）
        $freeFields = array_values(array_filter(($form['custom_fields'] ?? []), static fn ($f) => !in_array($f['label'], $knownLabels, true)));
        $cfTypeLabels = ['text' => '文字', 'number' => '数値', 'tel' => '電話番号', 'textarea' => '長文'];
        ?>
        <div class="chips">
            <?php foreach (known_field_catalog() as $key => $def): ?>
                <label class="chip">
                    <input type="checkbox" name="fields[]" value="<?= e($key) ?>" <?= in_array($def['label'], $selectedLabels, true) ? 'checked' : '' ?>>
                    <?= e($def['label']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <label style="margin-top:14px;">自由項目（上記以外を追加・任意）</label>
        <p class="hint" style="margin-top:0;">ここで追加した項目は<strong>メール・紹介者の後</strong>に表示されます（自由な項目名・種別・必須/任意）。</p>
        <div id="cfList">
            <?php foreach ($freeFields as $f): ?>
                <div class="cf-row">
                    <input type="text" name="cf_label[]" maxlength="40" placeholder="例: 会社名" value="<?= e((string) $f['label']) ?>">
                    <select name="cf_type[]">
                        <?php foreach ($cfTypeLabels as $tv => $tl): ?>
                            <option value="<?= e($tv) ?>" <?= ($f['type'] ?? 'text') === $tv ? 'selected' : '' ?>><?= e($tl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="cf_required[]">
                        <option value="1" <?= !empty($f['required']) ? 'selected' : '' ?>>必須</option>
                        <option value="0" <?= empty($f['required']) ? 'selected' : '' ?>>任意</option>
                    </select>
                    <button type="button" class="btn btn--ghost cf-del">削除</button>
                </div>
            <?php endforeach; ?>
        </div>
        <p><button type="button" class="btn btn--ghost" id="cfAdd">＋ 自由項目を追加</button></p>

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
                                        <span class="muted"><?= e($t['label']) ?></span> 事前<?= e(format_amount((int) $t['amount'], $ev['currency'] ?? 'jpy')) ?><?php if (!empty($ev['allow_onsite'])): ?>/当日<?= e(format_amount((int) $t['amount_onsite'], $ev['currency'] ?? 'jpy')) ?><?php endif; ?><br>
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
    // 料金タイプ（一律/男女別）で入力欄を出し分ける
    var flat = document.getElementById('flatPricing');
    var gender = document.getElementById('genderPricing');
    var radios = document.querySelectorAll('.js-pricing-mode');
    if (!flat || !gender || !radios.length) { return; }
    function apply() {
        var sel = document.querySelector('.js-pricing-mode:checked');
        var mode = sel ? sel.value : 'flat';
        flat.style.display = (mode === 'gender') ? 'none' : '';
        gender.style.display = (mode === 'gender') ? '' : 'none';
    }
    radios.forEach(function (r) { r.addEventListener('change', apply); });
    apply();
})();
(function () {
    // 自由項目の行を追加/削除
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
        var l = document.createElement('input');
        l.type = 'text'; l.name = 'cf_label[]'; l.maxLength = 40; l.placeholder = '例: 会社名';
        var ty = document.createElement('select');
        ty.name = 'cf_type[]';
        [['text', '文字'], ['number', '数値'], ['tel', '電話番号'], ['textarea', '長文']].forEach(function (o) {
            var op = document.createElement('option'); op.value = o[0]; op.textContent = o[1]; ty.appendChild(op);
        });
        var rq = document.createElement('select');
        rq.name = 'cf_required[]';
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
