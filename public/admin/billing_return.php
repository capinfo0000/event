<?php

/**
 * プラン課金の Checkout から戻ってきたときの表示。
 * 実際のプラン反映は Webhook（webhook.php）で行うため、ここでは案内のみ。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
require __DIR__ . '/_auth_header.php';
?>
<h1>お手続きを受け付けました</h1>
<div class="card">
    <p>ご登録ありがとうございます。プランの反映には少し時間がかかる場合があります（自動で更新されます）。</p>
    <p>現在のプラン：<strong><?= e(plan_label($tenant['plan'] ?? 'free')) ?></strong></p>
    <p class="muted">反映されない場合は数十秒おいて、<a href="dashboard.php">ダッシュボード</a>を再読み込みしてください。</p>
</div>
<?php require __DIR__ . '/_auth_footer.php'; ?>
