<?php

/**
 * Stripe Connect 連携の開始。
 * 主催者を Stripe の OAuth 認可画面へ送る。秘密鍵は当方では一切預からない。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

$clientId = env('STRIPE_CONNECT_CLIENT_ID');
if ($clientId === null) {
    http_response_code(500);
    exit('設定エラー: STRIPE_CONNECT_CLIENT_ID が未設定です（.env を確認してください）。');
}

// CSRF 兼・誰の連携かを保持する state をセッションに保存
session_boot();
$state = bin2hex(random_bytes(16));
$_SESSION['connect_state'] = $state;

$params = http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'scope' => 'read_write',
    'redirect_uri' => base_url() . '/admin/connect_callback.php',
    'state' => $state,
    // 主催者の入力を一部省略できるよう、わかっている情報を渡す
    'stripe_user[email]' => $tenant['email'],
]);

header('Location: https://connect.stripe.com/oauth/authorize?' . $params, true, 302);
exit;
