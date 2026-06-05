<?php

/**
 * Stripe カスタマーポータルへ遷移（プランの変更・解約・支払い方法の更新）。
 * プラットフォーム本体の顧客に対して開く。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

if (empty($tenant['stripe_customer_id'])) {
    header('Location: upgrade.php');
    exit;
}

init_stripe();
try {
    $portal = \Stripe\BillingPortal\Session::create([
        'customer' => $tenant['stripe_customer_id'],
        'return_url' => base_url() . '/admin/dashboard.php',
    ]);
} catch (\Throwable $e) {
    error_log('カスタマーポータル作成失敗: ' . $e->getMessage());
    http_response_code(502);
    exit('ポータルを開けませんでした。時間をおいて再度お試しください。');
}

header('Location: ' . $portal->url, true, 303);
exit;
