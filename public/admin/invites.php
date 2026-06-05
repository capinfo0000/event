<?php

/**
 * 招待コードの発行（プラットフォーム管理者のみ）。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$admin = require_admin_tenant();

$newCode = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $email = trim((string) ($_POST['email'] ?? ''));
    $newCode = create_invite($admin['id'], $email !== '' ? $email : null);
}

$token = csrf_token();
$base = base_url();

// 招待一覧
$invites = db()->query('SELECT * FROM invites ORDER BY created_at DESC LIMIT 100')->fetchAll();
require __DIR__ . '/_auth_header.php';
?>
<h1>招待コードの発行</h1>
<p class="muted"><a href="index.php">← ダッシュボードへ</a></p>

<?php if ($newCode !== ''): ?>
    <div class="card">
        <p>招待リンクを発行しました。これを相手に共有してください：</p>
        <input type="text" readonly value="<?= e($base . '/admin/signup.php?invite=' . $newCode) ?>" onclick="this.select()">
    </div>
<?php endif; ?>

<form method="post" class="card">
    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
    <label>招待先メール（任意・限定したい場合のみ）</label>
    <input type="email" name="email" placeholder="空欄なら誰でも使える招待">
    <p style="margin-top:16px;"><button type="submit" class="btn">招待コードを発行</button></p>
</form>

<div class="card">
    <p class="muted">発行済み招待（最新100件）</p>
    <?php foreach ($invites as $iv): ?>
        <div style="border-bottom:1px solid #eee; padding:6px 0; font-size:.85rem;">
            <code><?= e($iv['code']) ?></code>
            <?= $iv['used_by'] ? '— 使用済み' : ($iv['expires_at'] && $iv['expires_at'] < time() ? '— 期限切れ' : '— 未使用') ?>
            <?php if ($iv['email']): ?>（<?= e($iv['email']) ?>宛）<?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/_auth_footer.php'; ?>
