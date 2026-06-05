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

// 連携済みの主催者のイベントのみ公開（未連携は申込できないため出さない）
$events = empty($tenant['stripe_account_id']) ? [] : tenant_events($tenantId);
$account = $tenant['stripe_account_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tenant['display_name']) ?> のイベント</title>
    <style>
        :root { --accent: #2563eb; --border: #e5e7eb; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.7; color: #1f2937; max-width: 720px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        h1 { font-size: 1.5rem; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin: 16px 0; }
        .card h2 { margin: 0 0 8px; font-size: 1.2rem; }
        .meta { color: var(--muted); font-size: .9rem; margin: 4px 0; }
        .price { font-size: 1.2rem; font-weight: 700; color: var(--accent); margin: 10px 0; }
        .btn { display: inline-block; background: var(--accent); color: #fff; text-decoration: none;
               padding: 10px 18px; border-radius: 8px; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
        .btn[aria-disabled="true"] { background: #9ca3af; pointer-events: none; }
        .muted { color: var(--muted); }
        .full { color: #dc2626; font-weight: 700; }
    </style>
</head>
<body>
    <h1><?= e($tenant['display_name']) ?> のイベント</h1>

    <?php if (empty($events)): ?>
        <div class="card"><p>現在受付中のイベントはありません。</p></div>
    <?php else: ?>
        <?php foreach ($events as $ev): ?>
            <?php
                // 残席（capacity>0 のとき）。取得失敗時は表示なし。
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
                <h2><?= e($ev['name']) ?></h2>
                <p class="meta">📅 <?= e($ev['date']) ?>　📍 <?= e($ev['place']) ?></p>
                <p><?= e($ev['description']) ?></p>
                <p class="price">
                    <?php if ($ev['allow_prepay']): ?>事前 <?= e(format_amount($ev['amount'], $ev['currency'])) ?><?php endif; ?>
                    <?php if ($ev['allow_prepay'] && $ev['allow_onsite']): ?> ／ <?php endif; ?>
                    <?php if ($ev['allow_onsite']): ?><span class="muted" style="font-size:.9rem;">当日 <?= e(format_amount($ev['amount_onsite'], $ev['currency'])) ?></span><?php endif; ?>
                </p>
                <?php if ($cap > 0 && $remaining !== null): ?>
                    <p class="meta">定員 <?= $cap ?> 名　<?= $full ? '<span class="full">満員</span>' : '残り ' . $remaining . ' 名' ?></p>
                <?php endif; ?>
                <?php if ($full): ?>
                    <span class="btn" aria-disabled="true">満員</span>
                <?php else: ?>
                    <a class="btn" href="apply.php?event_id=<?= e($ev['id']) ?>">申し込む</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="muted" style="margin-top:24px;font-size:.85rem;">
        カード情報の入力は決済代行 Stripe 上で行われ、主催者・当サービスは決済情報を保持しません。
        <a href="policy.php">キャンセル・返金ポリシー</a>
    </p>
</body>
</html>
