<?php

/**
 * 決済中断ページ。参加者が Stripe の決済画面で「戻る」を押した場合に表示。
 * （まだ支払いは発生していません）
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$eventId = (string)($_GET['event_id'] ?? '');
$event = $eventId !== '' ? find_event($eventId) : null;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お支払いは完了していません</title>
    <style>
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.7; color: #1f2937; max-width: 640px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-top: 24px; }
        a { color: #2563eb; }
    </style>
</head>
<body>
    <div class="card">
        <p>お支払いは完了していません。料金は請求されていません。</p>
        <?php if ($event !== null): ?>
            <form action="checkout.php" method="post">
                <input type="hidden" name="event_id" value="<?= e($event['id']) ?>">
                <button type="submit">「<?= e($event['name'] ?? '') ?>」をもう一度申し込む</button>
            </form>
        <?php endif; ?>
        <p style="margin-top:24px;"><a href="index.php">← イベント一覧へ戻る</a></p>
    </div>
</body>
</html>
