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
require __DIR__ . '/mail.php';

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
 * DB の行を、画面・決済処理が期待する形に正規化する（型変換つき）。
 * 'stripe_account_id' は所有テナントの接続アカウント（公開申込で利用）。
 */
function event_normalize(array $row): array
{
    return [
        'id'                => (string) $row['id'],
        'tenant_id'         => (string) $row['tenant_id'],
        'name'              => (string) $row['name'],
        'description'       => (string) ($row['description'] ?? ''),
        'date'              => (string) ($row['date'] ?? ''),
        'place'             => (string) ($row['place'] ?? ''),
        'amount'            => (int) ($row['amount'] ?? 0),
        'amount_onsite'     => (int) ($row['amount_onsite'] ?? 0),
        'currency'          => (string) ($row['currency'] ?? 'jpy'),
        'capacity'          => (int) ($row['capacity'] ?? 0),
        'allow_prepay'      => (int) ($row['allow_prepay'] ?? 1) === 1,
        'allow_onsite'      => (int) ($row['allow_onsite'] ?? 0) === 1,
        'stripe_account_id' => $row['stripe_account_id'] ?? null,
        'created_at'        => (int) ($row['created_at'] ?? 0),
    ];
}

/**
 * イベントを ID で取得（所有テナントの Stripe 接続アカウントも併せて取得）。
 * 公開申込ページなど、ログイン不要の文脈からも使う。
 */
function find_event(string $id): ?array
{
    $stmt = db()->prepare(
        'SELECT e.*, t.stripe_account_id
           FROM events e JOIN tenants t ON t.id = e.tenant_id
          WHERE e.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? event_normalize($row) : null;
}

/**
 * 指定テナントのイベント一覧（新しい順）。
 */
function tenant_events(string $tenantId): array
{
    $stmt = db()->prepare(
        'SELECT e.*, t.stripe_account_id
           FROM events e JOIN tenants t ON t.id = e.tenant_id
          WHERE e.tenant_id = ? ORDER BY e.created_at DESC'
    );
    $stmt->execute([$tenantId]);
    return array_map('event_normalize', $stmt->fetchAll());
}

/** 重複しないイベントIDを生成する。 */
function generate_event_id(): string
{
    do {
        $id = 'ev_' . bin2hex(random_bytes(6));
    } while (find_event($id) !== null);
    return $id;
}

/**
 * イベントを作成して ID を返す（所有テナントを指定）。
 * @param array<string,mixed> $d 正規化済みの値（amount 等は整数、allow_* は bool）
 */
function create_event(string $tenantId, array $d): string
{
    $id = generate_event_id();
    $stmt = db()->prepare(
        'INSERT INTO events (id, tenant_id, name, description, date, place, amount, amount_onsite, currency, capacity, allow_prepay, allow_onsite, created_at)
         VALUES (:id,:tenant,:name,:desc,:date,:place,:amount,:onsite,:cur,:cap,:ap,:ao,:ts)'
    );
    $stmt->execute([
        ':id' => $id, ':tenant' => $tenantId,
        ':name' => $d['name'], ':desc' => $d['description'], ':date' => $d['date'], ':place' => $d['place'],
        ':amount' => $d['amount'], ':onsite' => $d['amount_onsite'], ':cur' => $d['currency'], ':cap' => $d['capacity'],
        ':ap' => $d['allow_prepay'] ? 1 : 0, ':ao' => $d['allow_onsite'] ? 1 : 0, ':ts' => time(),
    ]);
    return $id;
}

/**
 * イベントを更新（所有テナントに限定）。更新できたら true。
 */
function update_event(string $tenantId, string $id, array $d): bool
{
    $stmt = db()->prepare(
        'UPDATE events SET name=:name, description=:desc, date=:date, place=:place,
                amount=:amount, amount_onsite=:onsite, currency=:cur, capacity=:cap,
                allow_prepay=:ap, allow_onsite=:ao
          WHERE id=:id AND tenant_id=:tenant'
    );
    $stmt->execute([
        ':name' => $d['name'], ':desc' => $d['description'], ':date' => $d['date'], ':place' => $d['place'],
        ':amount' => $d['amount'], ':onsite' => $d['amount_onsite'], ':cur' => $d['currency'], ':cap' => $d['capacity'],
        ':ap' => $d['allow_prepay'] ? 1 : 0, ':ao' => $d['allow_onsite'] ? 1 : 0,
        ':id' => $id, ':tenant' => $tenantId,
    ]);
    return $stmt->rowCount() > 0;
}

/** イベントを削除（所有テナントに限定）。 */
function delete_event(string $tenantId, string $id): bool
{
    $stmt = db()->prepare('DELETE FROM events WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$id, $tenantId]);
    return $stmt->rowCount() > 0;
}

/**
 * 料金プランの定義。max_events は「同じ開催月に登録できるイベント数」の上限。
 * price は月額（最小通貨単位・JPY）。実際の課金連携は別途。
 *
 * @return array<string, array{label:string, max_events:int, price:int}>
 */
function plan_catalog(): array
{
    return [
        'free'      => ['label' => '無料',         'max_events' => 1,           'price' => 0],
        'p5'        => ['label' => '月5イベント',   'max_events' => 5,           'price' => 500],
        'p10'       => ['label' => '月10イベント',  'max_events' => 10,          'price' => 1000],
        'unlimited' => ['label' => '無制限',        'max_events' => PHP_INT_MAX, 'price' => 1500],
    ];
}

/** プランが同じ開催月に登録できるイベント数。未知のプランは無料相当(1)。 */
function plan_max_events(string $plan): int
{
    return plan_catalog()[$plan]['max_events'] ?? 1;
}

/** プランの表示名。 */
function plan_label(string $plan): string
{
    return plan_catalog()[$plan]['label'] ?? $plan;
}

/**
 * 各有料プランに対応する Stripe Price ID（.env で設定）。
 * 未設定のプランは課金導線に出さない。料金は Stripe 側の Price が正。
 *
 * @return array<string,string> plan => price_id
 */
function plan_price_ids(): array
{
    $map = [
        'p5'        => env('STRIPE_PRICE_P5'),
        'p10'       => env('STRIPE_PRICE_P10'),
        'unlimited' => env('STRIPE_PRICE_UNLIMITED'),
    ];
    return array_filter($map, static fn ($v) => $v !== null && $v !== '');
}

/** Stripe Price ID から内部プラン名を引く（Webhook で使用）。無ければ null。 */
function plan_for_price_id(string $priceId): ?string
{
    foreach (plan_price_ids() as $plan => $pid) {
        if ($pid === $priceId) {
            return $plan;
        }
    }
    return null;
}

/** テナントの登録済みイベント総数（表示用）。 */
function tenant_event_count(string $tenantId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM events WHERE tenant_id = ?');
    $stmt->execute([$tenantId]);
    return (int) $stmt->fetchColumn();
}

/**
 * イベントの「開催月」を 'YYYY-MM' 形式で返す。日付文字列から年月を抽出。
 * 判定できなければ null（プランの月内上限はイベント開催月で数えるため必要）。
 */
function event_month(string $dateStr): ?string
{
    if (preg_match('/(\d{4})\D{1,3}(\d{1,2})/', $dateStr, $m)) {
        $year = (int) $m[1];
        $month = (int) $m[2];
        if ($month >= 1 && $month <= 12) {
            return sprintf('%04d-%02d', $year, $month);
        }
    }
    return null;
}

/**
 * 指定テナントが、ある開催月に登録済みのイベント数を数える（プラン上限の判定用）。
 * $excludeId を渡すと、そのイベント自身は除外する（編集時の自己重複回避）。
 */
function tenant_month_event_count(string $tenantId, string $month, string $excludeId = ''): int
{
    $n = 0;
    foreach (tenant_events($tenantId) as $e) {
        if ($e['id'] === $excludeId) {
            continue;
        }
        if (event_month($e['date']) === $month) {
            $n++;
        }
    }
    return $n;
}

/**
 * イベントの現在の参加人数（party_size 合計）。
 * 事前決済（返金済みを除く）＋当日支払いを数える。定員判定に使う。
 */
function event_headcount(string $eventId, ?string $account): int
{
    $n = 0;
    foreach (fetch_event_participants($eventId, $account) as $p) {
        if (!empty($p['fully_refunded'])) {
            continue; // 全額返金＝キャンセル扱いは定員に数えない
        }
        $n += max(1, (int) $p['party_size']);
    }
    return $n;
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
 * 保存済みの日時文字列を <input type="datetime-local"> の value 形式
 * （YYYY-MM-DDTHH:MM）に変換する。解釈できなければ空文字（＝空欄表示）。
 */
function datetime_local_value(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return '';
    }
    $ts = strtotime($date);
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
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
 * 参加者の名簿は各テナントの Stripe（Checkout セッション／顧客）から都度取得する。
 * 管理画面の認証はテナントのセッションログイン（src/tenant.php）で行う。
 * ===================================================================== */

/**
 * CSRF トークンを取得（なければ生成）。セッションに保存する。
 */
function csrf_token(): string
{
    session_boot();
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
    session_boot();
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected === '' || !is_string($token) || !hash_equals($expected, $token)) {
        http_response_code(400);
        exit('不正なリクエストです（CSRF トークン不一致）。画面を開き直してください。');
    }
}

/**
 * Connect 接続アカウント向けのリクエストオプションを返す。
 * $account が null の場合は空（プラットフォーム自身に対する操作）。
 *
 * @return array<string,string>
 */
function stripe_opts(?string $account): array
{
    return $account ? ['stripe_account' => $account] : [];
}

/**
 * 指定イベントの参加者一覧を Stripe から取得する（テナントの接続アカウント単位）。
 *
 * - 事前決済: 支払い済み Checkout セッション
 * - 当日支払い: metadata.payment_type=onsite の顧客（未収/集金済み）
 *
 * @param string      $eventId 対象イベント
 * @param string|null $account テナントの Stripe 接続アカウント（acct_...）。null なら自アカウント
 * @return array<int, array<string, mixed>>
 */
function fetch_event_participants(string $eventId, ?string $account = null): array
{
    init_stripe();
    $opts = stripe_opts($account);

    $participants = [];
    $params = [
        'limit' => 100,
        'expand' => ['data.payment_intent.latest_charge', 'data.customer'],
    ];

    foreach (\Stripe\Checkout\Session::all($params, $opts)->autoPagingIterator() as $session) {
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

        // 出席チェックは顧客の metadata.attended に保存する（事前・当日で共通）
        $customerObj = is_object($session->customer) ? $session->customer : null;
        $customerId = $customerObj ? ($customerObj->id ?? '') : (string) $session->customer;
        $attended = $customerObj ? (($customerObj->metadata['attended'] ?? '') === '1') : false;

        $participants[] = [
            'payment_type'    => 'prepay',   // 事前決済
            'session_id'      => $session->id,
            'payment_intent'  => $piId,
            'customer_id'     => $customerId,
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
            'attended'        => $attended,
            'created'         => (int) ($session->created ?? 0),
        ];
    }

    // 当日支払いの申込者は「課金なしの Stripe 顧客（metadata.payment_type=onsite）」として記録される。
    // これらを名簿に合流させる（未収として表示）。
    foreach (\Stripe\Customer::all(['limit' => 100], $opts)->autoPagingIterator() as $customer) {
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
            'collected'       => (($meta['collected'] ?? '') === '1'), // 当日分の受領（集金）済みか
            'attended'        => (($meta['attended'] ?? '') === '1'),  // 出席確認済みか
            'created'         => (int) ($customer->created ?? 0),
        ];
    }

    // 申込日時の新しい順
    usort($participants, static fn ($a, $b) => $b['created'] <=> $a['created']);

    return $participants;
}
