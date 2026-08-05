<?php

/**
 * 当日払いのキャンセル料請求（No-show 対応）。管理ダッシュボードからの POST のみ。
 *
 * 当日払いはカード情報を預かっていないため自動請求はできない。
 * そこで「キャンセル料の支払いリンク（Stripe Checkout）」を作成し、参加者にメールで送る。
 * 参加者がリンクから支払うと、主催者の Stripe に入金される（当社・主催者はカードを保持しない）。
 * このリンクの支払いは名簿には載せない（metadata.payment_type=cancel_fee で除外）。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST のみ許可されています。');
}
csrf_verify($_POST['csrf_token'] ?? null);

$eventId    = (string) ($_POST['event_id'] ?? '');
$customerId = (string) ($_POST['customer_id'] ?? '');
$amountRaw  = trim((string) ($_POST['amount'] ?? ''));

/** 管理ダッシュボードへ結果を持って戻る。 */
function back_to_admin(string $eventId, string $msg, string $type): never
{
    $q = http_build_query(['event_id' => $eventId, 'msg' => $msg, 'type' => $type]);
    header('Location: index.php?' . $q, true, 303);
    exit;
}

// 対象イベントが自分のものか確認
$event = $eventId !== '' ? find_event($eventId) : null;
if ($event === null || $event['tenant_id'] !== $tenant['id']) {
    audit_log('authz.deny', ['action' => 'onsite_fee', 'tenant' => $tenant['id'], 'event' => $eventId]);
    back_to_admin($eventId, '対象イベントが見つかりません。', 'ng');
}
$account = stripe_resolve_tenant($tenant);
if (!stripe_ready_for_tenant($tenant)) {
    back_to_admin($eventId, 'Stripe キー未設定のため請求リンクを作成できません。', 'ng');
}

// IDOR対策: 指定 customer が「このイベントの当日払い参加者」であることを Stripe 側で検証
$participant = find_event_participant_by_customer($eventId, $account, $customerId);
if ($participant === null || ($participant['payment_type'] ?? '') !== 'onsite') {
    audit_log('authz.deny', ['action' => 'onsite_fee.cust', 'tenant' => $tenant['id'], 'event' => $eventId]);
    back_to_admin($eventId, 'キャンセル料の対象（当日払いの参加者）が見つかりません。', 'ng');
}

$email    = trim((string) ($participant['email'] ?? ''));
$name     = (string) ($participant['name'] ?? '');
$currency = strtolower((string) ($participant['currency'] ?? 'jpy'));
$onsiteAmount = (int) ($participant['amount'] ?? 0); // 当日払いの予定額（最小通貨単位）

if ($email === '') {
    back_to_admin($eventId, 'この参加者のメールアドレスが無いため、支払いリンクを送れません。', 'ng');
}

// キャンセル料は「開催日からの逆算（キャンセルポリシーの区分）」で自動算定する（手入力しない）。
$rateInfo = cancellation_fee_rate_for_event((string) ($event['date'] ?? ''));
$fee = (int) round($onsiteAmount * (float) $rateInfo['rate']);
if ($fee <= 0) {
    back_to_admin($eventId, 'キャンセルポリシー上、現時点ではキャンセル料は発生しません（' . $rateInfo['label'] . '）。', 'ng');
}

init_stripe();
$opts = stripe_opts($account);

try {
    $session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'line_items' => [[
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $fee,
                'product_data' => [
                    'name' => '【キャンセル料】' . ($event['name'] ?? 'イベント'),
                    'description' => trim(($event['date'] ?? '') . ' / ' . ($event['place'] ?? '')),
                ],
            ],
            'quantity' => 1,
        ]],
        'customer_email' => $email,
        // 名簿には載せない支払い（payment_type=cancel_fee）。event_id も入れず一覧から除外。
        'metadata' => [
            'payment_type'     => 'cancel_fee',
            'fee_event_id'     => $event['id'],
            'fee_event_name'   => $event['name'] ?? '',
            'participant_name' => $name,
            'orig_customer'    => $customerId,
        ],
        'payment_intent_data' => [
            'metadata' => [
                'payment_type'   => 'cancel_fee',
                'fee_event_id'   => $event['id'],
                'orig_customer'  => $customerId,
            ],
        ],
        'custom_text' => [
            'submit' => [
                'message' => 'キャンセルポリシーに基づくキャンセル料のお支払いです。',
            ],
        ],
        'success_url' => base_url() . '/cancelfee_done.php',
        'cancel_url'  => base_url() . '/cancelfee_done.php?canceled=1',
    ], $opts);
} catch (\Throwable $e) {
    http_response_code(502);
    error_log('キャンセル料リンク作成失敗: ' . $e->getMessage());
    back_to_admin($eventId, '支払いリンクの作成に失敗しました。時間をおいて再度お試しください。', 'ng');
}

$feeText = format_amount($fee, $currency);
$link = (string) ($session->url ?? '');

// 参加者へキャンセル料の支払いリンクをメール送信
$mailBody = ($name !== '' ? $name . ' 様' : 'ご参加予定者様') . "\n\n"
    . '「' . ($event['name'] ?? 'イベント') . '」につきまして、当日ご参加の確認ができませんでした。' . "\n"
    . 'キャンセルポリシーに基づき、キャンセル料のお支払いをお願いいたします。' . "\n\n"
    . '金額：' . $feeText . "\n"
    . 'お支払いはこちらから（Stripe の安全な決済ページ）：' . "\n"
    . $link . "\n\n"
    . '※ このリンクの有効期限は発行からおおよそ24時間です。期限切れの場合は主催者へご連絡ください。' . "\n"
    . '※ お心当たりのない場合は、お手数ですが主催者までお問い合わせください。' . "\n";
$mailOk = send_mail($email, '【キャンセル料のお支払い】' . ($event['name'] ?? 'イベント'), $mailBody);

audit_log('onsite_fee', [
    'tenant' => $tenant['id'], 'event' => $eventId,
    'cust' => substr($customerId, 0, 10) . '…',
    'amount' => (string) $fee, 'mail' => $mailOk ? 'ok' : 'ng',
]);

$msg = 'キャンセル料（' . $feeText . '／' . $rateInfo['label'] . '）の支払いリンクを作成しました。'
    . ($mailOk ? ' ' . mask_email_for_log($email) . ' 宛にメールを送信しました。' : ' メール送信に失敗した可能性があります。下記リンクを主催者から直接お送りください：')
    . ($mailOk ? '' : ' ' . $link);
back_to_admin($eventId, $msg, 'ok');
