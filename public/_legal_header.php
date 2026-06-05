<?php

/** 法務ページ共通ヘッダ。$title を設定してから require する。 */
declare(strict_types=1);
$title = $title ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <style>
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.9; color: #1f2937; max-width: 760px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px 28px; }
        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.1rem; margin-top: 24px; }
        table { border-collapse: collapse; width: 100%; margin: 12px 0; }
        th, td { border: 1px solid #e5e7eb; padding: 10px 12px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; white-space: nowrap; width: 30%; }
        a { color: #2563eb; }
        .muted { color: #6b7280; font-size: .9rem; }
        ol { padding-left: 1.2em; }
    </style>
</head>
<body>
    <div class="card">
        <h1><?= e($title) ?></h1>
