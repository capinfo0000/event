<?php

/**
 * 参加者管理ダッシュボード（ログイン中テナント専用）。
 *
 * 名簿は運営者自身の Stripe アカウントから取得する（DBには参加者を持たない）。
 * - 自分のイベントを選んで参加者一覧を表示
 * - 事前決済の返金（全額＝キャンセル／一部）、当日支払いの集金確認・取消
 * - CSV ダウンロード
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
// 名簿は運営者の Stripe から取得する。画面登録鍵→Connect→プラットフォームの順で文脈確立。
$account = stripe_resolve_tenant($tenant);

$events = tenant_events($tenant['id']);
$selectedId = (string) ($_GET['event_id'] ?? ($events[0]['id'] ?? ''));
$selectedEvent = $selectedId !== '' ? find_event($selectedId) : null;
// 他テナントのイベントIDを指定されても見せない
if ($selectedEvent !== null && $selectedEvent['tenant_id'] !== $tenant['id']) {
    $selectedEvent = null;
}

// 直前の操作の結果メッセージ（リダイレクトで引き継ぎ）
$flash = (string) ($_GET['msg'] ?? '');
$flashType = (string) ($_GET['type'] ?? '');

$participants = [];
$fetchError = '';
$totalCount = 0;
$prepaidCount = 0;
$onsiteCount = 0;
$onsiteCollectedCount = 0;
$collected = 0;   // 事前決済の入金合計
$refunded = 0;    // 返金合計
$onsiteDue = 0;   // 当日支払い予定（未収）合計
$attendedCount = 0; // 出席確認済みの申込数（頭数ではなく行数）
$headcount = 0;     // 参加予定の頭数（返金済みを除く party_size 合計）

if ($selectedEvent !== null && !stripe_ready_for_tenant($tenant)) {
    $fetchError = 'Stripe キーが未設定のため名簿を取得できません。「Stripe設定」から鍵を登録してください。';
} elseif ($selectedEvent !== null) {
    try {
        $participants = fetch_event_participants($selectedId, $account);
        $totalCount = count($participants);
        foreach ($participants as $p) {
            $isCancelled = !empty($p['cancelled']);
            if (!empty($p['attended'])) {
                $attendedCount++;
            }
            if (empty($p['fully_refunded']) && !$isCancelled) {
                $headcount += max(1, (int) $p['party_size']);
            }
            if (($p['payment_type'] ?? 'prepay') === 'onsite') {
                $onsiteCount++;
                if (!empty($p['collected'])) {
                    $onsiteCollectedCount++;
                } elseif (!$isCancelled) {
                    $onsiteDue += $p['amount']; // キャンセル済みは未収に数えない
                }
            } else {
                $prepaidCount++;
                $collected += $p['amount'];
                $refunded += $p['amount_refunded'];
            }
        }
    } catch (\Throwable $ex) {
        $fetchError = 'Stripe から名簿を取得できませんでした: ' . $ex->getMessage();
        error_log('参加者取得失敗: ' . $ex->getMessage());
    }
}

$token = csrf_token();

$pageTitle = '参加者管理';
$pageSub = '名簿はあなたの Stripe アカウントから取得しています（参加者DBは持ちません）';
require __DIR__ . '/_app_header.php';
?>
<style nonce="<?= e(csp_nonce()) ?>">
    .bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin: 0 0 18px; }
    .bar select { width: auto; }
    .refund-form { display: flex; gap: 6px; align-items: center; }
    .refund-form input { width: 92px; }
    /* 統計カードは常に横一列（返金合計まで折り返さない）。狭い画面のみ自動折返し。 */
    .stat-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
    @media (max-width: 900px) {
        .stat-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
    }
    /* この画面は表が横に広いので、ページ幅の上限（1100px）を外して左右の余白まで使い切る。 */
    .page { max-width: none; }
    /* 表のセルは改行させない（枠が狭くて文字が縦に折れないように）。横幅が足りなければ表ごと横スクロール。 */
    .table-wrap th, .table-wrap td { white-space: nowrap; }
    /* 列を少しコンパクトにして、できるだけ横スクロールなしで収める。 */
    .table-wrap th, .table-wrap td { padding-left: 10px; padding-right: 10px; }
    /* 請求管理モーダル内のセクション・返金行 */
    .mgr-sec { padding: 4px 0 10px; border-top: 1px solid var(--border); margin-top: 10px; }
    .mgr-sec:first-of-type { border-top: 0; margin-top: 0; }
    .mgr-sec__h { font-weight: 800; margin: 10px 0 6px; }
    .rfrow { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); }
    .rfrow__name { flex: 1 1 160px; min-width: 0; display: flex; flex-direction: column; }
    .rfrow__pct { display: flex; gap: 4px; }
    .rfrow .pctbtn { padding: 4px 8px; font-size: .8rem; }
    .rfrow .pctbtn.is-active { background: var(--navy); color: #fff; }
    .rfrow input[type=number] { width: 150px; }
    #bulkModal input[type=checkbox] { width: auto; flex: 0 0 auto; margin: 0; }
    #bulkModal label { font-weight: 400; }
    /* スマホ時は余白を詰める（この画面のみ） */
    @media (max-width: 720px) {
        .page { padding: 10px 10px 20px; }
        .topbar { padding: 10px 12px; }
        .topbar__title { font-size: 1.12rem; }
        .topbar__sub { font-size: .78rem; }
        .stat-grid { gap: 8px; margin-bottom: 12px; }
        .stat { padding: 10px 12px; }
        .stat__num { font-size: 1.25rem; }
        .stat__label { font-size: .72rem; }
        .bar { gap: 8px; margin-bottom: 10px; }
        .psearchbar { margin-bottom: 8px; }
        .table-wrap th, .table-wrap td { padding-left: 8px; padding-right: 8px; }
        .ptbl td { padding-top: 9px; padding-bottom: 9px; }
        .modal__box { margin: 12px auto; padding: 16px 16px; }
        .searchgrid { grid-template-columns: 1fr 1fr; gap: 10px; }
    }
</style>

<?php if ($flash !== ''): ?>
    <div class="flash <?= $flashType === 'ok' ? 'flash--ok' : 'flash--ng' ?>"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (empty($events)): ?>
    <div class="err">まだイベントがありません。<a href="events.php">イベント管理</a>から登録してください。</div>
<?php else: ?>
<form method="get" class="bar">
    <label style="margin:0; font-weight:600;">イベント：</label>
    <select name="event_id" class="js-autosubmit">
        <?php foreach ($events as $ev): ?>
            <option value="<?= e($ev['id']) ?>" <?= $ev['id'] === $selectedId ? 'selected' : '' ?>>
                <?= e($ev['name'] ?? $ev['id']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn">表示</button></noscript>
    <?php if ($selectedEvent !== null): ?>
        <a class="btn btn--ghost" href="export.php?event_id=<?= e($selectedId) ?>">CSV ダウンロード</a>
        <a class="btn btn--ghost" href="../apply.php?event_id=<?= e($selectedId) ?>" target="_blank">申込ページを開く</a>
    <?php endif; ?>
</form>
<?php endif; ?>

<?php if ($selectedEvent !== null): ?>
    <p class="muted">
        <?= e($selectedEvent['date'] ?? '') ?>　<?= e($selectedEvent['place'] ?? '') ?>
        <?php if (!empty($selectedEvent['capacity'])): ?>　／ 定員目安: <?= (int) $selectedEvent['capacity'] ?> 名<?php endif; ?>
    </p>
<?php endif; ?>

<?php if ($fetchError !== ''): ?>
    <p class="err"><?= e($fetchError) ?></p>
<?php elseif ($selectedEvent === null): ?>
    <p class="err">イベントが選択されていません。</p>
<?php else: ?>
    <?php $cur0 = $selectedEvent['currency'] ?? 'jpy'; ?>
    <?php $cap = (int) ($selectedEvent['capacity'] ?? 0); ?>
    <?php // 当日払いキャンセル料の率（開催日からの逆算・ポリシー区分）。全参加者共通。
          $feeInfo = cancellation_fee_rate_for_event((string) ($selectedEvent['date'] ?? '')); ?>
    <?php
        // 当日払いの一括操作（キャンセル料請求・再送／名簿削除）の対象＝当日払いの人すべて。
        // （入金済みの人も「名簿から削除」できるよう一覧に含める。請求は自動でスキップされる）
        $onsiteList = [];
        foreach ($participants as $pp1) {
            if (($pp1['payment_type'] ?? '') !== 'onsite') { continue; }
            $onsiteList[] = $pp1;
        }
    ?>
    <div class="stat-grid">
        <div class="stat"><span class="stat__num accent"><?= $headcount ?><?= $cap > 0 ? ' / ' . $cap : '' ?></span><span class="stat__label">参加人数<?= $cap > 0 ? '（定員）' : '' ?></span></div>
        <div class="stat"><span class="stat__num"><?= $totalCount ?></span><span class="stat__label">申込数（事前<?= $prepaidCount ?>・当日<?= $onsiteCount ?>）</span></div>
        <div class="stat"><span class="stat__num"><?= $attendedCount ?> / <?= $totalCount ?></span><span class="stat__label">出席確認済み</span></div>
        <div class="stat"><span class="stat__num"><?= e(format_amount($collected, $cur0)) ?></span><span class="stat__label">事前入金合計</span></div>
        <div class="stat"><span class="stat__num"><?= e(format_amount($onsiteDue, $cur0)) ?></span><span class="stat__label">当日・未収（受領 <?= $onsiteCollectedCount ?>/<?= $onsiteCount ?>）</span></div>
        <div class="stat"><span class="stat__num"><?= e(format_amount($refunded, $cur0)) ?></span><span class="stat__label">返金合計</span></div>
    </div>

    <?php
        // 事前決済の返金対象＝全額返金済みでなく、実受取額（Stripe手数料を除く）の残りがある人。
        $refundList = [];
        foreach ($participants as $pp2) {
            if (($pp2['payment_type'] ?? 'prepay') === 'onsite') { continue; }
            if (!empty($pp2['fully_refunded'])) { continue; }
            $rem = (int) (($pp2['net'] ?? $pp2['amount']) - $pp2['amount_refunded']);
            if ($rem <= 0) { continue; }
            $pp2['remaining'] = $rem;
            $refundList[] = $pp2;
        }
    ?>
    <?php if ($onsiteList !== [] || $refundList !== []): ?>
        <p style="margin:0 0 14px;">
            <button type="button" class="btn btn--danger" data-modal-open="bulkModal">請求管理</button>
            <span class="muted" style="font-size:.82rem; margin-left:8px;">当日払いの<strong>キャンセル料の請求・キャンセル処理</strong>や、事前決済の<strong>一部返金（％で選択）</strong>をまとめて行えます</span>
        </p>
        <div class="modal" id="bulkModal" role="dialog" aria-modal="true">
            <div class="modal__box">
                <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
                <div class="modal__title">請求管理</div>

                <?php if ($onsiteList !== []): ?>
                <div class="mgr-sec">
                    <div class="mgr-sec__h">当日払い（キャンセル料の請求・キャンセル処理）</div>
                    <p class="modal__lead">当日払いの方を選んで、キャンセル料の請求メール送信（未請求の方へ新規／請求済みの方へ再送）、または<strong>キャンセル済みへの変更</strong>ができます。キャンセルにしても<strong>名簿からは削除されず状態が変わるだけ</strong>です。金額はポリシー逆算で自動（<?= e($feeInfo['label']) ?>）。<strong>初期選択は「未受領・未請求」の方</strong>です。</p>
                    <form id="bulkForm" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                        <input type="hidden" name="event_id" value="<?= e($selectedId) ?>">
                        <label style="display:flex; gap:8px; align-items:center; padding:6px 0; border-bottom:1px solid var(--border); font-weight:700;">
                            <input type="checkbox" id="bulkAll"> すべて選択 / 解除
                        </label>
                        <div style="max-height:280px; overflow:auto; margin:4px 0;">
                            <?php foreach ($onsiteList as $op): ?>
                                <?php
                                    // 初期選択＝未受領・未請求・未入金（＝新規に請求する候補）。
                                    $precheck = empty($op['collected']) && empty($op['fee_link_sent']) && empty($op['fee_paid']);
                                    $opFee = (int) round(((int) $op['amount']) * (float) $feeInfo['rate']);
                                    $noEmail = trim((string) ($op['email'] ?? '')) === '';
                                ?>
                                <label style="display:flex; gap:10px; align-items:center; padding:8px 0; border-bottom:1px solid var(--border);">
                                    <input type="checkbox" class="bulkChk" name="customer_ids[]" value="<?= e($op['customer_id']) ?>" <?= $precheck ? 'checked' : '' ?>>
                                    <span style="flex:1; min-width:0;">
                                        <strong><?= e($op['name'] !== '' ? $op['name'] : '（未入力）') ?></strong>
                                        <span class="muted" style="font-size:.82rem;">
                                            <?= e(format_amount((int) $op['amount'], $cur0)) ?>
                                            <?php if (!empty($op['fee_paid'])): ?>・<span style="color:var(--ok,#16a34a);">キャンセル料 入金済み</span><?php else: ?>・<?= !empty($op['collected']) ? '受領済み' : '未受領' ?><?php endif; ?>
                                            <?= !empty($op['cancelled']) ? '・<span style="color:var(--dng,#dc2626);">キャンセル済み</span>' : '' ?>
                                            <?= !empty($op['attended']) ? '・出席済み' : '' ?>
                                            <?= (!empty($op['fee_link_sent']) && empty($op['fee_paid'])) ? '・請求済み（未入金）' : '' ?>
                                            <?= $noEmail ? '・メールなし' : '' ?>
                                        </span>
                                    </span>
                                    <span class="muted" style="font-size:.82rem; white-space:nowrap;">料金 <?= $opFee > 0 ? e(format_amount($opFee, $cur0)) : 'なし' ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="hint">※ キャンセル料の請求は「メール有り・料金&gt;0」の方にのみ送信します（メールなし・料金0の方は自動スキップ）。</p>
                        <div class="modal__actions">
                            <button type="submit" formaction="onsite_fee_bulk.php" class="btn btn--danger" data-confirm="選択した当日払いの方へ、キャンセル料の請求メールを送信します。よろしいですか？（金額はポリシー逆算で自動）">選択者にキャンセル料を請求</button>
                            <button type="submit" formaction="onsite_cancel_bulk.php" class="btn btn--ghost" data-confirm="選択した当日払いの方を「キャンセル済み」にします。よろしいですか？（名簿からは削除されず、状態が変わるだけです）">選択者をキャンセルにする</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($refundList !== []): ?>
                <div class="mgr-sec">
                    <div class="mgr-sec__h">事前決済の返金（％で選択／金額入力）</div>
                    <p class="modal__lead">返金する<strong>割合（％）</strong>を選ぶか金額を入力して「返金」を押します。<strong>全額は Stripe手数料を除いた実受取額</strong>を返します（手数料は戻りません）。</p>
                    <div style="max-height:300px; overflow:auto; margin:4px 0;">
                        <?php foreach ($refundList as $rp): ?>
                            <?php $rcur = (string) $rp['currency']; $rjpy = strtolower($rcur) === 'jpy'; $rrem = (int) $rp['remaining']; ?>
                            <form method="post" action="refund.php" class="rfrow" data-confirm="「<?= e($rp['name']) ?>」さんへ返金します。よろしいですか？（空欄＝全額返金は手数料を除いた実受取額）">
                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                <input type="hidden" name="event_id" value="<?= e($selectedId) ?>">
                                <input type="hidden" name="payment_intent" value="<?= e($rp['payment_intent']) ?>">
                                <div class="rfrow__name">
                                    <strong><?= e($rp['name'] !== '' ? $rp['name'] : '（未入力）') ?></strong>
                                    <span class="muted" style="font-size:.8rem;">残り <?= e(format_amount($rrem, $rcur)) ?><?= !empty($rp['cancel_requested']) ? ' ・<span style="color:#dc2626;">返金承認待ち</span>' : '' ?></span>
                                </div>
                                <div class="rfrow__pct">
                                    <button type="button" class="btn btn--ghost pctbtn" data-pct="25">25%</button>
                                    <button type="button" class="btn btn--ghost pctbtn" data-pct="50">50%</button>
                                    <button type="button" class="btn btn--ghost pctbtn" data-pct="75">75%</button>
                                    <button type="button" class="btn btn--ghost pctbtn" data-pct="100">全額</button>
                                </div>
                                <?php if ($rjpy): ?>
                                    <input type="number" class="rfamt" name="amount" min="1" max="<?= $rrem ?>" placeholder="金額¥（空欄=全額）" data-remaining="<?= $rrem ?>" data-cur="jpy">
                                <?php else: ?>
                                    <input type="number" class="rfamt" name="amount" step="0.01" min="0.01" placeholder="金額（空欄=全額）" data-remaining="<?= $rrem ?>" data-cur="other">
                                <?php endif; ?>
                                <button type="submit" class="btn btn--danger"><?= !empty($rp['cancel_requested']) ? '承認して返金' : '返金' ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <p class="hint">※ ％は「実受取額の残り」に対する割合です。全額を選ぶと入力欄は空欄（＝実受取額の全額返金）になります。</p>
                </div>
                <?php endif; ?>

                <div class="modal__actions" style="justify-content:flex-end;">
                    <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
                </div>
            </div>
        </div>
        <script nonce="<?= e(csp_nonce()) ?>">
            (function(){
                var all = document.getElementById('bulkAll');
                if (all) { all.addEventListener('change', function(){
                    document.querySelectorAll('.bulkChk').forEach(function(c){ c.checked = all.checked; });
                }); }
                // ％ボタンで返金額を自動入力（全額＝空欄）
                document.querySelectorAll('#bulkModal .pctbtn').forEach(function(btn){
                    btn.addEventListener('click', function(){
                        var form = btn.closest('form');
                        var inp = form && form.querySelector('.rfamt');
                        if (!inp) { return; }
                        var rem = parseFloat(inp.getAttribute('data-remaining')) || 0;
                        var pct = parseFloat(btn.getAttribute('data-pct')) || 0;
                        if (pct >= 100) {
                            inp.value = '';
                        } else {
                            var v = rem * pct / 100;
                            inp.value = (inp.getAttribute('data-cur') === 'jpy') ? String(Math.round(v)) : v.toFixed(2);
                        }
                        form.querySelectorAll('.pctbtn').forEach(function(b){ b.classList.remove('is-active'); });
                        btn.classList.add('is-active');
                    });
                });
            })();
        </script>
    <?php endif; ?>

    <?php if ($totalCount === 0): ?>
        <p class="muted">まだ申込はありません。</p>
    <?php else: ?>
        <?php
            // フリガナ相当は「名前の上」に表示するため列から除外。残りの追加項目を列に展開。
            $isKanaLabel = static fn (string $l): bool => (bool) preg_match('/(フリガナ|ふりがな|カナ|かな)/u', $l);
            $customCols = [];
            foreach ($participants as $pp0) {
                foreach (($pp0['custom'] ?? []) as $lab => $val) {
                    if ($isKanaLabel((string) $lab) || in_array($lab, $customCols, true)) { continue; }
                    $customCols[] = $lab;
                }
            }
            // 詳細検索用: 追加項目ごとの絞り込み。年齢は数値範囲、それ以外は値のプルダウン。
            $isAgeLabel = static fn (string $l): bool => (bool) preg_match('/(年齢|歳|age)/ui', $l);
            $customFilters = []; // ci => ['label' => ..., 'values' => [...]]
            foreach ($customCols as $ci => $lab) {
                if ($isAgeLabel((string) $lab)) { continue; } // 年齢は範囲入力で扱う
                $vals = [];
                foreach ($participants as $pp) {
                    $v = trim((string) ($pp['custom'][$lab] ?? ''));
                    if ($v !== '') { $vals[$v] = true; }
                }
                if ($vals !== []) {
                    $keys = array_keys($vals);
                    sort($keys, SORT_NATURAL | SORT_FLAG_CASE);
                    $customFilters[$ci] = ['label' => (string) $lab, 'values' => $keys];
                }
            }
        ?>
        <style nonce="<?= e(csp_nonce()) ?>">
            /* 列を内容幅に合わせる（全幅に引き伸ばして間隔が空きすぎるのを防ぐ）。足りなければ横スクロール。 */
            .ptbl { width: auto; min-width: 0; }
            .ptbl th, .ptbl td { white-space: nowrap; vertical-align: top; background: var(--surface); }
            .ptbl thead th { background: #f8fafc; }
            .ptbl td { padding-top: 12px; padding-bottom: 12px; }
            /* 横スクロールしても「操作」「名前」を固定 */
            .ptbl th.op1, .ptbl td.op1 { position: sticky; left: 0; z-index: 2; width: 98px; min-width: 98px; white-space: nowrap; }
            .ptbl th.op2, .ptbl td.op2 { position: sticky; left: 98px; z-index: 2; width: 116px; min-width: 116px; white-space: nowrap; }
            .ptbl th.nm, .ptbl td.nm { position: sticky; left: 214px; z-index: 2; white-space: nowrap; min-width: 92px; box-shadow: 6px 0 6px -4px rgba(0,0,0,.12); }
            .ptbl .op1 .btn, .ptbl .op2 .btn { white-space: nowrap; }
            /* 列幅は内容ギリギリに：ボタン・セル余白を小さくして各列を詰める */
            .ptbl .btn { padding: 6px 10px; font-size: .82rem; }
            .ptbl th, .ptbl td { padding-left: 7px; padding-right: 7px; }
            .ptbl thead th.op1, .ptbl thead th.op2, .ptbl thead th.nm { z-index: 3; }
            /* 操作ボタンは横並び（幅が足りなければ折り返し） */
            .ptbl .op1 form, .ptbl .op2 form { display: inline-flex; gap: 6px; align-items: center; margin: 0 6px 6px 0; vertical-align: top; }
            .ptbl .op2 input[type=number] { width: 84px; }
            .ptbl .nm .kana { font-size: .72rem; color: var(--muted); line-height: 1.2; }
            .ptbl .nm .nmmain { font-weight: 700; }
            .ptbl .feenote { font-size: .76rem; color: var(--muted); margin-top: 2px; }
            #bulkModal input[type=checkbox] { width: auto; flex: 0 0 auto; margin: 0; }
            #bulkModal label { font-weight: 400; }
            /* 並べ替え可能な見出し */
            .ptbl th[data-sort] { cursor: pointer; user-select: none; }
            .ptbl th[data-sort]::after { content: "⇅"; font-size: .72em; color: var(--muted); margin-left: 4px; }
            .ptbl th[data-sort][data-dir=asc]::after { content: "↑"; color: var(--navy); }
            .ptbl th[data-sort][data-dir=desc]::after { content: "↓"; color: var(--navy); }
            /* 検索バー */
            .psearchbar { display: flex; gap: 10px; align-items: center; margin: 0 0 10px; flex-wrap: wrap; }
            /* 詳細検索モーダルの入力グリッド */
            .searchgrid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px 16px; margin: 6px 0 10px; }
            .searchgrid label { display: flex; flex-direction: column; gap: 4px; font-size: .82rem; font-weight: 700; margin: 0; }
            .searchgrid select, .searchgrid input[type=search] { width: 100%; }
            .searchgrid .fAge span { display: flex; align-items: center; gap: 6px; }
            .searchgrid .fAge input[type=number] { width: 84px; }
            /* スマホ: 文字を小さくして、できるだけ多くの列を表示（固定列も縮小） */
            @media (max-width: 720px) {
                .ptbl th, .ptbl td { font-size: .74rem; }
                .table-wrap th, .table-wrap td { padding-left: 6px; padding-right: 6px; }
                .ptbl td { padding-top: 7px; padding-bottom: 7px; }
                .ptbl .op1 .btn, .ptbl .op2 .btn { padding: 4px 6px; font-size: .7rem; }
                .ptbl .nm .nmmain { font-size: .8rem; }
                .ptbl .nm .kana { font-size: .62rem; }
                .badge { font-size: .66rem; padding: 2px 6px; }
                .ptbl th, .ptbl td { padding-left: 5px; padding-right: 5px; }
                .ptbl th.op1, .ptbl td.op1 { width: 78px; min-width: 78px; }
                .ptbl th.op2, .ptbl td.op2 { left: 78px; width: 92px; min-width: 92px; }
                .ptbl th.nm, .ptbl td.nm { left: 170px; min-width: 72px; }
            }
        </style>
        <div class="psearchbar">
            <button type="button" class="btn btn--ghost" data-modal-open="searchModal" id="advToggle">🔍 詳細検索</button>
            <span class="jsCount muted" style="font-size:.85rem;"></span>
            <span class="muted" style="font-size:.8rem;">※ 見出しをクリックで並べ替え</span>
        </div>
        <div class="modal" id="searchModal" role="dialog" aria-modal="true">
            <div class="modal__box">
                <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
                <div class="modal__title">検索・絞り込み</div>
                <p class="modal__lead">名前・フリガナで検索したり、状態・出席・支払方法などで絞り込めます。入力するとすぐ下の表に反映されます。</p>
                <div class="searchgrid">
                    <label>名前・フリガナ <input type="search" id="psearch" placeholder="名前・フリガナで検索" autocomplete="off"></label>
                    <label>状態 <select id="fStatus"><option value="">すべて</option></select></label>
                    <label>出席 <select id="fAttend"><option value="">すべて</option><option value="1">出席済み</option><option value="0">未確認</option></select></label>
                    <label>支払方法 <select id="fMethod"><option value="">すべて</option></select></label>
                    <?php foreach ($customFilters as $ci => $cf): ?>
                        <label><?= e($cf['label']) ?> <select class="fcust" data-col="<?= (int) $ci ?>"><option value="">すべて</option><?php foreach ($cf['values'] as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
                    <?php endforeach; ?>
                    <label class="fAge">年齢 <span><input type="number" id="fAgeMin" min="0" placeholder="下限"> 〜 <input type="number" id="fAgeMax" min="0" placeholder="上限"></span></label>
                </div>
                <div class="modal__actions">
                    <span class="jsCount muted" style="font-size:.9rem; font-weight:700; margin-right:auto;"></span>
                    <button type="button" class="btn btn--ghost" id="fClear">条件クリア</button>
                    <button type="button" class="btn" data-modal-close>この条件で表示</button>
                </div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="ptbl" id="ptbl">
                <thead>
                    <tr>
                        <th class="op1">出席</th>
                        <th class="op2">集金・返金</th>
                        <th class="nm" data-sort="name">名前</th>
                        <?php foreach ($customCols as $lab): ?><th><?= e($lab) ?></th><?php endforeach; ?>
                        <th data-sort="method">支払方法</th>
                        <th data-sort="amount">金額</th>
                        <th data-sort="status">状態</th>
                        <th data-sort="created">申込日時</th>
                        <th>電話</th>
                        <th>メール</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $p): ?>
                <?php
                    $cur = $p['currency'];
                    $isOnsite = ($p['payment_type'] ?? 'prepay') === 'onsite';
                    $cancelReq = !empty($p['cancel_requested']);
                    $isCancelled = !empty($p['cancelled']);
                    $statusText = '';   // 並べ替え用のプレーンテキスト（バッジの主ラベル）
                    if ($isOnsite) {
                        if ($isCancelled) {
                            $statusText = 'キャンセル済み';
                            $statusHtml = '<span class="badge badge--danger">キャンセル済み</span>';
                            if (!empty($p['fee_paid'])) {
                                $statusHtml .= ' <span class="badge badge--ok" style="font-size:.72rem;">料金 入金済み</span>';
                            } elseif (!empty($p['fee_link_sent'])) {
                                $statusHtml .= ' <span class="badge badge--warn" style="font-size:.72rem;">料金 請求中</span>';
                            }
                        } elseif (!empty($p['fee_paid'])) {
                            $statusText = 'キャンセル料 入金済み';
                            $statusHtml = '<span class="badge badge--ok">キャンセル料 入金済み</span>';
                        } elseif (!empty($p['fee_link_sent'])) {
                            $statusText = 'キャンセル料 請求中';
                            $statusHtml = '<span class="badge badge--warn">キャンセル料 請求中（支払い待ち）</span>';
                        } elseif ($cancelReq) {
                            $statusText = 'キャンセル受付';
                            $statusHtml = '<span class="badge badge--warn">キャンセル受付（料金なし）</span>';
                        } elseif (!empty($p['collected'])) {
                            $statusText = '受領済み';
                            $statusHtml = '<span class="badge badge--ok">受領済み</span>';
                        } else {
                            $statusText = '当日支払い・未収';
                            $statusHtml = '<span class="badge badge--warn">当日支払い・未収</span>';
                        }
                    } elseif ($p['fully_refunded']) {
                        $statusText = 'キャンセル済み（全額返金）';
                        $statusHtml = '<span class="badge badge--danger">キャンセル済み（全額返金）</span>';
                    } elseif ($cancelReq) {
                        $statusText = '返金承認待ち';
                        $statusHtml = '<span class="badge badge--warn">返金承認待ち</span>'
                            . ($p['amount_refunded'] > 0 ? ' <span class="badge badge--warn">一部返金 ' . e(format_amount($p['amount_refunded'], $cur)) . '</span>' : '');
                    } elseif ($p['amount_refunded'] > 0) {
                        $statusText = '一部返金';
                        $statusHtml = '<span class="badge badge--warn">一部返金 ' . e(format_amount($p['amount_refunded'], $cur)) . '</span>';
                    } else {
                        $statusText = '事前決済済み';
                        $statusHtml = '<span class="badge badge--ok">事前決済済み</span>';
                    }
                    // 返金の上限＝「実受取額（Stripe手数料を除いた額）」の残り。全額返金もこの額を返金する。
                    $remaining = (int) (($p['net'] ?? $p['amount']) - $p['amount_refunded']);
                    // フリーワード検索は「名前・フリガナ」のみ。その他の条件は詳細検索で絞り込む。
                    $kana = '';
                    foreach (($p['custom'] ?? []) as $lab => $val) {
                        if ($isKanaLabel((string) $lab)) { $kana = (string) $val; break; }
                    }
                    $searchHay = mb_strtolower(trim((string) ($p['name'] ?? '') . ' ' . $kana));
                    $sortName = $kana !== '' ? $kana : (string) ($p['name'] ?? '');
                    // 年齢（追加項目に「年齢/歳/age」があれば数値だけ取り出して詳細検索に使う）。
                    $ageVal = '';
                    foreach (($p['custom'] ?? []) as $lab => $val) {
                        if (preg_match('/(年齢|歳|age)/ui', (string) $lab) && preg_match('/\d+/', (string) $val, $m)) {
                            $ageVal = $m[0];
                            break;
                        }
                    }
                    $attendedAttr = !empty($p['attended']) ? '1' : '0';
                ?>
                <tr data-search="<?= e($searchHay) ?>" data-name="<?= e($sortName) ?>" data-amount="<?= (int) $p['amount'] ?>" data-created="<?= (int) $p['created'] ?>" data-status="<?= e($statusText) ?>" data-method="<?= $isOnsite ? '当日' : '事前' ?>" data-attended="<?= $attendedAttr ?>" data-age="<?= e($ageVal) ?>"<?php foreach ($customCols as $ci => $lab): ?> data-c<?= (int) $ci ?>="<?= e((string) ($p['custom'][$lab] ?? '')) ?>"<?php endforeach; ?>>
                    <td class="op1">
                        <?php if (!empty($p['customer_id']) && !$isCancelled): ?>
                            <form method="post" action="attend.php">
                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                <input type="hidden" name="event_id" value="<?= e($selectedId) ?>">
                                <input type="hidden" name="customer_id" value="<?= e($p['customer_id']) ?>">
                                <?php if (empty($p['attended'])): ?>
                                    <input type="hidden" name="attend" value="1">
                                    <button type="submit" class="btn">出席にする</button>
                                <?php else: ?>
                                    <input type="hidden" name="attend" value="0">
                                    <button type="submit" class="btn btn--ghost">出席取消</button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="op2">
                        <?php if ($isOnsite): ?>
                            <?php if ($isCancelled || !empty($p['fee_paid']) || !empty($p['fee_link_sent']) || $cancelReq): ?>
                                <?php // キャンセル済み等は行内で操作しない。戻す場合は参加者に再申込してもらう（メールで自動紐づけ）。 ?>
                                <span class="muted">—</span>
                            <?php elseif (empty($p['attended'])): ?>
                                <?php // 受領（集金）は「出席にする」を押してから。未出席のうちは表示しない。 ?>
                                <span class="muted" title="「出席にする」を押すと受領ボタンが出ます">—</span>
                            <?php else: ?>
                                <form method="post" action="onsite_collect.php">
                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                    <input type="hidden" name="event_id" value="<?= e($selectedId) ?>">
                                    <input type="hidden" name="customer_id" value="<?= e($p['customer_id']) ?>">
                                    <?php if (empty($p['collected'])): ?>
                                        <input type="hidden" name="collect" value="1">
                                        <button type="submit" class="btn">受領にする</button>
                                    <?php else: ?>
                                        <input type="hidden" name="collect" value="0">
                                        <button type="submit" class="btn btn--ghost">受領取消</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        <?php elseif ($cancelReq && $remaining > 0 && !$p['fully_refunded']): ?>
                            <?php // 参加者からのキャンセル希望が出ている事前決済のみ「承認して返金」。任意の全額・一部返金は上部の「請求管理」から。 ?>
                            <form method="post" action="refund.php" class="refund-form" data-confirm="「<?= e($p['name']) ?>」さんのキャンセル希望を承認して全額返金します。よろしいですか？（Stripe手数料を除いた実受取額 <?= e(format_amount((int) $remaining, $cur)) ?> を返金します）">
                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                <input type="hidden" name="event_id" value="<?= e($selectedId) ?>">
                                <input type="hidden" name="payment_intent" value="<?= e($p['payment_intent']) ?>">
                                <?php // amount 未指定＝全額返金（実受取額）。 ?>
                                <button type="submit" class="btn btn--danger" title="キャンセル希望を承認し、実受取額 <?= e(format_amount((int) $remaining, $cur)) ?> を全額返金します。">承認して返金</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="nm">
                        <?php if ($kana !== ''): ?><div class="kana"><?= e($kana) ?></div><?php endif; ?>
                        <span class="nmmain"><?= e($p['name'] !== '' ? $p['name'] : '（未入力）') ?></span>
                        <?php if (!empty($p['category'])): ?> <span class="badge" style="font-size:.72rem;">区分:<?= e($p['category']) ?></span><?php endif; ?>
                        <?php if ($p['note'] !== ''): ?> <span class="muted" style="font-size:.8rem;" title="<?= e($p['note']) ?>">[備考]</span><?php endif; ?>
                    </td>
                    <?php foreach ($customCols as $lab): ?><td><?= e($p['custom'][$lab] ?? '') ?></td><?php endforeach; ?>
                    <td><?= $isOnsite ? '当日' : '事前' ?></td>
                    <td><?= e(format_amount($p['amount'], $cur)) ?><br><span class="muted" style="font-size:.78rem;"><?= (int) $p['party_size'] ?>名</span></td>
                    <td><?= $statusHtml ?><?php if (!empty($p['attended'])): ?><br><span class="badge badge--ok" style="font-size:.72rem;">出席済み</span><?php endif; ?></td>
                    <td class="muted"><?= e(date('Y-m-d H:i', $p['created'])) ?></td>
                    <td><?= ($p['phone'] ?? '') !== '' ? e($p['phone']) : '<span class="muted">—</span>' ?></td>
                    <td><?= $p['email'] !== '' ? e($p['email']) : '<span class="muted">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script nonce="<?= e(csp_nonce()) ?>">
            (function(){
                var table = document.getElementById('ptbl');
                if (!table || !table.tBodies.length) { return; }
                var tbody = table.tBodies[0];
                var rows = Array.prototype.slice.call(tbody.rows);
                var total = rows.length;
                var search = document.getElementById('psearch');
                var counts = Array.prototype.slice.call(document.querySelectorAll('.jsCount'));
                var fStatus = document.getElementById('fStatus');
                var fAttend = document.getElementById('fAttend');
                var fMethod = document.getElementById('fMethod');
                var fAgeMin = document.getElementById('fAgeMin');
                var fAgeMax = document.getElementById('fAgeMax');
                var fClear = document.getElementById('fClear');
                var custSels = Array.prototype.slice.call(document.querySelectorAll('.fcust'));

                // 状態・支払方法の選択肢を実データから生成（常に表示中のデータと一致させる）
                function fillSelect(sel, values){
                    if (!sel) { return; }
                    values.sort(function(a, b){ return a.localeCompare(b, 'ja'); });
                    values.forEach(function(v){ var o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o); });
                }
                var statuses = {}, methods = {}, hasAge = false;
                rows.forEach(function(tr){
                    var s = tr.getAttribute('data-status'); if (s) { statuses[s] = 1; }
                    var m = tr.getAttribute('data-method'); if (m) { methods[m] = 1; }
                    if ((tr.getAttribute('data-age') || '') !== '') { hasAge = true; }
                });
                fillSelect(fStatus, Object.keys(statuses));
                fillSelect(fMethod, Object.keys(methods));
                var ageWrap = document.querySelector('#searchModal .fAge');
                if (ageWrap && !hasAge) { ageWrap.style.display = 'none'; }

                function applyFilter(){
                    var q = (search && search.value || '').trim().toLowerCase();
                    var st = fStatus ? fStatus.value : '';
                    var at = fAttend ? fAttend.value : '';
                    var me = fMethod ? fMethod.value : '';
                    var amin = (fAgeMin && fAgeMin.value !== '') ? parseFloat(fAgeMin.value) : null;
                    var amax = (fAgeMax && fAgeMax.value !== '') ? parseFloat(fAgeMax.value) : null;
                    var shown = 0;
                    rows.forEach(function(tr){
                        var ok = true;
                        if (q !== '' && (tr.getAttribute('data-search') || '').indexOf(q) < 0) { ok = false; }
                        if (ok && st !== '' && tr.getAttribute('data-status') !== st) { ok = false; }
                        if (ok && at !== '' && (tr.getAttribute('data-attended') || '0') !== at) { ok = false; }
                        if (ok && me !== '' && tr.getAttribute('data-method') !== me) { ok = false; }
                        if (ok && (amin !== null || amax !== null)) {
                            var av = tr.getAttribute('data-age');
                            if (av === '' || av === null) { ok = false; }
                            else { var n = parseFloat(av); if (amin !== null && n < amin) { ok = false; } if (amax !== null && n > amax) { ok = false; } }
                        }
                        if (ok) {
                            for (var i = 0; i < custSels.length; i++) {
                                var cv = custSels[i].value;
                                if (cv !== '' && tr.getAttribute('data-c' + custSels[i].getAttribute('data-col')) !== cv) { ok = false; break; }
                            }
                        }
                        tr.style.display = ok ? '' : 'none';
                        if (ok) { shown++; }
                    });
                    var custActive = custSels.some(function(cs){ return cs.value !== ''; });
                    var active = q !== '' || st !== '' || at !== '' || me !== '' || amin !== null || amax !== null || custActive;
                    var label = active ? (shown + ' / ' + total + ' 件') : (total + ' 件');
                    counts.forEach(function(c){ c.textContent = label; });
                }
                [search, fStatus, fAttend, fMethod, fAgeMin, fAgeMax].concat(custSels).forEach(function(el){
                    if (el) { el.addEventListener('input', applyFilter); el.addEventListener('change', applyFilter); }
                });
                if (fClear) {
                    fClear.addEventListener('click', function(){
                        if (search) { search.value = ''; }
                        if (fStatus) { fStatus.value = ''; }
                        if (fAttend) { fAttend.value = ''; }
                        if (fMethod) { fMethod.value = ''; }
                        if (fAgeMin) { fAgeMin.value = ''; }
                        if (fAgeMax) { fAgeMax.value = ''; }
                        custSels.forEach(function(cs){ cs.value = ''; });
                        applyFilter();
                    });
                }
                applyFilter();
                var curKey = null, curDir = 1;
                Array.prototype.forEach.call(table.querySelectorAll('th[data-sort]'), function(th){
                    th.addEventListener('click', function(){
                        var key = th.getAttribute('data-sort');
                        if (curKey === key) { curDir = -curDir; } else { curKey = key; curDir = 1; }
                        var sorted = rows.slice().sort(function(a, b){
                            var va = a.getAttribute('data-' + key) || '', vb = b.getAttribute('data-' + key) || '';
                            var na = parseFloat(va), nb = parseFloat(vb);
                            var isNum = va !== '' && vb !== '' && !isNaN(na) && !isNaN(nb);
                            var cmp = isNum ? (na - nb) : va.localeCompare(vb, 'ja');
                            return cmp * curDir;
                        });
                        sorted.forEach(function(r){ tbody.appendChild(r); });
                        Array.prototype.forEach.call(table.querySelectorAll('th[data-sort]'), function(h){ h.removeAttribute('data-dir'); });
                        th.setAttribute('data-dir', curDir > 0 ? 'asc' : 'desc');
                    });
                });
            })();
        </script>
        <p class="muted" style="margin-top:10px;">表内の<strong>「承認して返金」</strong>は、参加者からの<strong>キャンセル希望を承認して全額返金</strong>するボタンです（Stripe手数料を除いた実受取額を返金。例：¥50決済で手数料¥2なら¥48）。キャンセル希望がない方への<strong>任意の全額・一部（％）返金</strong>は上部の<strong>「請求管理」</strong>から行えます。</p>
        <p class="muted" style="margin-top:4px;">⚠️ Stripe の決済手数料は返金時に戻りません。この仕組みでは<strong>手数料分は参加者の実質負担</strong>となります（全額返金でも参加者へ戻るのは実受取額まで）。トラブル防止のため、キャンセル・返金ポリシーに明記することをおすすめします。</p>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/_app_footer.php'; ?>
