<?php

/**
 * ログイン時の2段階認証（TOTP）コード入力。
 * パスワード検証済みで 2fa_pending がセッションにある場合のみ表示する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

session_boot();

$pendingId = (string) ($_SESSION['2fa_pending'] ?? '');
$pendingAt = (int) ($_SESSION['2fa_time'] ?? 0);
$pendingUa = (string) ($_SESSION['2fa_ua'] ?? '');

// 保留状態が無い/古い/UA不一致なら最初からやり直し。
if ($pendingId === '' || (time() - $pendingAt) > 300 || !hash_equals($pendingUa, session_ua_hash())) {
    unset($_SESSION['2fa_pending'], $_SESSION['2fa_time'], $_SESSION['2fa_ua']);
    header('Location: login.php');
    exit;
}

$tenant = find_tenant_by_id($pendingId);
if ($tenant === null || !tenant_totp_enabled($tenant)) {
    unset($_SESSION['2fa_pending'], $_SESSION['2fa_time'], $_SESSION['2fa_ua']);
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $code = (string) ($_POST['code'] ?? '');
    if (!rate_limit_check('twofa', 10, 300)) {
        $error = '試行が多すぎます。しばらくしてからお試しください。';
    } else {
        $secret = tenant_totp_secret($tenant);
        if ($secret !== null && totp_verify($secret, $code)) {
            complete_tenant_login($tenant); // ここでログイン確定（2fa_* も破棄）
            audit_log('login.ok.2fa', ['tenant' => $tenant['id']]);
            header('Location: dashboard.php');
            exit;
        }
        audit_log('login.2fa.fail', ['tenant' => $tenant['id']]);
        $error = 'コードが正しくありません。認証アプリの6桁コードを入力してください。';
    }
}

$token = csrf_token();
require __DIR__ . '/_auth_header.php';
?>
<h1>2段階認証</h1>
<p class="muted">認証アプリ（Google Authenticator など）に表示されている<strong>6桁のコード</strong>を入力してください。</p>
<?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card">
    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
    <label>認証コード</label>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required autofocus placeholder="123456">
    <p style="margin-top:16px;"><button type="submit" class="btn">認証してログイン</button></p>
</form>
<p class="muted"><a href="login.php">ログインに戻る</a></p>
<?php require __DIR__ . '/_auth_footer.php'; ?>
