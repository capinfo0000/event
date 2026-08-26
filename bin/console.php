<?php

/**
 * 運用用 CLI。
 *
 * 使い方:
 *   php bin/console.php init                       … DB を作成（スキーマ初期化）
 *   php bin/console.php create-admin <email> <pw>  … プラットフォーム管理者を作成
 *   php bin/console.php make-admin <email>         … 既存アカウントを管理者に昇格
 *   php bin/console.php make-invite <admin-email>  … 招待コードを発行して表示
 *   php bin/console.php list-tenants               … テナント一覧
 *   php bin/console.php set-plan <email> <plan>    … プラン変更（free/p5/p10/unlimited）
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI からのみ実行できます。\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';

$cmd = $argv[1] ?? '';

switch ($cmd) {
    case 'init':
        db(); // 接続＝マイグレーション実行
        echo "DB を初期化しました。\n";
        break;

    case 'create-admin':
        $email = $argv[2] ?? '';
        $pw = $argv[3] ?? '';
        if ($email === '' || $pw === '') {
            exit("使い方: php bin/console.php create-admin <email> <password>\n");
        }
        try {
            $id = create_tenant($email, $pw, 'プラットフォーム管理者', true);
            echo "管理者を作成しました: {$email} (id={$id})\n";
        } catch (\Throwable $e) {
            exit('失敗: ' . $e->getMessage() . "\n");
        }
        break;

    case 'make-admin':
        $email = $argv[2] ?? '';
        $t = $email !== '' ? find_tenant_by_email($email) : null;
        if ($t === null) {
            exit("テナントが見つかりません: {$email}\n");
        }
        db()->prepare('UPDATE tenants SET is_admin = 1 WHERE id = ?')->execute([$t['id']]);
        echo "{$email} を管理者にしました。招待発行（/admin/invites.php）が使えます。\n";
        break;

    case 'revoke-sessions':
        // 全ログインセッションを失効（乗っ取り・セッション窃取が疑われる時の緊急対応）。
        $dir = dirname(current_db_path()) . '/sessions';
        $n = 0;
        if (is_dir($dir)) {
            foreach (glob($dir . '/sess_*') ?: [] as $f) {
                if (@unlink($f)) {
                    $n++;
                }
            }
        }
        echo "全ログインセッションを失効しました（{$n} 件削除）。全ユーザーが再ログインになります。\n";
        break;

    case 'rotate-app-key':
        // APP_KEY（暗号化鍵）をローテーション。現行キーで全秘密を復号→新キーで再暗号化。
        // APP_KEY 流出やサーバー侵害が疑われる時の対応。実行前に data/ のバックアップ推奨。
        if (!crypto_available()) {
            exit("現在の APP_KEY が未設定/不正です。先に正しい APP_KEY を用意してください。\n");
        }
        $rows = db()->query('SELECT * FROM tenants')->fetchAll();
        $plainKeys = [];
        $plainTotp = [];
        foreach ($rows as $t) {
            $k = get_tenant_stripe_key($t);
            if ($k !== null) {
                $plainKeys[$t['id']] = $k;
            }
            $s = tenant_totp_secret($t);
            if ($s !== null) {
                $plainTotp[$t['id']] = $s;
            }
        }
        $new = base64_encode(random_bytes(32));
        $envPath = APP_ROOT . '/.env';
        $lines = is_file($envPath) ? (file($envPath, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $found = false;
        foreach ($lines as $i => $l) {
            if (preg_match('/^\s*APP_KEY\s*=/', $l)) {
                $lines[$i] = 'APP_KEY=' . $new;
                $found = true;
            }
        }
        if (!$found) {
            $lines[] = 'APP_KEY=' . $new;
        }
        @file_put_contents($envPath, implode("\n", $lines) . "\n", LOCK_EX);
        @chmod($envPath, 0600);
        putenv('APP_KEY=' . $new);
        $_ENV['APP_KEY'] = $new;
        foreach ($plainKeys as $id => $k) {
            set_tenant_stripe_key($id, $k);
        }
        foreach ($plainTotp as $id => $s) {
            set_tenant_totp($id, $s, true);
        }
        echo 'APP_KEY をローテーションしました。Stripe鍵 ' . count($plainKeys) . ' 件・2FA ' . count($plainTotp) . " 件を再暗号化。\n";
        echo "新 APP_KEY: {$new}\n";
        echo "※ APP_KEY を実環境変数で運用している場合は、環境変数を上記の値に更新してください（.env にも書き込み済み）。\n";
        break;

    case 'disable-2fa':
        $email = $argv[2] ?? '';
        $t = $email !== '' ? find_tenant_by_email($email) : null;
        if ($t === null) {
            exit("テナントが見つかりません: {$email}\n");
        }
        set_tenant_totp($t['id'], null, false);
        echo "{$email} の2段階認証を解除しました（認証アプリ紛失時の復旧用）。\n";
        break;

    case 'make-invite':
        $adminEmail = $argv[2] ?? '';
        $admin = $adminEmail !== '' ? find_tenant_by_email($adminEmail) : null;
        if ($admin === null || (int) $admin['is_admin'] !== 1) {
            exit("管理者のメールを指定してください（先に create-admin を実行）。\n");
        }
        $code = create_invite($admin['id']);
        $base = rtrim(env('APP_BASE_URL', 'http://localhost:8000'), '/');
        echo "招待コード: {$code}\n";
        echo "サインアップURL: {$base}/admin/signup.php?invite={$code}\n";
        break;

    case 'set-plan':
        $email = $argv[2] ?? '';
        $plan = $argv[3] ?? '';
        $t = $email !== '' ? find_tenant_by_email($email) : null;
        if ($t === null) {
            exit("テナントが見つかりません: {$email}\n");
        }
        if (!isset(plan_catalog()[$plan])) {
            exit('プランは ' . implode(' / ', array_keys(plan_catalog())) . " のいずれかを指定してください。\n");
        }
        set_tenant_plan($t['id'], $plan);
        echo "プランを {$plan}（" . plan_label($plan) . "・上限 " .
             (plan_max_events($plan) === PHP_INT_MAX ? '無制限' : plan_max_events($plan) . '件') . "）に変更しました。\n";
        break;

    case 'list-tenants':
        foreach (db()->query('SELECT id, email, display_name, stripe_account_id, is_admin FROM tenants ORDER BY created_at') as $t) {
            $connected = $t['stripe_account_id'] ? $t['stripe_account_id'] : '(未連携)';
            $role = $t['is_admin'] ? '[admin]' : '';
            echo "{$t['id']}  {$t['email']}  {$connected}  {$role}\n";
        }
        break;

    default:
        echo "コマンド: init | create-admin | make-admin | make-invite | disable-2fa | revoke-sessions | rotate-app-key | list-tenants | set-plan\n";
}
