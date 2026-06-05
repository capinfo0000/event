<?php

/**
 * 主催者ログイン（メール＋パスワード）。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    // 総当たり対策：直近15分で失敗が多すぎる場合は一時的にブロック
    if (recent_failed_logins($email) >= 5) {
        $error = '試行回数が多すぎます。しばらく時間をおいてからお試しください。';
    } elseif (login_tenant($email, $password)) {
        clear_failed_logins($email);
        header('Location: dashboard.php');
        exit;
    } else {
        record_failed_login($email);
        $error = 'メールアドレスまたはパスワードが違います。';
    }
}

// すでにログイン済みならダッシュボードへ
if (current_tenant() !== null) {
    header('Location: dashboard.php');
    exit;
}

$token = csrf_token();
require __DIR__ . '/_auth_header.php';
?>
<h1>主催者ログイン</h1>
<?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card">
    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
    <label>メールアドレス</label>
    <input type="email" name="email" required autocomplete="email">
    <label>パスワード</label>
    <input type="password" name="password" required autocomplete="current-password">
    <p style="margin-top:16px;"><button type="submit" class="btn">ログイン</button></p>
</form>
<p class="muted"><a href="forgot.php">パスワードを忘れた場合</a></p>
<p class="muted">アカウントをお持ちでない方は <a href="signup.php">新規登録</a>（無料）</p>
<?php require __DIR__ . '/_auth_footer.php'; ?>
