<?php

/**
 * 当日支払いの「集金確認済み／未収に戻す」を切り替える。
 * 当日申込は課金なしの Stripe 顧客として記録しているため、その顧客の
 * metadata.collected を更新する（DB は持たない方針を維持）。
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
$collect = ($_POST['collect'] ?? '1') === '1'; // 1=集金済みにする / 0=未収に戻す

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
$secretKey = tenant_stripe_key($tenant);
if ($customerId === '' || $secretKey === null) {
    back_to_admin($eventId, '対象が不正です。', 'ng');
}

init_stripe($secretKey);
$opts = stripe_opts($account);

try {
    \Stripe\Customer::update($customerId, [
        'metadata' => [
            'collected' => $collect ? '1' : '',
            'collected_at' => $collect ? (string) time() : '',
        ],
    ], $opts);
    back_to_admin($eventId, $collect ? '集金確認済みにしました。' : '未収に戻しました。', 'ok');
} catch (\Throwable $ex) {
    error_log('集金状態の更新失敗: ' . $ex->getMessage());
    back_to_admin($eventId, '更新に失敗しました: ' . $ex->getMessage(), 'ng');
}
