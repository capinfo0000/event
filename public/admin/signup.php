<?php

/**
 * 主催者アカウント登録（招待制）。
 * 登録には管理者が /admin/invites.php で発行した招待リンク（?invite=コード）が必要。
 * 招待が無い/無効なアクセスでは登録フォームを出さない。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$error = '';

// 招待コードは URL（?invite=）または送信フォームの hidden から受け取る。
$code = trim((string) ($_GET['invite'] ?? ($_POST['invite'] ?? '')));
$invite = $code !== '' ? find_valid_invite($code) : null;

// 有効な招待が無ければ登録画面の存在を見せず、ログインへ転送する。
if ($invite === null) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $email = (string) ($_POST['email'] ?? '');
    $name = (string) ($_POST['display_name'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($invite === null) {
        $error = '招待が無効か、期限切れ・使用済みです。管理者に新しい招待リンクをご依頼ください。';
    } elseif (!rate_limit_check('signup', 5, 3600)) {
        $error = '登録の試行が多すぎます。しばらく時間をおいて再度お試しください。';
    } elseif (strtolower(trim($email)) === DEMO_TENANT_EMAIL) {
        $error = 'このメールアドレスはご利用いただけません。';
    } elseif ($invite['email'] !== null && $invite['email'] !== ''
              && strtolower(trim($email)) !== strtolower(trim((string) $invite['email']))) {
        // 招待にメール指定がある場合は、その宛先だけが使える。
        $error = 'この招待は別のメールアドレス宛に発行されています。招待された宛先でご登録ください。';
    } elseif (!captcha_verify($_POST['cf-turnstile-response'] ?? null, true)) {
        $error = '認証（CAPTCHA）に失敗しました。もう一度お試しください。';
    } else {
        try {
            $newId = create_tenant($email, $password, $name);
            consume_invite($code, $newId); // 招待を使用済みにする（多重利用防止）
            audit_log('signup', ['email' => mask_email_for_log($email), 'invite' => substr($code, 0, 8)]);
            login_tenant($email, $password);
            header('Location: dashboard.php');
            exit;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
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
<h1>主催者アカウント登録</h1>
<?php if ($invite === null): ?>
    <?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <div class="card">
        <p style="margin:0 0 8px;">アカウント登録には、<strong>管理者が発行した招待リンク</strong>が必要です。</p>
        <p class="muted" style="margin:0;">招待リンクをお持ちでない場合は、運営者（管理者）へご依頼ください。</p>
    </div>
    <p class="muted">すでにアカウントをお持ちですか？ <a href="login.php">ログイン</a></p>
<?php else: ?>
    <p class="muted">招待を確認しました。以下を入力して登録してください。</p>
    <?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="card">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="invite" value="<?= e($code) ?>">
        <label>表示名（団体・主催者名）</label>
        <input type="text" name="display_name" maxlength="100" placeholder="〇〇イベント事務局">
        <label>メールアドレス</label>
        <input type="email" name="email" required autocomplete="email"
               value="<?= e((string) ($invite['email'] ?? '')) ?>" <?= ($invite['email'] ?? '') !== '' ? 'readonly' : '' ?>>
        <label>パスワード（8文字以上）</label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
        <?= captcha_widget_html() ?>
        <p style="margin-top:16px;"><button type="submit" class="btn">登録してはじめる</button></p>
    </form>
    <p class="muted">すでにアカウントをお持ちですか？ <a href="login.php">ログイン</a></p>
<?php endif; ?>
<?php require __DIR__ . '/_auth_footer.php'; ?>
