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

// データ層・テナント（マルチテナント）ヘルパー。関数定義のみで、呼び出し時に env() を使う。
require __DIR__ . '/db.php';
require __DIR__ . '/tenant.php';

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
 * イベント定義を config/events.json に保存する（管理画面からの登録・編集・削除で使用）。
 * 自前DBは持たない方針のため、イベントの「カタログ」だけをファイルで永続化する。
 * 排他ロックを取り、一時ファイル経由で原子的に書き換える。
 *
 * @param array<int, array<string, mixed>> $events
 */
function save_events(array $events): void
{
    $path = APP_ROOT . '/config/events.json';
    $payload = [
        '_comment' => '提供するイベントのカタログ。管理画面（/admin/events.php）から編集できます。amount は最小通貨単位（JPYは円そのまま、例: 3000 = ¥3,000）。capacity は表示・申込人数の上限目安です。',
        'events' => array_values($events),
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new \RuntimeException('イベント定義の JSON 変換に失敗しました。');
    }

    $tmp = $path . '.tmp';
    $fp = fopen($tmp, 'w');
    if ($fp === false) {
        throw new \RuntimeException('イベント定義ファイルを書き込めません。');
    }
    flock($fp, LOCK_EX);
    fwrite($fp, $json . "\n");
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new \RuntimeException('イベント定義ファイルの保存に失敗しました。');
    }
}

/**
 * 重複しないイベントIDを生成する（管理画面での新規登録時）。
 * 推測・衝突を避けるため短いランダム英数字を用いる。
 */
function generate_event_id(): string
{
    do {
        $id = 'ev_' . bin2hex(random_bytes(4));
    } while (find_event($id) !== null);
    return $id;
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

/* =====================================================================
 * 管理（参加者管理）画面むけの共通処理
 *
 * 設計はトップと同じく「自前DBを持たない」。参加者の名簿は Stripe の
 * Checkout セッション（metadata.event_id でイベントを識別）から都度取得する。
 * 個人情報を表示するため、管理画面は ID＋パスワードの Basic 認証で保護する。
 * ===================================================================== */

/**
 * 管理画面の Basic 認証。.env の ADMIN_USER / ADMIN_PASS と照合する。
 * 認証情報が未設定・不一致なら 401 を返して終了する。
 */
function require_admin_auth(): void
{
    $expectedUser = env('ADMIN_USER');
    $expectedPass = env('ADMIN_PASS');

    if ($expectedUser === null || $expectedPass === null) {
        http_response_code(500);
        exit('設定エラー: 管理画面の ADMIN_USER / ADMIN_PASS が未設定です。.env を確認してください。');
    }

    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW'] ?? '';

    // タイミング攻撃を避けるため hash_equals で定数時間比較
    $ok = hash_equals($expectedUser, $user) && hash_equals($expectedPass, $pass);

    if (!$ok) {
        header('WWW-Authenticate: Basic realm="参加者管理", charset="UTF-8"');
        http_response_code(401);
        exit('認証が必要です。');
    }
}

/**
 * CSRF トークンを取得（なければ生成）。セッションに保存する。
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 送信された CSRF トークンを検証。不一致なら 400 で終了。
 */
function csrf_verify(?string $token): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected === '' || !is_string($token) || !hash_equals($expected, $token)) {
        http_response_code(400);
        exit('不正なリクエストです（CSRF トークン不一致）。画面を開き直してください。');
    }
}

/**
 * 指定イベントの「支払い済み参加者」一覧を Stripe から取得する。
 *
 * Checkout セッション一覧を辿り、metadata.event_id が一致し、かつ支払い済み
 * （payment_status === 'paid'）のものを参加者として返す。返金状況も付与する。
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_event_participants(string $eventId): array
{
    init_stripe();

    $participants = [];
    $params = [
        'limit' => 100,
        'expand' => ['data.payment_intent.latest_charge'],
    ];

    foreach (\Stripe\Checkout\Session::all($params)->autoPagingIterator() as $session) {
        if (($session->metadata['event_id'] ?? null) !== $eventId) {
            continue;
        }
        if ($session->payment_status !== 'paid') {
            continue; // 未払い・中断セッションは名簿に含めない
        }

        // 参加者名: 自前フォームの metadata → Stripe カスタム項目 → 顧客情報の順で拾う
        $meta = $session->metadata ?? null;
        $name = $meta['participant_name'] ?? '';
        if ($name === '') {
            foreach (($session->custom_fields ?? []) as $field) {
                if (($field->key ?? '') === 'participant_name') {
                    $name = $field->text->value ?? '';
                    break;
                }
            }
        }
        if ($name === '') {
            $name = $session->customer_details->name ?? '';
        }

        // 自前フォームで集めた電話・参加人数・備考（metadata 優先）
        $phone = $meta['phone'] ?? ($session->customer_details->phone ?? '');
        $partySize = max(1, (int) ($meta['party_size'] ?? 1));
        $note = $meta['note'] ?? '';

        $pi = $session->payment_intent;            // expand 済みのオブジェクト
        $piId = is_object($pi) ? ($pi->id ?? '') : (string) $pi;
        $charge = is_object($pi) ? ($pi->latest_charge ?? null) : null;

        $amountRefunded = 0;
        $fullyRefunded = false;
        if (is_object($charge)) {
            $amountRefunded = (int) ($charge->amount_refunded ?? 0);
            $fullyRefunded = (bool) ($charge->refunded ?? false);
        }

        $participants[] = [
            'payment_type'    => 'prepay',   // 事前決済
            'session_id'      => $session->id,
            'payment_intent'  => $piId,
            'customer_id'     => is_string($session->customer) ? $session->customer : ($session->customer->id ?? ''),
            'name'            => $name,
            'email'           => $session->customer_details->email ?? '',
            'phone'           => $phone,
            'party_size'      => $partySize,
            'note'            => $note,
            'amount'          => (int) ($session->amount_total ?? 0),
            'currency'        => (string) ($session->currency ?? 'jpy'),
            'amount_refunded' => $amountRefunded,
            'fully_refunded'  => $fullyRefunded,
            'collected'       => false, // 事前決済では使わない（当日支払い用）
            'created'         => (int) ($session->created ?? 0),
        ];
    }

    // 当日支払いの申込者は「課金なしの Stripe 顧客（metadata.payment_type=onsite）」として記録される。
    // これらを名簿に合流させる（未収として表示）。
    foreach (\Stripe\Customer::all(['limit' => 100])->autoPagingIterator() as $customer) {
        $meta = $customer->metadata ?? null;
        if (($meta['event_id'] ?? null) !== $eventId) {
            continue;
        }
        if (($meta['payment_type'] ?? '') !== 'onsite') {
            continue;
        }

        $participants[] = [
            'payment_type'    => 'onsite',   // 当日支払い
            'session_id'      => '',
            'payment_intent'  => '',
            'customer_id'     => $customer->id,
            'name'            => $meta['participant_name'] ?? ($customer->name ?? ''),
            'email'           => $customer->email ?? '',
            'phone'           => $meta['phone'] ?? ($customer->phone ?? ''),
            'party_size'      => max(1, (int) ($meta['party_size'] ?? 1)),
            'note'            => $meta['note'] ?? '',
            'amount'          => (int) ($meta['onsite_total'] ?? 0),
            'currency'        => (string) ($meta['currency'] ?? 'jpy'),
            'amount_refunded' => 0,
            'fully_refunded'  => false,
            'collected'       => (($meta['collected'] ?? '') === '1'), // 当日分の集金確認済みか
            'created'         => (int) ($customer->created ?? 0),
        ];
    }

    // 申込日時の新しい順
    usort($participants, static fn ($a, $b) => $b['created'] <=> $a['created']);

    return $participants;
}
