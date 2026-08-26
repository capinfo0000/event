<?php

/**
 * キャンセル料お支払いの完了／中断ページ（参加者向け・簡易表示）。
 * onsite_fee.php が作成した Checkout の success_url / cancel_url。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$canceled = ((string) ($_GET['canceled'] ?? '')) === '1';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャンセル料のお支払い</title>
    <link rel="stylesheet" href="/assets/app.css?v=5">
    <style nonce="<?= e(csp_nonce()) ?>">
        .ok { color: #16a34a; font-size: 1.3rem; font-weight: 800; margin: 0 0 8px; }
        .ng { color: var(--dng); font-size: 1.2rem; font-weight: 800; margin: 0 0 8px; }
    </style>
</head>
<body>
<div class="container">
    <div class="brandbar">決済くん</div>
    <div class="card">
        <?php if ($canceled): ?>
            <p class="ng">お支払いは完了していません</p>
            <p>お支払いを中断しました。再度お手続きいただく場合は、メールのリンクから開き直してください。ご不明な点は主催者までご連絡ください。</p>
        <?php else: ?>
            <p class="ok">✅ お支払いが完了しました</p>
            <p>キャンセル料のお支払いを確認しました。ご対応ありがとうございました。確認メールが Stripe より送信されます。</p>
            <p class="muted">※ カード情報は Stripe が直接お預かりしており、主催者・当サービスは決済情報を受け取っておりません。</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
