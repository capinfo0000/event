<?php

/**
 * 当日払い参加者の一括取消（名簿から削除）。管理画面の一括モーダルからの POST のみ。
 * 選択された customer_ids[] の当日払い顧客を削除する。
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

function back_to_admin(string $eventId, string $msg, string $type): never
{
    $q = http_build_query(['event_id' => $eventId, 'msg' => $msg, 'type' => $type]);
    header('Location: index.php?' . $q, true, 303);
    exit;
}

$event = $eventId !== '' ? find_event($eventId) : null;
if ($event === null || $event['tenant_id'] !== $tenant['id']) {
    audit_log('authz.deny', ['action' => 'onsite_cancel_bulk', 'tenant' => $tenant['id'], 'event' => $eventId]);
    back_to_admin($eventId, '対象イベントが見つかりません。', 'ng');
}
$account = stripe_resolve_tenant($tenant);
if (!stripe_ready_for_tenant($tenant)) {
    back_to_admin($eventId, 'Stripe キー未設定のため操作できません。', 'ng');
}

$selected = $_POST['customer_ids'] ?? null;
$ids = is_array($selected) ? array_values(array_unique(array_map('strval', $selected))) : [];
if ($ids === []) {
    back_to_admin($eventId, '対象が選択されていません。', 'ng');
}

init_stripe();
$opts = stripe_opts($account);

$removed = 0;
$skipped = 0;
foreach ($ids as $cid) {
    // IDOR対策: 指定 customer が「このイベントの当日払い参加者」であることを検証してから削除。
    $target = find_event_participant_by_customer($eventId, $account, $cid);
    if ($target === null || ($target['payment_type'] ?? '') !== 'onsite') {
        $skipped++;
        continue;
    }
    try {
        \Stripe\Customer::retrieve($cid, $opts)->delete([], $opts);
        $removed++;
    } catch (\Throwable $ex) {
        error_log('一括取消の削除失敗: ' . $ex->getMessage());
        $skipped++;
    }
}

audit_log('onsite_cancel_bulk', ['tenant' => $tenant['id'], 'event' => $eventId, 'removed' => (string) $removed, 'skipped' => (string) $skipped]);

if ($removed === 0) {
    back_to_admin($eventId, '削除できる対象がありませんでした。', 'ng');
}
back_to_admin($eventId, '当日払い ' . $removed . ' 件を名簿から削除しました' . ($skipped > 0 ? '（対象外 ' . $skipped . ' 件）' : '') . '。', 'ok');
