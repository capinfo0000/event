<?php

/**
 * 2段階認証（TOTP）の有効化・解除。ログイン中の主催者自身が設定する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$msg = '';
$msgType = 'ok';

$enabled = tenant_totp_enabled($tenant);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'enable' && !$enabled) {
        $secret = (string) ($_SESSION['totp_setup_secret'] ?? '');
        $code = (string) ($_POST['code'] ?? '');
        if ($secret === '') {
            $msg = '設定用の情報が失効しました。もう一度お試しください。';
            $msgType = 'ng';
        } elseif (!crypto_available() && !ensure_app_key()) {
            $msg = '暗号化キー（APP_KEY）が用意できないため有効化できません。管理者へご連絡ください。';
            $msgType = 'ng';
        } elseif (totp_verify($secret, $code)) {
            set_tenant_totp($tenant['id'], $secret, true);
            unset($_SESSION['totp_setup_secret']);
            audit_log('twofa.enable', ['tenant' => $tenant['id']]);
            $tenant = find_tenant_by_id($tenant['id']);
            $enabled = true;
            $msg = '2段階認証を有効にしました。次回ログインからコードの入力が必要です。';
        } else {
            $msg = 'コードが正しくありません。認証アプリの6桁コードを入力してください。';
            $msgType = 'ng';
        }
    } elseif ($action === 'disable' && $enabled) {
        // 解除は現在のパスワード確認を必須にする（乗っ取り時の勝手な解除を防ぐ）。
        $pw = (string) ($_POST['current_password'] ?? '');
        if (!password_verify($pw, (string) $tenant['password_hash'])) {
            $msg = '現在のパスワードが違います。';
            $msgType = 'ng';
        } else {
            set_tenant_totp($tenant['id'], null, false);
            audit_log('twofa.disable', ['tenant' => $tenant['id']]);
            $tenant = find_tenant_by_id($tenant['id']);
            $enabled = false;
            $msg = '2段階認証を解除しました。';
        }
    }
}

// 未有効なら設定用の秘密鍵をセッションに用意（確認できるまで DB には保存しない）。
if (!$enabled && empty($_SESSION['totp_setup_secret'])) {
    $_SESSION['totp_setup_secret'] = totp_generate_secret();
}
$setupSecret = (string) ($_SESSION['totp_setup_secret'] ?? '');
$otpauth = $setupSecret !== '' ? totp_uri($setupSecret, (string) $tenant['email']) : '';

$token = csrf_token();
$pageTitle = '2段階認証';
$pageSub = 'ログインを二重に保護します';
require __DIR__ . '/_app_header.php';
?>
<?php if ($msg !== ''): ?>
    <div class="flash <?= $msgType === 'ok' ? 'flash--ok' : 'flash--ng' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($enabled): ?>
    <div class="card">
        <div class="card__title">2段階認証：<span style="color:#16a34a;">有効</span></div>
        <p>ログイン時に認証アプリの6桁コードが必要です。</p>
    </div>
    <div class="card">
        <div class="card__title">解除する</div>
        <p class="muted" style="margin-top:0;">解除には現在のパスワードが必要です。</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="disable">
            <label>現在のパスワード</label>
            <input type="password" name="current_password" required autocomplete="current-password">
            <p style="margin-top:16px;"><button type="submit" class="btn btn--danger">2段階認証を解除</button></p>
        </form>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card__title">2段階認証を有効にする</div>
        <ol style="line-height:1.9;">
            <li>認証アプリ（Google Authenticator / Microsoft Authenticator など）を開く。</li>
            <li>「手動で追加」でアカウント名にメール、キーに下記の<strong>セットアップキー</strong>を入力（種類は「時間ベース」）。</li>
            <li>アプリに表示された6桁コードを下に入力して「有効にする」。</li>
        </ol>
        <label>セットアップキー</label>
        <input type="text" class="js-select" readonly value="<?= e($setupSecret) ?>">
        <label style="margin-top:10px;">登録用URI（対応アプリに貼り付け可）</label>
        <input type="text" class="js-select" readonly value="<?= e($otpauth) ?>">
        <form method="post" style="margin-top:14px;">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="enable">
            <label>認証アプリの6桁コード</label>
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required placeholder="123456">
            <p style="margin-top:16px;"><button type="submit" class="btn">有効にする</button></p>
        </form>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/_app_footer.php'; ?>
