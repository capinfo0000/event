<?php

/**
 * 初回パスワード変更（強制）。管理者が発行した仮パスワードのアカウントは、
 * ここで新しいパスワードに変更するまで他の画面へ進めない（require_tenant のガード）。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if ($new !== $confirm) {
        $error = '確認用パスワードが一致しません。';
    } else {
        try {
            update_tenant_password($tenant['id'], $new); // 強度チェックは内部で実施
            set_tenant_must_change_password($tenant['id'], false);
            audit_log('account.first_password_set', ['tenant' => $tenant['id']]);
            header('Location: dashboard.php');
            exit;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$token = csrf_token();
require __DIR__ . '/_auth_header.php';
?>
<h1>パスワードの設定</h1>
<p class="muted">はじめに、ご自身の新しいパスワードを設定してください（この後の画面に進むには変更が必要です）。</p>
<?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card">
    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
    <label>新しいパスワード（8文字以上）</label>
    <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
    <label>新しいパスワード（確認）</label>
    <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
    <p style="margin-top:16px;"><button type="submit" class="btn">パスワードを設定</button></p>
</form>
<p class="muted"><a href="logout.php">ログアウト</a></p>
<?php require __DIR__ . '/_auth_footer.php'; ?>
