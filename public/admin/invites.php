<?php

/**
 * 主催者アカウントの発行（プラットフォーム管理者のみ）。
 *  - 初期アカウント発行: メール＋仮パスワードを作成し、その場で一度だけ表示。
 *    受け取った人はログイン後、アカウント設定でパスワード・表示名を変更する。
 *  - 招待リンク発行: 相手に自分で登録してもらう場合（従来方式）。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$admin = require_admin_tenant();
$tenant = $admin; // シェルのサイドバー表示用

/** 紛らわしい文字を除いた強いランダム仮パスワードを生成。 */
function gen_temp_password(int $len = 12): string
{
    $alpha = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alpha) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alpha[random_int(0, $max)];
    }
    return $out;
}

$newCode = '';
$createdEmail = '';
$createdPassword = '';
$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'account') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $name = trim((string) ($_POST['display_name'] ?? ''));
        $pw = gen_temp_password(12);
        try {
            $newId = create_tenant($email, $pw, $name);
            set_tenant_must_change_password($newId, true); // 初回ログイン時にパスワード変更を強制
            audit_log('admin.create_account', ['by' => $admin['id'], 'email' => mask_email_for_log($email)]);
            $createdEmail = $email;
            $createdPassword = $pw; // この画面でのみ一度だけ表示（保存はハッシュのみ）
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } else { // invite
        $email = trim((string) ($_POST['email'] ?? ''));
        $newCode = create_invite($admin['id'], $email !== '' ? $email : null);
    }
}

$token = csrf_token();
$base = base_url();
$invites = db()->query('SELECT * FROM invites ORDER BY created_at DESC LIMIT 100')->fetchAll();

$pageTitle = '主催者アカウントの発行';
require __DIR__ . '/_app_header.php';
?>
<?php if ($error !== ''): ?><div class="flash flash--ng"><?= e($error) ?></div><?php endif; ?>

<?php if ($createdPassword !== ''): ?>
    <div class="card" style="border:1px solid #16a34a;">
        <div class="card__title">アカウントを発行しました（この内容は一度だけ表示されます）</div>
        <p class="muted" style="margin-top:0;">下記のメールと仮パスワードを本人に伝えてください。本人はログイン後、<strong>アカウント設定</strong>でパスワードを変更できます。</p>
        <label>メールアドレス</label>
        <input type="text" class="js-select" readonly value="<?= e($createdEmail) ?>">
        <label>仮パスワード</label>
        <input type="text" class="js-select" readonly value="<?= e($createdPassword) ?>">
        <p class="muted" style="margin-bottom:0;">ログインURL：<?= e($base) ?>/admin/login.php</p>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card__title">初期アカウントを発行</div>
    <p class="muted" style="margin-top:0;">メールを指定すると仮パスワード付きのアカウントを作成します。パスワードは本人が後で変更します。</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="account">
        <label>表示名（団体・主催者名・任意）</label>
        <input type="text" name="display_name" maxlength="100" placeholder="〇〇イベント事務局">
        <label>メールアドレス <span class="req">必須</span></label>
        <input type="email" name="email" required placeholder="operator@example.com">
        <p style="margin-top:16px;"><button type="submit" class="btn">アカウントを発行</button></p>
    </form>
</div>

<?php if ($newCode !== ''): ?>
    <div class="card">
        <p style="margin-top:0;">招待リンクを発行しました。これを相手に共有してください：</p>
        <input type="text" class="js-select" readonly value="<?= e($base . '/admin/signup.php?invite=' . $newCode) ?>">
    </div>
<?php endif; ?>

<div class="card">
    <div class="card__title">招待リンクで登録してもらう（任意）</div>
    <p class="muted" style="margin-top:0;">相手に自分でパスワードを決めて登録してもらう場合はこちら。</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="invite">
        <label>招待先メール（任意・限定したい場合のみ）</label>
        <input type="email" name="email" placeholder="空欄なら誰でも使える招待">
        <p style="margin-top:16px;"><button type="submit" class="btn btn--ghost">招待リンクを発行</button></p>
    </form>
</div>

<div class="card">
    <div class="card__title">発行済み招待（最新100件）</div>
    <?php if (empty($invites)): ?>
        <p class="muted" style="margin:0;">まだありません。</p>
    <?php endif; ?>
    <?php foreach ($invites as $iv): ?>
        <div style="border-bottom:1px solid var(--border); padding:8px 0; font-size:.86rem;">
            <code><?= e($iv['code']) ?></code>
            <?= $iv['used_by'] ? '— 使用済み' : ($iv['expires_at'] && $iv['expires_at'] < time() ? '— 期限切れ' : '— 未使用') ?>
            <?php if ($iv['email']): ?>（<?= e($iv['email']) ?>宛）<?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/_app_footer.php'; ?>
