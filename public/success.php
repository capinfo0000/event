<?php

/**
 * 決済成功ページ。Stripe から戻ってきた session_id を使って
 * 支払い結果を確認し、申込完了を表示する。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$sessionId = (string)($_GET['session_id'] ?? '');
$paid = false;
$eventName = '';
$amountText = '';
$email = '';

if ($sessionId !== '') {
    init_stripe();
    try {
        $session = \Stripe\Checkout\Session::retrieve($sessionId);
        $paid = ($session->payment_status === 'paid');
        $eventName = $session->metadata['event_name'] ?? '';
        $email = $session->customer_details->email ?? '';
        if ($session->amount_total !== null) {
            $amountText = format_amount((int)$session->amount_total, (string)$session->currency);
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Session 取得失敗: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>申込完了</title>
    <style>
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.7; color: #1f2937; max-width: 640px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-top: 24px; }
        .ok { color: #16a34a; font-size: 1.4rem; font-weight: 700; }
        .ng { color: #dc2626; font-size: 1.3rem; font-weight: 700; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 8px 16px; }
        dt { color: #6b7280; }
        a { color: #2563eb; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($paid): ?>
            <p class="ok">✅ お申し込みが完了しました</p>
            <p>ご参加ありがとうございます。お支払いを確認しました。確認メールが Stripe より送信されます。</p>
            <p style="font-size:.85rem;color:#6b7280;">※ カード情報は Stripe が直接お預かりしており、主催者（当方）は決済情報を受け取っておりません。</p>
            <dl>
                <?php if ($eventName !== ''): ?><dt>イベント</dt><dd><?= e($eventName) ?></dd><?php endif; ?>
                <?php if ($amountText !== ''): ?><dt>お支払い額</dt><dd><?= e($amountText) ?></dd><?php endif; ?>
                <?php if ($email !== ''): ?><dt>メール</dt><dd><?= e($email) ?></dd><?php endif; ?>
            </dl>
        <?php else: ?>
            <p class="ng">お支払いを確認できませんでした</p>
            <p>恐れ入りますが、もう一度お試しいただくか、主催者までご連絡ください。</p>
        <?php endif; ?>
        <p style="margin-top:24px;"><a href="index.php">← イベント一覧へ戻る</a></p>
    </div>
</body>
</html>
