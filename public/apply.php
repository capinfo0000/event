<?php

/**
 * 参加申込フォーム（自前）。氏名・連絡先・参加人数・備考を入力してもらい、
 * その内容を checkout.php へ渡して Stripe 決済へ進む。
 *
 * 【DBは持たない】入力内容は当サーバーに保存せず、Stripe の決済データ（metadata 等）
 * として渡して保管する。名簿は管理画面が Stripe から読み出して表示する。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$eventId = (string) ($_GET['event_id'] ?? '');
$event = find_event($eventId);

if ($event === null) {
    http_response_code(404);
    exit('指定されたイベントが見つかりません。');
}

// 濫用対策: 公開ページの連打（Stripe集計の増幅・偵察）を IP 単位で緩く制限する。
if (!rate_limit_check('view_apply', 120, 300)) {
    http_response_code(429);
    exit('アクセスが多すぎます。しばらくしてから再度お開きください。');
}

// 運営者の Stripe が使えるか（画面登録鍵 or Connect/プラットフォーム）。未設定でもフォームは表示。
$stripeReady = stripe_ready_for_event($event);
$account = stripe_resolve_event($event); // 残席計算で使う Stripe 文脈を確立

// 定員と残席（capacity>0 のとき）。取得に失敗しても申込は止めない。
$capacity = (int) ($event['capacity'] ?? 0);
$remaining = null; // null = 定員管理なし／不明
$isFull = false;
if ($capacity > 0 && $stripeReady) {
    try {
        $remaining = max(0, $capacity - event_headcount_cached($event['id'], $account));
        $isFull = ($remaining <= 0);
    } catch (\Throwable $e) {
        $remaining = null;
    }
}

$currency = $event['currency'] ?? 'jpy';
$prepayUnit = (int) ($event['amount'] ?? 0);
// 当日料金は未設定なら事前と同額
$onsiteUnit = isset($event['amount_onsite']) && $event['amount_onsite'] !== ''
    ? (int) $event['amount_onsite']
    : $prepayUnit;

// 受け付ける支払い方法（既存イベントには allow_* が無いので事前決済を既定で許可）
$allowPrepay = array_key_exists('allow_prepay', $event) ? !empty($event['allow_prepay']) : true;
$allowOnsite = !empty($event['allow_onsite']);
if (!$allowPrepay && !$allowOnsite) {
    $allowPrepay = true; // 念のため最低1つは有効に
}
$defaultMethod = $allowPrepay ? 'prepay' : 'onsite';
$defaultUnit = $defaultMethod === 'prepay' ? $prepayUnit : $onsiteUnit;

// 参加人数の上限: capacity があればそれ、無ければ 10 を目安に
$maxParty = (int) ($event['capacity'] ?? 0);
if ($maxParty < 1) {
    $maxParty = 10;
}
$maxParty = min($maxParty, 20);

// 料金区分（男性/女性等）。設定があれば「区分を1つ選ぶ・1申込=1名」の申込に切り替える。
$tiers = $event['tiers'] ?? [];
$hasTiers = !empty($tiers);
if ($hasTiers) {
    $defaultUnit = (int) $tiers[0]['amount']; // 初期表示は先頭区分
    $maxParty = 1;
}

// 主催者が定義したカスタム入力項目。設定があれば固定の標準項目（氏名/電話/人数/備考）は出さず、
// メール（固定）＋性別（＝料金区分・設定時）＋定義した項目のみにする。1申込=1名。
$customFields = $event['custom_fields'] ?? [];
$hasCustom = !empty($customFields);
if ($hasCustom) {
    $maxParty = 1;
}
$cfInputType = ['text' => 'text', 'number' => 'number', 'tel' => 'tel']; // textarea は別扱い

// キャンセルポリシーは申込ページ内のモーダルで表示（別ページへ遷移させない）。
$policyOwner = find_tenant_by_id((string) ($event['tenant_id'] ?? ''));
$customPolicy = ($policyOwner !== null && trim((string) ($policyOwner['cancel_policy'] ?? '')) !== '')
    ? (string) $policyOwner['cancel_policy'] : null;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>参加申込 - <?= e($event['name'] ?? '') ?></title>
    <link rel="stylesheet" href="/assets/app.css?v=3">
    <script src="/assets/app.js?v=3" defer></script>
    <style nonce="<?= e(csp_nonce()) ?>">
        .pay-options { display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
        .pay-options label { font-weight: 400; display: flex; gap: 8px; align-items: center; margin: 0; }
        .pay-options input[type=radio] { width: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="brandbar">イベント参加申込</div>
    <h1><?= e($event['name'] ?? '') ?></h1>
    <div class="card">
        <p class="muted"><?= e($event['date'] ?? '') ?>　<?= e($event['place'] ?? '') ?></p>
        <p><?= e($event['description'] ?? '') ?></p>
        <p class="muted">
            <?php if ($hasTiers): ?>
                <?php foreach ($tiers as $ti => $t): ?><?= $ti > 0 ? '　／　' : '' ?><?= e($t['label']) ?>：<strong>事前 <?= e(format_amount((int) $t['amount'], $currency)) ?></strong><?php if ($allowOnsite): ?>／当日 <strong><?= e(format_amount((int) $t['amount_onsite'], $currency)) ?></strong><?php endif; ?><?php endforeach; ?>
            <?php else: ?>
                <?php if ($allowPrepay): ?>事前決済：<strong><?= e(format_amount($prepayUnit, $currency)) ?></strong> / 1名<?php endif; ?>
                <?php if ($allowPrepay && $allowOnsite): ?>　／　<?php endif; ?>
                <?php if ($allowOnsite): ?>当日支払い：<strong><?= e(format_amount($onsiteUnit, $currency)) ?></strong> / 1名<?php endif; ?>
            <?php endif; ?>
        </p>
        <?php if ($capacity > 0 && $remaining !== null): ?>
            <p class="muted">定員 <?= $capacity ?> 名　<?= $isFull ? '<strong style="color:#dc2626;">満員</strong>' : '残り <strong>' . $remaining . '</strong> 名' ?></p>
        <?php endif; ?>
    </div>

    <?php if ($isFull): ?>
        <div class="card"><p style="font-weight:700; color:#dc2626;">申し訳ありません。定員に達したため、受付を終了しました。</p></div>
    <?php else: ?>
    <form action="checkout.php" method="post" class="card">
        <input type="hidden" name="event_id" value="<?= e($event['id']) ?>">

        <?php
        // 表示順（固定）: 氏名 → 氏名フリガナ → 年齢 →（性別）→ メール → 紹介者。
        // 選択された項目（$customFields）を pre / post に振り分けて描画する。1申込＝1名。
        $renderField = static function (int $ci, array $f) use ($cfInputType): void {
            $req = !empty($f['required']); ?>
            <label for="cf<?= $ci ?>"><?= e($f['label']) ?> <?php if ($req): ?><span class="req">必須</span><?php endif; ?></label>
            <?php if (($f['type'] ?? 'text') === 'textarea'): ?>
                <textarea id="cf<?= $ci ?>" name="cf[<?= $ci ?>]" maxlength="500" <?= $req ? 'required' : '' ?>></textarea>
            <?php else: ?>
                <input type="<?= e($cfInputType[$f['type'] ?? 'text'] ?? 'text') ?>" id="cf<?= $ci ?>" name="cf[<?= $ci ?>]" maxlength="200" <?= $req ? 'required' : '' ?>>
            <?php endif; ?>
        <?php }; ?>

        <?php foreach ($customFields as $ci => $f): if (field_slot_for_label($f['label']) === 'pre') { $renderField($ci, $f); } endforeach; ?>

        <?php if ($hasTiers): ?>
            <label>性別 <span class="req">必須</span></label>
            <div class="pay-options">
                <?php foreach ($tiers as $ti => $t): ?>
                    <label style="font-weight:400; display:flex; gap:8px; align-items:center; width:auto;">
                        <input type="radio" name="tier" value="<?= e($t['label']) ?>" <?= $ti === 0 ? 'checked' : '' ?> style="width:auto;" required>
                        <?= e($t['label']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label for="email">メールアドレス <span class="req">必須</span></label>
        <input type="email" id="email" name="email" required maxlength="200" autocomplete="email" placeholder="taro@example.com">

        <?php foreach ($customFields as $ci => $f): if (field_slot_for_label($f['label']) === 'post') { $renderField($ci, $f); } endforeach; ?>

        <input type="hidden" name="party_size" value="1">

        <label>お支払い方法 <span class="req">必須</span></label>
        <div class="pay-options">
            <?php if ($allowPrepay): ?>
                <label style="font-weight:400; display:flex; gap:8px; align-items:center; width:auto;">
                    <input type="radio" name="payment_type" value="prepay" <?= $defaultMethod === 'prepay' ? 'checked' : '' ?> style="width:auto;">
                    事前決済
                </label>
            <?php endif; ?>
            <?php if ($allowOnsite): ?>
                <label style="font-weight:400; display:flex; gap:8px; align-items:center; width:auto;">
                    <input type="radio" name="payment_type" value="onsite" <?= $defaultMethod === 'onsite' ? 'checked' : '' ?> style="width:auto;">
                    当日支払い
                </label>
            <?php endif; ?>
        </div>

        <p class="total">お支払い合計：<span id="total"><?= e(format_amount($defaultUnit, $currency)) ?></span> <span id="total-note" class="hint" style="margin:0;"></span></p>

        <?= captcha_widget_html() ?>
        <?php $blockedInit = (!$stripeReady && $defaultMethod === 'prepay'); ?>
        <button type="submit" class="btn btn--block btn--lg" id="submitBtn" <?= $blockedInit ? 'disabled' : '' ?>><?= $defaultMethod === 'onsite' ? 'この内容で申し込む（当日支払い）→' : '事前決済する →' ?></button>
        <?php if (!$stripeReady): ?>
            <p class="notice" id="prepayBlockNote" style="<?= $blockedInit ? '' : 'display:none;' ?>">⚠️ 現在この主催者は支払い口座の設定が完了していないため、<strong>事前決済（オンライン前払い）</strong>は利用できません。<?= $allowOnsite ? '「当日支払い」を選んでお申し込みください。' : '準備が整うまでお待ちください。' ?></p>
        <?php endif; ?>
        <p class="hint" id="methodNote"></p>
        <p class="hint">キャンセル時の返金は<button type="button" class="btn btn--ghost" data-modal-open="policyInfo" style="padding:3px 10px; font-size:.82rem; vertical-align:baseline;">キャンセルポリシー</button>をご確認ください。</p>
        <p class="hint">ご参加いただけなくなった場合は、<strong>開催日前までに</strong>キャンセルのご連絡をお願いします。無断キャンセル（無連絡不参加）は、キャンセルポリシーに基づき<strong>キャンセル料</strong>が発生する場合があります。</p>
        <p style="margin-top:6px;"><a class="btn btn--ghost" href="cancel_request.php?event_id=<?= e($event['id']) ?>">キャンセル連絡はこちら →</a></p>
        <?php if ($allowPrepay): ?>
            <p class="hint"><button type="button" class="btn btn--ghost" data-modal-open="prepayInfo">事前決済（カード）の安全性について</button></p>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_prepay_info_modal.php'; ?>

<div class="modal" id="policyInfo" role="dialog" aria-modal="true">
    <div class="modal__box">
        <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
        <div class="modal__title">キャンセル・返金ポリシー</div>
        <?php if ($customPolicy !== null): ?>
            <p><?= nl2br(e($customPolicy)) ?></p>
        <?php else: ?>
            <p>本イベントの参加費は<strong>事前決済（前払い）</strong>または<strong>当日払い</strong>でお受けします。キャンセルの取り扱いは以下のとおりです。</p>
            <p style="margin:8px 0 4px; font-weight:700;">事前決済のキャンセル・返金（開催日基準）</p>
            <div class="table-wrap" style="margin:6px 0 12px;">
                <table>
                    <thead><tr><th>キャンセル時期</th><th>返金額</th></tr></thead>
                    <tbody>
                        <tr><td>開催 8 日前まで</td><td>Stripe手数料を差し引いた全額を返金</td></tr>
                        <tr><td>開催 7〜2 日前</td><td>50% 返金</td></tr>
                        <tr><td>開催前日・当日／無連絡不参加</td><td>返金なし</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="muted" style="font-size:.85rem;">※「全額返金」は、決済時にかかった Stripe 手数料を差し引いた全額（主催者の実受取額）の返金を指します。手数料は返金時に戻らないため、その分は差し引かれます。<br>※ 主催者都合での中止（荒天等）の場合も、Stripe手数料を差し引いた全額を返金します。</p>
            <p style="margin:12px 0 4px; font-weight:700;">当日払いのキャンセル</p>
            <p class="muted" style="font-size:.85rem; margin:0 0 6px;">当日払いは事前の決済は発生しませんが、キャンセルの場合は下記のキャンセル料を申し受けます（開催日基準）。</p>
            <div class="table-wrap" style="margin:0 0 8px;">
                <table>
                    <thead><tr><th>キャンセル時期</th><th>キャンセル料</th></tr></thead>
                    <tbody>
                        <tr><td>開催 8 日前まで</td><td>無料</td></tr>
                        <tr><td>開催 7〜2 日前</td><td>参加費の 50%</td></tr>
                        <tr><td>開催前日・当日／無連絡不参加</td><td>参加費の全額</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="muted" style="font-size:.85rem; margin-top:0;">※ キャンセル料が発生する場合は、お支払い用のリンクをメールでお送りします。開催日前までにキャンセルのご連絡をお願いします。</p>
        <?php endif; ?>
        <p class="muted" style="font-size:.85rem;">カード情報の入力・処理は決済代行 Stripe 上で行われ、主催者は決済情報を受け取りません。</p>
        <div class="modal__actions"><button type="button" class="btn" data-modal-close>閉じる</button></div>
    </div>
</div>

    <script nonce="<?= e(csp_nonce()) ?>">
        // 支払い方法・参加人数に応じて合計金額と案内文を更新（計算の正は決済時にサーバー側で再確定）
        const PREPAY_UNIT = <?= $prepayUnit ?>;
        const ONSITE_UNIT = <?= $onsiteUnit ?>;
        const CURRENCY = <?= json_encode(strtolower((string) $currency)) ?>;
        const STRIPE_READY = <?= $stripeReady ? 'true' : 'false' ?>;
        const HAS_TIERS = <?= $hasTiers ? 'true' : 'false' ?>;
        // {区分名: {prepay:事前額, onsite:当日額}}
        const TIERS = <?= json_encode(array_reduce($tiers, function ($acc, $t) {
            $acc[$t['label']] = ['prepay' => (int) $t['amount'], 'onsite' => (int) $t['amount_onsite']];
            return $acc;
        }, []), JSON_UNESCAPED_UNICODE) ?>;
        function selectedTierUnit(method) {
            const el = document.querySelector('input[name="tier"]:checked');
            if (!el) return 0;
            const t = TIERS[el.value];
            if (!t) return 0;
            return method === 'onsite' ? t.onsite : t.prepay;
        }
        function formatAmount(total) {
            if (CURRENCY === 'jpy') {
                return '¥' + total.toLocaleString('ja-JP');
            }
            return (total / 100).toFixed(2) + ' ' + CURRENCY.toUpperCase();
        }
        function selectedMethod() {
            const el = document.querySelector('input[name="payment_type"]:checked');
            return el ? el.value : 'prepay';
        }
        function updateTotal() {
            const totalEl = document.getElementById('total');
            if (!totalEl) return; // 満員などでフォーム非表示のとき
            const method = selectedMethod();
            let unit, qty = 1;
            if (HAS_TIERS) {
                unit = selectedTierUnit(method); // 1名・選んだ性別×支払い方法の金額
            } else {
                unit = method === 'onsite' ? ONSITE_UNIT : PREPAY_UNIT; // 一律・1名
            }
            totalEl.textContent = formatAmount(unit * qty);

            const btn = document.getElementById('submitBtn');
            const note = document.getElementById('methodNote');
            const totalNote = document.getElementById('total-note');
            if (method === 'onsite') {
                btn.textContent = 'この内容で申し込む（当日支払い）→';
                totalNote.textContent = '（当日、会場でお支払い）';
                note.textContent = '申込を受け付けます。当日、会場で上記金額をお支払いください。今はお支払いは発生しません。';
            } else {
                btn.textContent = '事前決済する →';
                totalNote.textContent = '';
                note.textContent = '「事前決済する」を押すと、安全な Stripe の決済画面に移動します。カード情報は主催者には渡りません。';
            }

            // 事前決済は主催者の支払い口座連携が必須。未連携時は事前決済のみ無効化し、当日支払いは許可。
            const blocked = (method === 'prepay' && !STRIPE_READY);
            btn.disabled = blocked;
            const blockNote = document.getElementById('prepayBlockNote');
            if (blockNote) {
                blockNote.style.display = blocked ? '' : 'none';
            }
        }
        // インライン属性の代わりにここでイベントを束ねる（CSP厳格化対応）
        document.addEventListener('DOMContentLoaded', function () {
            const ps = document.getElementById('party_size');
            if (ps) { ps.addEventListener('change', updateTotal); }
            document.querySelectorAll('input[name="payment_type"]').forEach(function (r) {
                r.addEventListener('change', updateTotal);
            });
            document.querySelectorAll('input[name="tier"]').forEach(function (r) {
                r.addEventListener('change', updateTotal);
            });
            updateTotal();
        });
    </script>
</body>
</html>
