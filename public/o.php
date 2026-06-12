<?php

/**
 * 主催者の公開イベント一覧ページ。
 * 主催者はこの 1 つのリンク（/o.php?t=テナントID）を共有すれば、
 * 参加者が開催中のイベントを一覧から選んで申し込める。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$tenantId = (string) ($_GET['t'] ?? '');
$tenant = $tenantId !== '' ? find_tenant_by_id($tenantId) : null;

if ($tenant === null) {
    http_response_code(404);
    exit('主催者が見つかりません。');
}

// 公開イベント一覧（残席計算は運営者自身の Stripe アカウントから取得）
$events = tenant_events($tenantId);
$account = effective_stripe_account($tenant['stripe_account_id'] ?? null); // Connect: 接続済みは主催者のStripe
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tenant['display_name']) ?> のイベント</title>
    <link rel="stylesheet" href="/assets/app.css">
    <style nonce="<?= e(csp_nonce()) ?>">
        .ev-price { font-size: 1.1rem; font-weight: 700; color: var(--accent); margin: 10px 0; }
        .full { color: var(--dng); font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <div class="brandbar"><span class="logo">🎟️</span> <?= e($tenant['display_name']) ?> のイベント</div>

    <?php if (empty($events)): ?>
        <div class="card"><p style="margin:0;">現在受付中のイベントはありません。</p></div>
    <?php else: ?>
        <?php foreach ($events as $ev): ?>
            <?php
                $cap = (int) $ev['capacity'];
                $remaining = null; $full = false;
                if ($cap > 0) {
                    try {
                        $remaining = max(0, $cap - event_headcount($ev['id'], $account));
                        $full = ($remaining <= 0);
                    } catch (\Throwable $e) { $remaining = null; }
                }
            ?>
            <div class="card">
                <div class="card__title" style="font-size:1.15rem;"><?= e($ev['name']) ?></div>
                <p class="muted">📅 <?= e($ev['date']) ?>　📍 <?= e($ev['place']) ?></p>
                <p><?= e($ev['description']) ?></p>
                <p class="ev-price">
                    <?php if ($ev['allow_prepay']): ?>事前 <?= e(format_amount($ev['amount'], $ev['currency'])) ?><?php endif; ?>
                    <?php if ($ev['allow_prepay'] && $ev['allow_onsite']): ?> ／ <?php endif; ?>
                    <?php if ($ev['allow_onsite']): ?><span class="muted" style="font-size:.9rem;">当日 <?= e(format_amount($ev['amount_onsite'], $ev['currency'])) ?></span><?php endif; ?>
                </p>
                <?php if ($cap > 0 && $remaining !== null): ?>
                    <p class="muted">定員 <?= $cap ?> 名　<?= $full ? '<span class="full">満員</span>' : '残り ' . $remaining . ' 名' ?></p>
                <?php endif; ?>
                <?php if ($full): ?>
                    <span class="btn" aria-disabled="true" style="background:#9ca3af;border-color:#9ca3af;pointer-events:none;">満員</span>
                <?php else: ?>
                    <a class="btn" href="apply.php?event_id=<?= e($ev['id']) ?>">申し込む</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="muted" style="margin-top:24px; font-size:.85rem;">
        カード情報の入力は決済代行 Stripe 上で行われ、主催者・当サービスは決済情報を保持しません。
        <a href="policy.php">キャンセル・返金ポリシー</a>
    </p>
</div>
</body>
</html>
