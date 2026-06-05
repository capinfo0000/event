<?php

/**
 * Stripe Webhook 受信エンドポイント（任意・推奨）。
 *
 * 支払い完了などのイベントを Stripe から受け取り、署名検証のうえ
 * ローカルのログファイル（logs/payments.log）に記録します。
 * ※ カード情報は含まれません。記録するのは「イベント名・金額・メール・Stripeの決済ID」のみ。
 * ※ DB は使いません。正式な参加者名簿は Stripe ダッシュボードが正です。
 *
 * ローカルでの試し方（Stripe CLI）:
 *   stripe listen --forward-to localhost:8000/webhook.php
 * 表示された whsec_... を .env の STRIPE_WEBHOOK_SECRET に設定してください。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$secret = env('STRIPE_WEBHOOK_SECRET');
if ($secret === null) {
    http_response_code(500);
    error_log('STRIPE_WEBHOOK_SECRET が未設定です。');
    exit;
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400); // 不正なペイロード
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); // 署名検証失敗
    exit;
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;

    $name = '';
    foreach (($session->custom_fields ?? []) as $field) {
        if (($field->key ?? '') === 'participant_name') {
            $name = $field->text->value ?? '';
        }
    }

    $record = [
        'time' => date('c'),
        'event_id' => $session->metadata->event_id ?? '',
        'event_name' => $session->metadata->event_name ?? '',
        'participant_name' => $name,
        'email' => $session->customer_details->email ?? '',
        'amount' => $session->amount_total,
        'currency' => $session->currency,
        'payment_status' => $session->payment_status,
        'checkout_session_id' => $session->id,
    ];

    file_put_contents(
        APP_ROOT . '/logs/payments.log',
        json_encode($record, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

http_response_code(200);
echo 'ok';
