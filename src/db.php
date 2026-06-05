<?php

/**
 * SQLite データ層。
 *
 * マルチテナント化に伴い、主催者アカウント・招待・イベントを永続化する。
 * （参加者の決済データは引き続き各テナントの Stripe が正。ここには保存しない。）
 *
 * DB ファイルは Web 公開領域の外（プロジェクト直下の data/）に置く。
 */

declare(strict_types=1);

/**
 * PDO(SQLite) のシングルトン。初回アクセス時にスキーマを作成する。
 */
function db(): \PDO
{
    static $pdo = null;
    if ($pdo instanceof \PDO) {
        return $pdo;
    }

    $dir = APP_ROOT . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $path = env('DB_PATH', $dir . '/app.sqlite');

    $pdo = new \PDO('sqlite:' . $path, null, null, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA foreign_keys = ON;');

    db_migrate($pdo);

    return $pdo;
}

/**
 * スキーマ作成（冪等）。
 */
function db_migrate(\PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS tenants (
            id                TEXT PRIMARY KEY,
            email             TEXT NOT NULL UNIQUE,
            password_hash     TEXT NOT NULL,
            display_name      TEXT NOT NULL DEFAULT '',
            stripe_account_id TEXT,                       -- Connect で紐付く acct_...（未連携なら NULL）
            is_admin          INTEGER NOT NULL DEFAULT 0, -- プラットフォーム管理者（招待を発行できる）
            created_at        INTEGER NOT NULL
        );
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS invites (
            code       TEXT PRIMARY KEY,
            email      TEXT,            -- 招待先を限定したい場合（任意）
            created_by TEXT,            -- 発行した管理者 tenant.id
            used_by    TEXT,            -- 使用した tenant.id（未使用なら NULL）
            expires_at INTEGER,         -- 有効期限（NULL なら無期限）
            created_at INTEGER NOT NULL
        );
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS events (
            id            TEXT PRIMARY KEY,
            tenant_id     TEXT NOT NULL,
            name          TEXT NOT NULL,
            description   TEXT NOT NULL DEFAULT '',
            date          TEXT NOT NULL DEFAULT '',
            place         TEXT NOT NULL DEFAULT '',
            amount        INTEGER NOT NULL DEFAULT 0,   -- 事前決済の単価（最小通貨単位）
            amount_onsite INTEGER NOT NULL DEFAULT 0,   -- 当日支払いの単価
            currency      TEXT NOT NULL DEFAULT 'jpy',
            capacity      INTEGER NOT NULL DEFAULT 0,
            allow_prepay  INTEGER NOT NULL DEFAULT 1,
            allow_onsite  INTEGER NOT NULL DEFAULT 0,
            created_at    INTEGER NOT NULL,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        );
    SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_tenant ON events(tenant_id);');
}
