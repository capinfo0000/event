<?php

/**
 * 決済の中断／失敗ページ。
 * Stripe の決済画面で「戻る」を押した中断のほか、カード拒否で戻ってきた場合に、
 * 分かる範囲で原因（誰が拒否したか・拒否理由）を表示し、再試行へ導く。
 * （いずれの場合も料金は発生していません）
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$eventId   = (string) ($_GET['event_id'] ?? '');
$sessionId = (string) ($_GET['session_id'] ?? '');
$event = $eventId !== '' ? find_event($eventId) : null;
$account = $event !== null ? stripe_resolve_event($event) : null;

$declined   = false;   // カード拒否で戻ってきたか
$declineMsg = null;    // 具体的な拒否理由（分かれば）
$whoText    = '';      // 誰による中断か

// session_id があれば、その決済の結果を確認して原因を特定する（細工された値でも 500 にしない）。
if ($sessionId !== '' && $event !== null && stripe_ready_for_event($event)) {
    init_stripe();
    try {
        $session = \Stripe\Checkout\Session::retrieve(
            ['id' => $sessionId, 'expand' => ['payment_intent']],
            stripe_opts($account)
        );
        $pi = $session->payment_intent ?? null;
        if (is_object($pi)) {
            $lpe = $pi->last_payment_error ?? null;
            if (is_object($lpe)) {
                // 支払い試行が拒否された
                $declined = true;
                $declineMsg = decline_reason_ja($lpe->decline_code ?? null, $lpe->code ?? null);
                $whoText = 'カード発行会社（銀行）により、この支払いは承認されませんでした。';
            } else {
                $reason = (string) ($pi->cancellation_reason ?? '');
                $whoText = ($reason === 'abandoned')
                    ? 'お手続きの途中で画面を離れたため、完了していません。'
                    : 'お支払いは完了していません。';
            }
        }
    } catch (\Throwable $e) {
        error_log('cancel.php 状態取得失敗: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $declined ? 'カードが承認されませんでした' : 'お支払いは完了していません' ?></title>
    <link rel="stylesheet" href="/assets/app.css?v=5">
    <style nonce="<?= e(csp_nonce()) ?>">
        .ng { color: var(--dng); font-size: 1.25rem; font-weight: 800; margin: 0 0 8px; }
        .cause { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:10px; padding:12px 16px; margin:12px 0; }
        ul.reasons { line-height:1.9; margin:8px 0 0; padding-left:1.2em; }
    </style>
</head>
<body>
<div class="container">
    <div class="brandbar">イベント参加申込</div>
    <div class="card">
        <?php if ($declined): ?>
            <p class="ng">カードが承認されませんでした</p>
            <p><?= e($whoText) ?>料金は請求されていません。</p>
            <div class="cause">
                <strong>考えられる原因：</strong>
                <?php if ($declineMsg !== null): ?>
                    <?= e($declineMsg) ?>
                <?php else: ?>
                    カード発行会社が理由を明示せずに拒否したため、当方では詳細を確認できません。下記のいずれかが考えられます。
                <?php endif; ?>
                <ul class="reasons">
                    <li>残高不足、または利用限度額を超えている</li>
                    <li>ネットショッピングの利用制限がかかっている</li>
                    <li>不正利用防止のための一時的なブロック</li>
                    <li>カード情報（番号・有効期限・セキュリティコード）の入力誤り</li>
                    <li>本人認証（3Dセキュア）が完了していない</li>
                </ul>
            </div>
            <p class="muted">対処：もう一度お試しいただくか、<strong>別のカード</strong>をご利用ください。解決しない場合は<strong>カード発行会社</strong>へお問い合わせください（当方ではカード側の理由を変更できません）。</p>
        <?php else: ?>
            <p class="ng">お支払いは完了していません</p>
            <p><?= $whoText !== '' ? e($whoText) : '料金は請求されていません。' ?>もう一度お申し込みいただけます。</p>
        <?php endif; ?>

        <?php if ($event !== null): ?>
            <form action="apply.php" method="get" style="margin-top:14px;">
                <input type="hidden" name="event_id" value="<?= e($event['id']) ?>">
                <button type="submit" class="btn"><?= $declined ? 'もう一度お支払いを試す' : '「' . e($event['name'] ?? '') . '」をもう一度申し込む' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
