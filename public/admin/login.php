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

    // デモ（ポートフォリオ）ログイン：DEMO_MODE 有効かつメール・パスワード空欄のとき、
    // サンプル入りのデモ用テナントへ入る。CAPTCHA・回数制限は対象外（機密も外部送信も無い）。
    if (demo_mode_enabled() && $email === '' && $password === '') {
        // 濫用対策: 空欄デモログイン連打（毎回シード再作成・セッション生成）による資源浪費を IP 単位で抑止。
        if (!rate_limit_check('demo_login', 20, 3600)) {
            $error = 'デモへのアクセスが集中しています。しばらく時間をおいてからお試しください。';
        } elseif (demo_login()) {
            audit_log('login.demo', []);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'デモの準備に失敗しました。時間をおいて再度お試しください。';
        }
    // 総当たり対策：メール単位（標的型）と IP 単位（メール横断スプレー）の両方で失敗回数を制限。
    } elseif (recent_failed_logins($email) >= 5 || recent_failed_logins_by_ip(client_ip()) >= 20) {
        audit_log('login.blocked', ['email' => mask_email_for_log($email)]);
        $error = '試行回数が多すぎます。しばらく時間をおいてからお試しください。';
    } elseif (!captcha_verify($_POST['cf-turnstile-response'] ?? null, true)) {
        $error = '認証（CAPTCHA）に失敗しました。もう一度お試しください。';
    } elseif (login_tenant($email, $password)) {
        clear_failed_logins($email);
        audit_log('login.ok', ['email' => mask_email_for_log($email)]);
        header('Location: dashboard.php');
        exit;
    } else {
        record_failed_login($email);
        audit_log('login.fail', ['email' => mask_email_for_log($email)]);
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
    <?= captcha_widget_html() ?>
    <p style="margin-top:16px;"><button type="submit" class="btn">ログイン</button></p>
</form>
<?php if (demo_mode_enabled()): ?>
<div class="card" style="border-style:dashed;">
    <p style="margin-top:0;"><strong>デモをご覧の方へ</strong></p>
    <p class="muted">メールアドレスとパスワードは<strong>空欄のまま</strong>で、下のボタンから主催者管理画面のデモをお試しいただけます。サンプルのイベントが入っています（決済は無効・データは都度リセット）。</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <button type="submit" class="btn btn--ghost">デモを見る（入力不要）</button>
    </form>
</div>
<?php endif; ?>
<p class="muted"><a href="forgot.php">パスワードを忘れた場合</a></p>
<p class="muted">アカウント登録は招待制です。登録には管理者が発行した招待リンクが必要です。</p>
<?php require __DIR__ . '/_auth_footer.php'; ?>
