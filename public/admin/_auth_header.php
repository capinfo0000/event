<?php

/** ログイン／サインアップ画面共通のヘッダ。 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>主催者 - イベント事前決済</title>
    <style>
        :root { --accent: #2563eb; --border: #e5e7eb; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.7; color: #1f2937; max-width: 440px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        h1 { font-size: 1.4rem; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px 24px; margin: 16px 0; }
        label { display: block; font-weight: 600; margin: 14px 0 4px; font-size: .9rem; }
        input { width: 100%; font-size: 1rem; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; }
        .btn { width: 100%; background: var(--accent); color: #fff; border: none; cursor: pointer;
               font-weight: 700; padding: 12px; border-radius: 8px; font-size: 1rem; }
        .btn:hover { background: #1d4ed8; }
        .muted { color: var(--muted); font-size: .9rem; }
        .err { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 8px; }
        a { color: var(--accent); }
    </style>
</head>
<body>
