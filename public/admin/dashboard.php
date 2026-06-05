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
$plan = $tenant['plan'] ?? 'free';
$maxEvents = plan_max_events($plan);
$usedEvents = tenant_event_count($tenant['id']);
$publicUrl = base_url() . '/o.php?t=' . urlencode($tenant['id']);
$token = csrf_token();
require __DIR__ . '/_auth_header.php';
?>
<h1>ようこそ、<?= e($tenant['display_name']) ?> さん</h1>
<p class="muted">
    <?= e($tenant['email']) ?>
    ／ <a href="account.php">アカウント設定</a>
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
    <h2 style="font-size:1.1rem;">プラン</h2>
    <p>現在のプラン：<strong><?= e(plan_label($plan)) ?></strong></p>
    <p>同じ開催月に登録できるイベント数：<strong><?= $maxEvents === PHP_INT_MAX ? '無制限' : $maxEvents . ' 件' ?></strong></p>
    <p class="muted">登録済みイベント数（全期間）：<?= $usedEvents ?> 件</p>
    <p><a class="btn" href="upgrade.php" style="display:inline-block; width:auto; text-decoration:none;">プランをアップグレード</a>
       <?php if (!empty($tenant['stripe_customer_id'])): ?>
           <a href="portal.php" class="muted" style="margin-left:8px;">支払い・解約の管理</a>
       <?php endif; ?>
    </p>
    <p class="muted">上限は「イベントの開催月」ごとに数えます。料金別に上限が増えます（お支払いは運営へのプラン利用料）。</p>
</div>

<div class="card">
    <h2 style="font-size:1.1rem;">イベント・参加者</h2>
    <p><a href="events.php">イベント管理</a> ／ <a href="index.php">参加者管理</a></p>
    <p class="muted">公開イベント一覧（参加者に共有するリンク）：</p>
    <input type="text" readonly value="<?= e($publicUrl) ?>" onclick="this.select()" style="width:100%;">
</div>
<?php require __DIR__ . '/_auth_footer.php'; ?>
