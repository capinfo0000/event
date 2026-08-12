<?php

/**
 * 当日払い参加者の一括キャンセル。管理画面の請求管理モーダルからの POST のみ。
 * 名簿からは削除せず、選択された customer_ids[] を「キャンセル済み」（metadata.cancelled=1）にする。
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

$changed = 0;
$skipped = 0;
foreach ($ids as $cid) {
    // IDOR対策: 指定 customer が「このイベントの当日払い参加者」であることを検証してから更新。
    $target = find_event_participant_by_customer($eventId, $account, $cid);
    if ($target === null || ($target['payment_type'] ?? '') !== 'onsite') {
        $skipped++;
        continue;
    }
    try {
        \Stripe\Customer::update(
            $cid,
            ['metadata' => ['cancelled' => '1', 'cancelled_at' => (string) time()]],
            $opts
        );
        $changed++;
    } catch (\Throwable $ex) {
        error_log('一括キャンセルの更新失敗: ' . $ex->getMessage());
        $skipped++;
    }
}

audit_log('onsite_cancel_bulk', ['tenant' => $tenant['id'], 'event' => $eventId, 'changed' => (string) $changed, 'skipped' => (string) $skipped]);

if ($changed === 0) {
    back_to_admin($eventId, 'キャンセルにできる対象がありませんでした。', 'ng');
}
back_to_admin($eventId, '当日払い ' . $changed . ' 件をキャンセル済みにしました（名簿には残ります）' . ($skipped > 0 ? '（対象外 ' . $skipped . ' 件）' : '') . '。', 'ok');
