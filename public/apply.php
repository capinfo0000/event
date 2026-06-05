<?php

/**
 * 参加申込フォーム（自前）。氏名・連絡先・参加人数・備考を入力してもらい、
 * その内容を checkout.php へ渡して Stripe 決済へ進む。
 *
 * 【DBは持たない】入力内容は当サーバーに保存せず、Stripe の決済データ（metadata 等）
 * として渡して保管する。名簿は管理画面が Stripe から読み出して表示する。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$eventId = (string) ($_GET['event_id'] ?? '');
$event = find_event($eventId);

if ($event === null) {
    http_response_code(404);
    exit('指定されたイベントが見つかりません。');
}

$unit = (int) ($event['amount'] ?? 0);
$currency = $event['currency'] ?? 'jpy';
// 参加人数の上限: capacity があればそれ、無ければ 10 を目安に
$maxParty = (int) ($event['capacity'] ?? 0);
if ($maxParty < 1) {
    $maxParty = 10;
}
$maxParty = min($maxParty, 20);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>参加申込 - <?= e($event['name'] ?? '') ?></title>
    <style>
        :root { --accent: #2563eb; --border: #e5e7eb; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.7; color: #1f2937; max-width: 640px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        h1 { font-size: 1.4rem; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px 24px; margin: 16px 0; }
        .meta { color: var(--muted); font-size: .9rem; }
        label { display: block; font-weight: 600; margin: 16px 0 4px; }
        .req { color: #dc2626; font-size: .8rem; }
        input, select, textarea { width: 100%; font-size: 1rem; padding: 10px 12px; border: 1px solid var(--border);
               border-radius: 8px; font-family: inherit; }
        textarea { min-height: 72px; resize: vertical; }
        .total { font-size: 1.3rem; font-weight: 700; color: var(--accent); margin: 16px 0; }
        .btn { display: inline-block; width: 100%; background: var(--accent); color: #fff; text-align: center;
               padding: 14px 20px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; font-size: 1.05rem; }
        .btn:hover { background: #1d4ed8; }
        .note { font-size: .85rem; color: var(--muted); margin-top: 12px; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <h1><?= e($event['name'] ?? '') ?> 参加申込</h1>
    <div class="card">
        <p class="meta">📅 <?= e($event['date'] ?? '') ?>　📍 <?= e($event['place'] ?? '') ?></p>
        <p><?= e($event['description'] ?? '') ?></p>
        <p class="meta">参加費：<strong><?= e(format_amount($unit, $currency)) ?></strong> / 1名</p>
    </div>

    <form action="checkout.php" method="post" class="card">
        <input type="hidden" name="event_id" value="<?= e($event['id']) ?>">

        <label for="name">お名前 <span class="req">必須</span></label>
        <input type="text" id="name" name="name" required maxlength="100" autocomplete="name" placeholder="山田 太郎">

        <label for="email">メールアドレス <span class="req">必須</span></label>
        <input type="email" id="email" name="email" required maxlength="200" autocomplete="email" placeholder="taro@example.com">

        <label for="phone">電話番号</label>
        <input type="tel" id="phone" name="phone" maxlength="30" autocomplete="tel" placeholder="090-1234-5678">

        <label for="party_size">参加人数（ご本人を含む） <span class="req">必須</span></label>
        <select id="party_size" name="party_size" required onchange="updateTotal()">
            <?php for ($i = 1; $i <= $maxParty; $i++): ?>
                <option value="<?= $i ?>"><?= $i ?> 名</option>
            <?php endfor; ?>
        </select>

        <label for="note">備考（アレルギー・ご要望など）</label>
        <textarea id="note" name="note" maxlength="500" placeholder="例：エビ・カニアレルギーあり"></textarea>

        <p class="total">お支払い合計：<span id="total"><?= e(format_amount($unit, $currency)) ?></span></p>

        <button type="submit" class="btn">この内容で支払いへ進む →</button>
        <p class="note">「支払いへ進む」を押すと、安全な Stripe の決済画面に移動します。
           カード情報は主催者には渡りません。キャンセル時の返金は<a href="policy.php">キャンセルポリシー</a>をご確認ください。</p>
    </form>

    <p><a href="index.php">← イベント一覧へ戻る</a></p>

    <script>
        // 参加人数に応じて合計金額を画面上で更新（計算の正は決済時にサーバー側で再確定）
        const UNIT = <?= $unit ?>;
        const CURRENCY = <?= json_encode(strtolower((string) $currency)) ?>;
        function formatAmount(total) {
            if (CURRENCY === 'jpy') {
                return '¥' + total.toLocaleString('ja-JP');
            }
            return (total / 100).toFixed(2) + ' ' + CURRENCY.toUpperCase();
        }
        function updateTotal() {
            const qty = parseInt(document.getElementById('party_size').value, 10) || 1;
            document.getElementById('total').textContent = formatAmount(UNIT * qty);
        }
        updateTotal();
    </script>
</body>
</html>
