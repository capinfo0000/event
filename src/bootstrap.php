<?php

/**
 * アプリ共通の初期化処理。
 *
 * 【重要・設計思想】
 * このアプリのサーバー（PHP）は、クレジットカード情報を一切受け取らず・保存しません。
 * カード番号・有効期限・セキュリティコードの入力は、すべて Stripe がホストする
 * 決済ページ（Stripe Checkout）上で行われます。PCI DSS 準拠は Stripe 側の責任範囲です。
 * このサーバーが扱うのは「どのイベントに、誰（氏名・メール）が申し込んだか」だけです。
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/vendor/autoload.php';

/**
 * .env を読み込んで getenv() / $_ENV から参照できるようにする簡易ローダー。
 * （依存を増やさないため自前実装。値はクオート除去のみの素朴なパース。）
 */
function load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // 前後のクオートを外す
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

load_env(APP_ROOT . '/.env');

/**
 * 環境変数を取得。必須かつ未設定なら例外。
 */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function env_required(string $key): string
{
    $value = env($key);
    if ($value === null) {
        http_response_code(500);
        exit("設定エラー: 環境変数 {$key} が未設定です。.env を確認してください。\n");
    }
    return $value;
}

/**
 * イベント定義（config/events.json）を読み込む。
 * 参加者データは持たず、提供するイベントのカタログのみをここで管理する。
 */
function load_events(): array
{
    $path = APP_ROOT . '/config/events.json';
    if (!is_readable($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return $data['events'] ?? [];
}

function find_event(string $id): ?array
{
    foreach (load_events() as $event) {
        if (($event['id'] ?? null) === $id) {
            return $event;
        }
    }
    return null;
}

/**
 * このアプリの公開ベースURL（success/cancel/webhook の組み立てに使用）。
 * ローカル開発では APP_BASE_URL=http://localhost:8000 を想定。
 */
function base_url(): string
{
    return rtrim(env('APP_BASE_URL', 'http://localhost:8000'), '/');
}

/**
 * Stripe SDK を初期化（秘密鍵をセット）。
 */
function init_stripe(): void
{
    \Stripe\Stripe::setApiKey(env_required('STRIPE_SECRET_KEY'));
}

/**
 * 金額を「¥3,000」形式に整形（JPYは最小単位＝円なのでそのまま）。
 */
function format_amount(int $amount, string $currency): string
{
    if (strtolower($currency) === 'jpy') {
        return '¥' . number_format($amount);
    }
    return number_format($amount / 100, 2) . ' ' . strtoupper($currency);
}

/**
 * 出力エスケープ。
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
