<?php

/**
 * 当日払い No-show（未出席・未集金）へのキャンセル料を一括請求する。
 * 対象: 当日払いで attended/collected でなく、まだキャンセル料リンク未発行・未入金、メール有り。
 * 金額は開催日からの逆算（キャンセルポリシー区分）で各自自動算定。0%（8日以上前）のときは送らない。
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
    audit_log('authz.deny', ['action' => 'onsite_fee_bulk', 'tenant' => $tenant['id'], 'event' => $eventId]);
    back_to_admin($eventId, '対象イベントが見つかりません。', 'ng');
}
$account = stripe_resolve_tenant($tenant);
if (!stripe_ready_for_tenant($tenant)) {
    back_to_admin($eventId, 'Stripe キー未設定のため請求できません。', 'ng');
}

$rate = cancellation_fee_rate_for_event((string) ($event['date'] ?? ''));
if (($rate['rate'] ?? 0) <= 0) {
    back_to_admin($eventId, 'キャンセルポリシー上、現時点ではキャンセル料は発生しません（' . $rate['label'] . '）。', 'ng');
}

// モーダルで選択された対象（customer_ids[]）。指定があればその人だけに送る。
$selected = $_POST['customer_ids'] ?? null;
$selectedSet = is_array($selected) ? array_flip(array_map('strval', $selected)) : null;
if ($selectedSet !== null && $selectedSet === []) {
    back_to_admin($eventId, '対象が選択されていません。', 'ng');
}

$currency = strtolower((string) ($event['currency'] ?? 'jpy'));
$sent = 0;
$skipped = 0;

try {
    $participants = fetch_event_participants($eventId, $account);
} catch (\Throwable $e) {
    error_log('一括請求の名簿取得失敗: ' . $e->getMessage());
    back_to_admin($eventId, '名簿の取得に失敗しました。時間をおいて再度お試しください。', 'ng');
}

foreach ($participants as $p) {
    if (($p['payment_type'] ?? '') !== 'onsite') {
        continue;
    }
    $cid = (string) ($p['customer_id'] ?? '');
    if ($selectedSet !== null) {
        // 選択モード: 選ばれた人のみ。入金済みは除外（メール・料金は下でチェック）。
        if (!isset($selectedSet[$cid]) || !empty($p['fee_paid'])) {
            continue;
        }
    } else {
        // 自動モード: 未出席・未集金・未請求・未入金のみ対象
        if (!empty($p['attended']) || !empty($p['collected']) || !empty($p['fee_paid']) || !empty($p['fee_link_sent'])) {
            continue;
        }
    }
    $email = trim((string) ($p['email'] ?? ''));
    if ($email === '') {
        $skipped++;
        continue;
    }
    $fee = (int) round(((int) ($p['amount'] ?? 0)) * (float) $rate['rate']);
    if ($fee <= 0) {
        $skipped++;
        continue;
    }
    $session = create_cancel_fee_checkout($account, $event, $email, (string) ($p['name'] ?? ''), (string) ($p['customer_id'] ?? ''), $fee);
    if ($session === null || ((string) ($session->url ?? '')) === '') {
        $skipped++;
        continue;
    }
    $body = (($p['name'] ?? '') !== '' ? $p['name'] . ' 様' : 'ご参加予定者様') . "\n\n"
        . '「' . ($event['name'] ?? 'イベント') . '」につきまして、当日ご参加の確認ができませんでした。' . "\n"
        . 'キャンセルポリシーに基づき、キャンセル料のお支払いをお願いいたします。' . "\n\n"
        . '金額：' . format_amount($fee, $currency) . "\n"
        . 'お支払いはこちら（Stripe の安全な決済ページ）：' . "\n" . $session->url . "\n\n"
        . '※ このリンクの有効期限は発行からおおよそ24時間です。期限切れの場合は主催者へご連絡ください。' . "\n";
    if (send_mail($email, '【キャンセル料のお支払い】' . ($event['name'] ?? 'イベント'), $body)) {
        $sent++;
    } else {
        $skipped++;
    }
}

audit_log('onsite_fee_bulk', ['tenant' => $tenant['id'], 'event' => $eventId, 'sent' => (string) $sent, 'skipped' => (string) $skipped]);

if ($sent === 0) {
    back_to_admin($eventId, '送信対象がありませんでした（対象外 ' . $skipped . ' 件）。', 'ng');
}
back_to_admin($eventId, 'No-show ' . $sent . ' 人へキャンセル料の請求メールを送信しました' . ($skipped > 0 ? '（対象外 ' . $skipped . ' 件）' : '') . '。', 'ok');
