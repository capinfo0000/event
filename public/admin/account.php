<?php

/**
 * アカウント設定：表示名の変更、パスワードの変更。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$msg = '';
$msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'name') {
        $name = trim((string) ($_POST['display_name'] ?? ''));
        update_tenant_display_name($tenant['id'], mb_substr($name, 0, 100));
        $msg = '表示名を更新しました。';
        $tenant = find_tenant_by_id($tenant['id']);
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $tenant['password_hash'])) {
            $msg = '現在のパスワードが違います。';
            $msgType = 'ng';
        } else {
            try {
                update_tenant_password($tenant['id'], $new);
                $msg = 'パスワードを変更しました。';
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $msgType = 'ng';
            }
        }
    }
}

$token = csrf_token();
require __DIR__ . '/_auth_header.php';
?>
<h1>アカウント設定</h1>
<p class="muted"><a href="dashboard.php">← ダッシュボード</a> ／ <?= e($tenant['email']) ?></p>

<?php if ($msg !== ''): ?>
    <div class="<?= $msgType === 'ok' ? '' : 'err' ?>" style="<?= $msgType === 'ok' ? 'background:#dcfce7;color:#166534;padding:10px 14px;border-radius:8px;' : '' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <h2 style="font-size:1.05rem;">表示名</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="name">
        <input type="text" name="display_name" maxlength="100" value="<?= e($tenant['display_name']) ?>">
        <p style="margin-top:12px;"><button type="submit" class="btn" style="width:auto;">表示名を更新</button></p>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1.05rem;">パスワード変更</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="password">
        <label>現在のパスワード</label>
        <input type="password" name="current_password" required autocomplete="current-password">
        <label>新しいパスワード（8文字以上）</label>
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        <p style="margin-top:12px;"><button type="submit" class="btn" style="width:auto;">パスワードを変更</button></p>
    </form>
</div>
<?php require __DIR__ . '/_auth_footer.php'; ?>
