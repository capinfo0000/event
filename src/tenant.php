<?php

/**
 * 主催者（テナント）アカウント・招待・ログインのヘルパー。
 * 認証はサーバーサイドのセッションで行う（管理画面の Basic 認証を置き換える）。
 */

declare(strict_types=1);

/** セッションを開始（未開始なら）。 */
function session_boot(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/* ------------------------- テナント ------------------------- */

function generate_tenant_id(): string
{
    return 'tn_' . bin2hex(random_bytes(6));
}

function find_tenant_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM tenants WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_tenant_by_id(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM tenants WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * テナントを作成する。email 重複時は例外。
 */
function create_tenant(string $email, string $password, string $displayName, bool $isAdmin = false): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('メールアドレスの形式が正しくありません。');
    }
    if (strlen($password) < 8) {
        throw new \InvalidArgumentException('パスワードは8文字以上にしてください。');
    }
    if (find_tenant_by_email($email) !== null) {
        throw new \RuntimeException('このメールアドレスは既に登録されています。');
    }

    $id = generate_tenant_id();
    $stmt = db()->prepare(
        'INSERT INTO tenants (id, email, password_hash, display_name, is_admin, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $displayName !== '' ? $displayName : $email,
        $isAdmin ? 1 : 0,
        time(),
    ]);
    return $id;
}

function set_tenant_stripe_account(string $tenantId, ?string $accountId): void
{
    $stmt = db()->prepare('UPDATE tenants SET stripe_account_id = ? WHERE id = ?');
    $stmt->execute([$accountId, $tenantId]);
}

function set_tenant_plan(string $tenantId, string $plan): void
{
    $stmt = db()->prepare('UPDATE tenants SET plan = ? WHERE id = ?');
    $stmt->execute([$plan, $tenantId]);
}

function set_tenant_billing_customer(string $tenantId, string $customerId): void
{
    $stmt = db()->prepare('UPDATE tenants SET stripe_customer_id = ? WHERE id = ?');
    $stmt->execute([$customerId, $tenantId]);
}

function find_tenant_by_billing_customer(string $customerId): ?array
{
    $stmt = db()->prepare('SELECT * FROM tenants WHERE stripe_customer_id = ?');
    $stmt->execute([$customerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* ------------------------- ログイン ------------------------- */

/**
 * メール＋パスワードでログイン。成功でセッションに保存し true。
 */
function login_tenant(string $email, string $password): bool
{
    $tenant = find_tenant_by_email($email);
    if ($tenant === null || !password_verify($password, $tenant['password_hash'])) {
        return false;
    }
    session_boot();
    session_regenerate_id(true);
    $_SESSION['tenant_id'] = $tenant['id'];
    return true;
}

function logout_tenant(): void
{
    session_boot();
    $_SESSION = [];
    session_destroy();
}

/** 現在ログイン中のテナント（未ログインなら null）。 */
function current_tenant(): ?array
{
    session_boot();
    $id = $_SESSION['tenant_id'] ?? '';
    if ($id === '') {
        return null;
    }
    return find_tenant_by_id($id);
}

/** ログイン必須。未ログインならログイン画面へリダイレクト。 */
function require_tenant(): array
{
    $tenant = current_tenant();
    if ($tenant === null) {
        header('Location: login.php');
        exit;
    }
    return $tenant;
}

/** プラットフォーム管理者必須。 */
function require_admin_tenant(): array
{
    $tenant = require_tenant();
    if ((int) ($tenant['is_admin'] ?? 0) !== 1) {
        http_response_code(403);
        exit('この操作にはプラットフォーム管理者権限が必要です。');
    }
    return $tenant;
}

/* ------------------------- 招待 ------------------------- */

function generate_invite_code(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * 招待コードを発行する。
 */
function create_invite(string $createdBy, ?string $email = null, ?int $ttlDays = 14): string
{
    $code = generate_invite_code();
    $expires = $ttlDays !== null ? time() + $ttlDays * 86400 : null;
    $stmt = db()->prepare(
        'INSERT INTO invites (code, email, created_by, expires_at, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$code, $email ? strtolower(trim($email)) : null, $createdBy, $expires, time()]);
    return $code;
}

/**
 * 有効な（未使用・期限内の）招待を返す。無効なら null。
 */
function find_valid_invite(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM invites WHERE code = ?');
    $stmt->execute([$code]);
    $invite = $stmt->fetch();
    if (!$invite) {
        return null;
    }
    if ($invite['used_by'] !== null) {
        return null;
    }
    if ($invite['expires_at'] !== null && (int) $invite['expires_at'] < time()) {
        return null;
    }
    return $invite;
}

function consume_invite(string $code, string $tenantId): void
{
    $stmt = db()->prepare('UPDATE invites SET used_by = ? WHERE code = ? AND used_by IS NULL');
    $stmt->execute([$tenantId, $code]);
}
