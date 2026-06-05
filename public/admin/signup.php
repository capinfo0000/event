<?php

/**
 * 主催者サインアップ（招待制）。
 * 有効な招待コード（?invite=... または入力）が必須。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$error = '';
$code = (string) ($_GET['invite'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $code = (string) ($_POST['invite'] ?? '');
    $email = (string) ($_POST['email'] ?? '');
    $name = (string) ($_POST['display_name'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $invite = find_valid_invite($code);
    if ($invite === null) {
        $error = '招待コードが無効か、有効期限が切れています。';
    } elseif ($invite['email'] !== null && strtolower(trim($email)) !== $invite['email']) {
        $error = 'この招待は別のメールアドレス宛てです。';
    } else {
        try {
            $id = create_tenant($email, $password, $name);
            consume_invite($code, $id);
            login_tenant($email, $password);
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
<h1>主催者アカウント登録</h1>
<p class="muted">招待制です。受け取った招待コードでご登録ください。</p>
<?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card">
    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
    <label>招待コード</label>
    <input type="text" name="invite" required value="<?= e($code) ?>">
    <label>表示名（団体・主催者名）</label>
    <input type="text" name="display_name" maxlength="100" placeholder="〇〇イベント事務局">
    <label>メールアドレス</label>
    <input type="email" name="email" required autocomplete="email">
    <label>パスワード（8文字以上）</label>
    <input type="password" name="password" required minlength="8" autocomplete="new-password">
    <p style="margin-top:16px;"><button type="submit" class="btn">登録してはじめる</button></p>
</form>
<p class="muted">すでにアカウントをお持ちですか？ <a href="login.php">ログイン</a></p>
<?php require __DIR__ . '/_auth_footer.php'; ?>
