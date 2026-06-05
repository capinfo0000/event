<?php

/**
 * 主催者（テナント）アカウント・招待・ログインのヘルパー。
 * 認証はサーバーサイドのセッションで行う（管理画面の Basic 認証を置き換える）。
 */

declare(strict_types=1);

/** セッションを開始（未開始なら）。Cookie を堅牢化してから開始する。 */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // HTTPS 配信時は Secure 属性を付ける（リバースプロキシ経由も考慮）
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------- ログイン試行の制限（総当たり対策） ------------------- */

/** 直近 $windowSec 秒の失敗回数（メール単位）。 */
function recent_failed_logins(string $email, int $windowSec = 900): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at >= ?');
    $stmt->execute([strtolower(trim($email)), time() - $windowSec]);
    return (int) $stmt->fetchColumn();
}

/** 失敗を記録する。 */
function record_failed_login(string $email): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (identifier, ip, created_at) VALUES (?, ?, ?)');
    $stmt->execute([strtolower(trim($email)), $_SERVER['REMOTE_ADDR'] ?? '', time()]);
}

/** 成功時に失敗履歴をクリアする。 */
function clear_failed_logins(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    $stmt->execute([strtolower(trim($email))]);
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

/* ------------------- アカウント設定・パスワード ------------------- */

/** 表示名を更新する。 */
function update_tenant_display_name(string $tenantId, string $name): void
{
    $stmt = db()->prepare('UPDATE tenants SET display_name = ? WHERE id = ?');
    $stmt->execute([$name !== '' ? $name : '主催者', $tenantId]);
}

/** パスワードを更新する（8文字以上）。 */
function update_tenant_password(string $tenantId, string $newPassword): void
{
    if (strlen($newPassword) < 8) {
        throw new \InvalidArgumentException('パスワードは8文字以上にしてください。');
    }
    $stmt = db()->prepare('UPDATE tenants SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $tenantId]);
}

/* ------------------- パスワード再設定 ------------------- */

/**
 * パスワード再設定トークンを発行する。存在しないメールでも例外にせず null を返す
 * （アカウントの有無を外部に漏らさないため）。
 */
function create_password_reset(string $email, int $ttlSec = 3600): ?string
{
    $tenant = find_tenant_by_email($email);
    if ($tenant === null) {
        return null;
    }
    $token = bin2hex(random_bytes(32));
    $stmt = db()->prepare(
        'INSERT INTO password_resets (token, tenant_id, expires_at, created_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$token, $tenant['id'], time() + $ttlSec, time()]);
    return $token;
}

/** 有効な再設定トークンに対応するレコードを返す。無効なら null。 */
function find_valid_reset(string $token): ?array
{
    $stmt = db()->prepare('SELECT * FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['used'] === 1 || (int) $row['expires_at'] < time()) {
        return null;
    }
    return $row;
}

/** トークンを使ってパスワードを再設定する。成功で true。 */
function consume_password_reset(string $token, string $newPassword): bool
{
    $reset = find_valid_reset($token);
    if ($reset === null) {
        return false;
    }
    update_tenant_password($reset['tenant_id'], $newPassword);
    $stmt = db()->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
    $stmt->execute([$token]);
    return true;
}
