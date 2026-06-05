<?php

/**
 * 当日支払い申込の取消。管理画面からの POST のみを受ける。
 * 当日申込は課金なしの Stripe 顧客として記録しているため、その顧客を削除する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST のみ許可されています。');
}

csrf_verify($_POST['csrf_token'] ?? null);

$eventId = (string) ($_POST['event_id'] ?? '');
$customerId = (string) ($_POST['customer_id'] ?? '');

function back_to_admin(string $eventId, string $msg, string $type): never
{
    $q = http_build_query(['event_id' => $eventId, 'msg' => $msg, 'type' => $type]);
    header('Location: index.php?' . $q, true, 303);
    exit;
}

$event = $eventId !== '' ? find_event($eventId) : null;
if ($event === null || $event['tenant_id'] !== $tenant['id']) {
    back_to_admin($eventId, '対象イベントが見つかりません。', 'ng');
}
$account = null; // 運営者自身の Stripe アカウント（Connect 不使用）
if ($customerId === '' || env('STRIPE_SECRET_KEY') === null) {
    back_to_admin($eventId, '取消対象が不正です。', 'ng');
}

init_stripe();
$opts = stripe_opts($account);

try {
    \Stripe\Customer::retrieve($customerId, $opts)->delete([], $opts);
    back_to_admin($eventId, '当日支払いの申込を取り消しました。', 'ok');
} catch (\Throwable $ex) {
    error_log('当日申込の取消失敗: ' . $ex->getMessage());
    back_to_admin($eventId, '取消に失敗しました: ' . $ex->getMessage(), 'ng');
}
