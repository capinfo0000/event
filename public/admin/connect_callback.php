<?php

/**
 * Stripe Connect OAuth の戻り先。
 * 認可コードをアクセストークン交換し、連携アカウントID（acct_...）を保存する。
 * 主催者の秘密鍵は保存しない（Connect では当方はアカウントIDのみ保持）。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
session_boot();

// ユーザーが認可をキャンセルした等
if (isset($_GET['error'])) {
    $msg = (string) ($_GET['error_description'] ?? $_GET['error']);
    exit('Stripe 連携がキャンセルされました: ' . e($msg) . ' <a href="dashboard.php">戻る</a>');
}

$code = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');
$expected = $_SESSION['connect_state'] ?? '';
unset($_SESSION['connect_state']);

if ($code === '' || $state === '' || $expected === '' || !hash_equals($expected, $state)) {
    http_response_code(400);
    exit('不正な連携リクエストです（state 不一致）。<a href="dashboard.php">戻る</a>');
}

init_stripe(); // プラットフォームの秘密鍵で OAuth トークン交換を行う

try {
    $response = \Stripe\OAuth::token([
        'grant_type' => 'authorization_code',
        'code' => $code,
    ]);
    $accountId = $response->stripe_user_id ?? null;
    if (!$accountId) {
        throw new \RuntimeException('連携アカウントIDを取得できませんでした。');
    }
    set_tenant_stripe_account($tenant['id'], $accountId);
} catch (\Throwable $e) {
    error_log('Connect OAuth 失敗: ' . $e->getMessage());
    http_response_code(502);
    exit('Stripe 連携に失敗しました。時間をおいて再度お試しください。<a href="dashboard.php">戻る</a>');
}

header('Location: dashboard.php');
exit;
