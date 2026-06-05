<?php

/**
 * トップ（ランディング）。
 * マルチテナント構成のため、参加者は主催者から受け取った「イベントごとの申込リンク」
 * （apply.php?event_id=...）から申し込みます。ここは案内と主催者ログインへの入口。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>イベント事前決済サービス</title>
    <style>
        :root { --accent: #2563eb; --border: #e5e7eb; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.8; color: #1f2937; max-width: 640px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        h1 { font-size: 1.5rem; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px 24px; margin: 16px 0; }
        .btn { display: inline-block; background: var(--accent); color: #fff; text-decoration: none;
               padding: 12px 20px; border-radius: 8px; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
        .muted { color: var(--muted); font-size: .9rem; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <h1>イベント事前決済サービス</h1>
    <p class="muted">小規模イベントの参加費を、事前決済（前払い）または当日支払いで集められるサービスです。</p>

    <div class="card">
        <h2 style="font-size:1.1rem;">参加者の方へ</h2>
        <p>主催者から受け取った<strong>申込リンク</strong>から、各イベントにお申し込みください。</p>
        <p class="muted">カード情報の入力は決済代行 Stripe 上で行われ、主催者・当サービスは決済情報を保持しません。</p>
    </div>

    <div class="card">
        <h2 style="font-size:1.1rem;">主催者の方へ</h2>
        <p>イベントの作成・参加者管理・返金は主催者ページから行えます。</p>
        <p><a class="btn" href="admin/login.php">主催者ログイン</a></p>
        <p class="muted">アカウントは招待制です。招待リンクをお持ちの方はそちらからご登録ください。</p>
    </div>

    <p class="muted"><a href="policy.php">キャンセル・返金ポリシー</a></p>
</body>
</html>
