<?php

/**
 * 主催者ダッシュボード（ログイン後のトップ）。
 * Stage 1: アカウント情報と Stripe 連携状況を表示。
 * イベント管理・名簿は Stage 3 でこのテナント単位に移行する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$connected = !empty($tenant['stripe_account_id']);
$token = csrf_token();
require __DIR__ . '/_auth_header.php';
?>
<h1>ようこそ、<?= e($tenant['display_name']) ?> さん</h1>
<p class="muted">
    <?= e($tenant['email']) ?>
    <?php if ((int) $tenant['is_admin'] === 1): ?>／ <a href="invites.php">招待を発行</a><?php endif; ?>
    ／ <a href="logout.php">ログアウト</a>
</p>

<div class="card">
    <h2 style="font-size:1.1rem;">Stripe 連携</h2>
    <?php if ($connected): ?>
        <p>✅ 連携済み：<code><?= e($tenant['stripe_account_id']) ?></code></p>
        <p class="muted">参加費の入金はあなたの Stripe アカウントへ直接行われます。</p>
    <?php else: ?>
        <p>⚠️ まだ Stripe に連携していません。連携すると、あなたの口座で参加費を受け取れます。</p>
        <p style="margin-top:12px;"><a class="btn" href="connect.php" style="display:inline-block; width:auto; text-decoration:none;">Stripe と連携する</a></p>
    <?php endif; ?>
</div>

<div class="card">
    <h2 style="font-size:1.1rem;">イベント・参加者</h2>
    <p class="muted">※ Stage 3 で、このアカウント専用のイベント管理・参加者名簿をここに移行します。</p>
</div>
<?php require __DIR__ . '/_auth_footer.php'; ?>
