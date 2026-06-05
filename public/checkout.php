<?php

/**
 * Stripe Checkout セッションを作成し、Stripe の決済ページへリダイレクトする。
 *
 * 【カード情報は扱わない】
 * ここではイベント情報と金額を Stripe に渡してセッションを作るだけ。
 * 実際のカード入力は次の Stripe ホスト画面で行われ、当サーバーには一切渡りません。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST のみ許可されています。');
}

$eventId = (string)($_POST['event_id'] ?? '');
$event = find_event($eventId);

if ($event === null) {
    http_response_code(404);
    exit('指定されたイベントが見つかりません。');
}

init_stripe();

try {
    $session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'line_items' => [[
            'price_data' => [
                'currency' => $event['currency'] ?? 'jpy',
                'unit_amount' => (int)($event['amount'] ?? 0),
                'product_data' => [
                    'name' => $event['name'] ?? 'イベント参加費',
                    'description' => trim(($event['date'] ?? '') . ' / ' . ($event['place'] ?? '')),
                ],
            ],
            'quantity' => 1,
        ]],
        // 参加者の氏名・メールは Stripe 側で収集・保管（当サーバーのDBは持たない）
        'customer_creation' => 'always',
        'phone_number_collection' => ['enabled' => true],
        'custom_fields' => [[
            'key' => 'participant_name',
            'label' => ['type' => 'custom', 'custom' => '参加者のお名前'],
            'type' => 'text',
        ]],
        // どのイベントの申込かを Stripe の決済データに刻む（ダッシュボードで識別用）
        'metadata' => [
            'event_id' => $event['id'],
            'event_name' => $event['name'] ?? '',
        ],
        'payment_intent_data' => [
            'metadata' => [
                'event_id' => $event['id'],
                'event_name' => $event['name'] ?? '',
            ],
        ],
        // キャンセルポリシーを決済画面のボタン直上に明示（前払い＝後から取り立て不要にする要）
        'custom_text' => [
            'submit' => [
                'message' => 'お支払い後のキャンセルは、キャンセルポリシーに沿った返金規定が適用されます。',
            ],
        ],
        'success_url' => base_url() . '/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => base_url() . '/cancel.php?event_id=' . urlencode($event['id']),
    ]);
} catch (\Throwable $e) {
    // 認証エラー・通信エラー・予期しない応答など、あらゆる決済作成失敗をここで受ける
    http_response_code(502);
    error_log('Stripe Checkout 作成失敗: ' . $e->getMessage());
    exit('決済ページの作成に失敗しました。時間をおいて再度お試しください。');
}

// Stripe のホスト決済ページへ
header('Location: ' . $session->url, true, 303);
exit;
