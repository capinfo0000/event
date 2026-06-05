<?php

/**
 * トップページ: 開催イベントの一覧と「申し込む（前払い）」ボタン。
 * 申込ボタンを押すと checkout.php 経由で Stripe の決済ページへ移動します。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$events = load_events();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>イベント申込（事前決済）</title>
    <style>
        :root { --accent: #2563eb; --border: #e5e7eb; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.7; color: #1f2937; max-width: 720px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        h1 { font-size: 1.5rem; }
        .lead { color: var(--muted); }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin: 16px 0; }
        .card h2 { margin: 0 0 8px; font-size: 1.2rem; }
        .meta { color: var(--muted); font-size: .9rem; margin: 4px 0; }
        .price { font-size: 1.4rem; font-weight: 700; color: var(--accent); margin: 12px 0; }
        .btn { display: inline-block; background: var(--accent); color: #fff; text-decoration: none;
               padding: 12px 20px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #1d4ed8; }
        .note { font-size: .85rem; color: var(--muted); margin-top: 8px; }
        footer { margin-top: 32px; font-size: .85rem; color: var(--muted); border-top: 1px solid var(--border); padding-top: 16px; }
        footer a { color: var(--accent); }
    </style>
</head>
<body>
    <h1>イベント申込（事前決済）</h1>
    <p class="lead">参加には事前のお支払い（前払い）が必要です。お支払い後にお申し込み完了となります。<br>
       なお、<strong>カード番号などの決済情報は決済代行の Stripe が直接お預かりし、主催者（当方）は一切受け取りも保管もいたしません。</strong></p>

    <?php if (empty($events)): ?>
        <div class="card"><p>現在受付中のイベントはありません。</p></div>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <div class="card">
                <h2><?= e($event['name'] ?? '') ?></h2>
                <p class="meta">📅 <?= e($event['date'] ?? '') ?>　📍 <?= e($event['place'] ?? '') ?></p>
                <p><?= e($event['description'] ?? '') ?></p>
                <p class="price"><?= e(format_amount((int)($event['amount'] ?? 0), $event['currency'] ?? 'jpy')) ?></p>
                <form action="checkout.php" method="post">
                    <input type="hidden" name="event_id" value="<?= e($event['id'] ?? '') ?>">
                    <button type="submit" class="btn">申し込む（前払い）</button>
                </form>
                <p class="note">ボタンを押すと、安全な Stripe の決済画面に移動します。カード情報は主催者には渡りません。<br>
                   キャンセル時の返金については<a href="policy.php">キャンセルポリシー</a>をご確認ください。</p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer>
        <p>クレジットカード情報の入力・処理は決済代行サービス Stripe 上で行われます。主催者（当方）はカード番号・有効期限・セキュリティコードなどの決済情報を受け取らず、当方のサーバーにも一切保存しません。</p>
        <p><a href="policy.php">キャンセル・返金ポリシー</a></p>
    </footer>
</body>
</html>
