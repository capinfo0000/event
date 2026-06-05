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

// 申込フォームの入力を受け取り・検証する（金額は必ずサーバー側のイベント定義から確定）
$name  = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$note  = trim((string)($_POST['note'] ?? ''));
$partySize = (int)($_POST['party_size'] ?? 1);

if ($name === '' || $email === '') {
    http_response_code(400);
    exit('お名前とメールアドレスは必須です。フォームに戻って入力してください。');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('メールアドレスの形式が正しくありません。');
}

// 参加人数の上限（capacity があればそれ、無ければ 10）に丸める
$maxParty = (int)($event['capacity'] ?? 0);
if ($maxParty < 1) {
    $maxParty = 10;
}
$maxParty = min($maxParty, 20);
if ($partySize < 1) {
    $partySize = 1;
}
if ($partySize > $maxParty) {
    $partySize = $maxParty;
}

// 長すぎる入力は Stripe metadata 制限（値は最大500文字）に合わせて切り詰める
$metaName = mb_substr($name, 0, 100);
$metaPhone = mb_substr($phone, 0, 30);
$metaNote = mb_substr($note, 0, 450);

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
            'quantity' => $partySize, // 参加人数ぶんをまとめて決済
        ]],
        // 申込フォームで集めた連絡先を Stripe に引き継ぐ（領収書送付先＝入力メール）
        'customer_creation' => 'always',
        'customer_email' => $email,
        // 集めた情報は Stripe の決済データに metadata として保管（当サーバーのDBは持たない）
        'metadata' => [
            'event_id' => $event['id'],
            'event_name' => $event['name'] ?? '',
            'participant_name' => $metaName,
            'phone' => $metaPhone,
            'party_size' => (string)$partySize,
            'note' => $metaNote,
        ],
        'payment_intent_data' => [
            'metadata' => [
                'event_id' => $event['id'],
                'event_name' => $event['name'] ?? '',
                'participant_name' => $metaName,
                'phone' => $metaPhone,
                'party_size' => (string)$partySize,
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
