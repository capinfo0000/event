<?php

/**
 * 当日支払い申込のキャンセル状態を切り替える。管理画面からの POST のみを受ける。
 * 名簿からは削除せず、Stripe 顧客の metadata.cancelled を 1/0 で切り替える
 * （cancel=1: キャンセル確定 / cancel=0: キャンセルを戻す）。履歴を残すための方針。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_owner_tenant();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST のみ許可されています。');
}

csrf_verify($_POST['csrf_token'] ?? null);

$eventId = (string) ($_POST['event_id'] ?? '');
$customerId = (string) ($_POST['customer_id'] ?? '');
$makeCancel = (string) ($_POST['cancel'] ?? '1') !== '0'; // 既定はキャンセル確定。cancel=0 で復帰。

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
$account = stripe_resolve_tenant($tenant); // 画面登録鍵→Connect→プラットフォームの順で文脈確立
if ($customerId === '' || !stripe_ready_for_tenant($tenant)) {
    back_to_admin($eventId, '取消対象が不正です。', 'ng');
}

// IDOR対策: 指定 customer_id が「このイベントの当日支払い参加者」であることを Stripe 側で検証する。
$target = find_event_participant_by_customer($eventId, $account, $customerId);
if ($target === null || ($target['payment_type'] ?? '') !== 'onsite') {
    audit_log('authz.deny', ['action' => 'onsite_cancel', 'tenant' => $tenant['id'], 'event' => $eventId]);
    back_to_admin($eventId, '取消対象の参加者が見つかりません。', 'ng');
}

init_stripe();
$opts = stripe_opts($account);

try {
    \Stripe\Customer::update(
        $customerId,
        ['metadata' => ['cancelled' => $makeCancel ? '1' : '', 'cancelled_at' => $makeCancel ? (string) time() : '']],
        $opts
    );
    audit_log('onsite_cancel', ['tenant' => $tenant['id'], 'event' => $eventId, 'cancel' => $makeCancel ? '1' : '0']);
    back_to_admin($eventId, $makeCancel ? '当日支払いの申込をキャンセル済みにしました（名簿には残ります）。' : 'キャンセルを取り消しました。', 'ok');
} catch (\Throwable $ex) {
    error_log('当日申込のキャンセル状態更新失敗: ' . $ex->getMessage());
    back_to_admin($eventId, '更新に失敗しました: ' . $ex->getMessage(), 'ng');
}
