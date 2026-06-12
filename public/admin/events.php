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

// 各イベントの現在の参加人数（Stripe由来。返金済みを除く）。編集・削除時の警告に使う。
$counts = [];
if (stored_stripe_key() !== null) {
    foreach ($events as $ev) {
        try {
            $counts[$ev['id']] = event_headcount($ev['id'], null);
        } catch (\Throwable $e) {
            $counts[$ev['id']] = null; // 取得失敗時は不明
        }
    }
}

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
];

$flash = (string) ($_GET['msg'] ?? '');
$flashType = (string) ($_GET['type'] ?? '');
$token = csrf_token();

// 編集（?edit=ID）または新規（?new）のときだけポップアップを開いた状態で表示
$openModal = ($editing !== null) || isset($_GET['new']);

$pageTitle = 'イベント管理';
$topActions = '<a class="btn" href="events.php?new=1">＋ 新規イベントを作成</a>';
require __DIR__ . '/_app_header.php';
?>
<style>
    .ev-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:1000;
        align-items:flex-start; justify-content:center; padding:24px; overflow-y:auto; }
    .ev-modal.is-open { display:flex; }
    .ev-modal__box { background:#fff; border-radius:14px; max-width:640px; width:100%; position:relative;
        box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .ev-modal__close { position:absolute; top:8px; right:14px; background:none; border:none; font-size:1.7rem;
        line-height:1; cursor:pointer; color:#6b7280; z-index:1; }
    @media (max-width:480px){ .ev-modal{ padding:10px; } }
</style>
<?php if (stored_stripe_key() === null): ?>
    <div class="flash flash--ng">⚠️ Stripe キー未設定です。<a href="stripe.php">Stripe 設定</a>から API キーを登録してください。カード決済だけでなく、<strong>当日支払いの申込受付・参加者管理（名簿）にも Stripe を使う</strong>ため、現金のみの運用でも設定が必要です。</div>
<?php endif; ?>

<?php if ($flash !== ''): ?>
    <div class="flash <?= $flashType === 'ok' ? 'flash--ok' : 'flash--ng' ?>"><?= e($flash) ?></div>
<?php endif; ?>

<div class="ev-modal<?= $openModal ? ' is-open' : '' ?>" id="eventModal" onclick="if(event.target===this)closeEventModal()">
  <div class="ev-modal__box">
    <button type="button" class="ev-modal__close" onclick="closeEventModal()" aria-label="閉じる">×</button>
    <div class="card" style="margin:0;">
        <div class="card__title"><?= $editing ? 'イベントを編集' : 'イベントを新規登録' ?></div>
        <?php if ($editing !== null && !empty($counts[$editing['id']])): ?>
            <div class="flash flash--ng" style="margin-bottom:14px;">
                ⚠️ このイベントには現在 <strong><?= (int) $counts[$editing['id']] ?> 名</strong>の参加者がいます。
                日時・場所・参加費などの変更は、<strong>返金やクレームが発生する場合</strong>があります。内容をよくご確認ください。
            </div>
        <?php endif; ?>
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
                <p class="hint" style="margin:4px 0 0;">※ 事前決済（カード等）を使う場合は <strong>¥50 以上</strong>（Stripeの最低決済額）。当日支払いのみなら制限なし。</p>            </div>
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

        <?php if ($editing): ?>
            <label style="font-weight:400; margin-top:14px;"><input type="checkbox" name="notify" value="1" checked style="width:auto;"> この変更を現在の参加者にメールで通知する</label>
        <?php endif; ?>

        <p style="margin-top:18px;">
            <button type="submit" class="btn"><?= $editing ? '更新する' : '登録する' ?></button>
            <a class="btn btn--ghost" href="events.php" onclick="closeEventModal();">閉じる</a>
        </p>
        </form>
    </div>
  </div>
</div>

<div class="card">
    <div class="card__title">登録済みイベント（<?= count($events) ?>件）</div>
    <?php if (empty($events)): ?>
        <p class="muted">まだイベントがありません。右上の「＋ 新規イベントを作成」から登録してください。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>イベント名</th><th>日時</th><th>場所</th><th>参加費</th><th>申込リンク</th><th>操作</th></tr></thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                        <?php $applyUrl = base_url() . '/apply.php?event_id=' . urlencode($ev['id']); ?>
                        <?php $cnt = $counts[$ev['id']] ?? null; ?>
                        <tr>
                            <td><strong><?= e($ev['name'] ?? '') ?></strong>
                                <?php if (!empty($cnt)): ?><br><span class="muted">参加 <?= (int) $cnt ?> 名</span><?php endif; ?>
                            </td>
                            <td class="muted"><?= e($ev['date'] ?? '') ?></td>
                            <td class="muted"><?= e($ev['place'] ?? '') ?></td>
                            <td>
                                事前 <?= e(format_amount((int) ($ev['amount'] ?? 0), $ev['currency'] ?? 'jpy')) ?>
                                <?php if (!empty($ev['allow_onsite'])): ?><br><span class="muted">当日 <?= e(format_amount((int) ($ev['amount_onsite'] ?? 0), $ev['currency'] ?? 'jpy')) ?></span><?php endif; ?>
                            </td>
                            <td><button type="button" class="btn btn--ghost" style="padding:6px 10px; font-size:.8rem;"
                                    onclick="copyLink(this, <?= htmlspecialchars(json_encode($applyUrl), ENT_QUOTES) ?>)">リンクをコピー</button></td>
                            <td>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <a class="btn" href="events.php?edit=<?= e($ev['id']) ?>">編集</a>
                                    <?php
                                        $delMsg = '「' . ($ev['name'] ?? '') . '」を削除します。';
                                        if (!empty($cnt)) {
                                            $delMsg .= 'このイベントには現在 ' . (int) $cnt . ' 名の参加者がいます。削除すると返金やクレーム対応が必要になる場合があります。';
                                        }
                                        $delMsg .= 'よろしいですか？（過去の申込・決済データは Stripe に残ります。参加者には中止メールを送信します）';
                                    ?>
                                    <form method="post" action="event_delete.php"
                                          onsubmit="return confirm('<?= e(addslashes($delMsg)) ?>');">
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
<script>
    function copyLink(btn, url) {
        navigator.clipboard.writeText(url).then(function () {
            var t = btn.textContent; btn.textContent = 'コピーしました';
            setTimeout(function () { btn.textContent = t; }, 1500);
        }).catch(function () { window.prompt('このリンクをコピーしてください', url); });
    }
    function closeEventModal() {
        var m = document.getElementById('eventModal');
        if (m) m.classList.remove('is-open');
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeEventModal();
    });
</script>
<?php require __DIR__ . '/_app_footer.php'; ?>
