<?php

/**
 * Stripe 設定（ログイン中テナント専用）。
 * 主催者が「自分の Stripe シークレット/制限付きキー」を画面から登録・テスト・解除する。
 * 鍵は AES-256-GCM で暗号化して保存（src/crypto.php）。画面には全体を再表示しない（マスクのみ）。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$msg = '';
$msgType = 'ok';

/** 鍵を Stripe で疎通確認する（Balance 取得）。成功で [true, 説明]。 */
function stripe_test_key(string $key): array
{
    try {
        \Stripe\Stripe::setApiKey($key);
        $bal = \Stripe\Balance::retrieve();
        $live = ($bal->livemode ?? false) ? '本番(live)' : 'テスト(test)';
        return [true, "接続成功（{$live} モード）"];
    } catch (\Throwable $e) {
        return [false, '接続失敗: ' . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $key = trim((string) ($_POST['stripe_key'] ?? ''));
        if ($key === '') {
            $msg = '鍵が入力されていません。';
            $msgType = 'ng';
        } elseif (!preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $key)) {
            $msg = '鍵の形式が正しくありません（sk_… または rk_… で始まる文字列）。';
            $msgType = 'ng';
        } elseif (!crypto_available()) {
            $msg = 'サーバー側の APP_KEY が未設定のため、鍵を安全に保存できません。管理者にご連絡ください。';
            $msgType = 'ng';
        } else {
            [$okTest, $detail] = stripe_test_key($key);
            if (!$okTest) {
                $msg = '登録を中止しました（' . $detail . '）。鍵をご確認ください。';
                $msgType = 'ng';
            } else {
                set_tenant_stripe_key($tenant['id'], $key);
                $msg = 'Stripe キーを登録しました。' . $detail;
                $msgType = 'ok';
                $tenant = find_tenant_by_id($tenant['id']);
            }
        }
    } elseif ($action === 'test') {
        $key = get_tenant_stripe_key($tenant);
        if ($key === null) {
            $msg = '登録済みの鍵がありません。';
            $msgType = 'ng';
        } else {
            [$okTest, $detail] = stripe_test_key($key);
            $msg = $detail;
            $msgType = $okTest ? 'ok' : 'ng';
        }
    } elseif ($action === 'clear') {
        set_tenant_stripe_key($tenant['id'], null);
        $msg = 'Stripe キーの登録を解除しました。';
        $tenant = find_tenant_by_id($tenant['id']);
    }
}

// 表示用のマスク（先頭種別＋末尾4文字のみ）。全体は決して表示しない。
$masked = '';
$hasKey = tenant_has_stripe_key($tenant);
if ($hasKey) {
    $plain = get_tenant_stripe_key($tenant);
    if ($plain !== null && strlen($plain) > 8) {
        $masked = substr($plain, 0, 8) . '…' . substr($plain, -4);
    } else {
        $masked = '（登録済み）';
    }
}

$token = csrf_token();
$pageTitle = 'Stripe 設定';
$pageSub = '参加費を受け取る Stripe アカウントの鍵を登録します';
require __DIR__ . '/_app_header.php';
?>
<?php if ($msg !== ''): ?>
    <div class="flash <?= $msgType === 'ok' ? 'flash--ok' : 'flash--ng' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<?php if (!crypto_available()): ?>
    <div class="flash flash--ng">⚠️ サーバーの <code>APP_KEY</code> が未設定です。鍵を暗号化保存できないため、キー登録は無効化されています（<code>.env</code> に <code>APP_KEY</code>＝base64の32バイトを設定してください）。</div>
<?php endif; ?>

<div class="card">
    <div class="card__title">Stripe API キー</div>
    <?php if ($hasKey): ?>
        <p>✅ 登録済み: <code><?= e($masked) ?></code></p>
        <form method="post" style="display:inline-block; margin-right:8px;">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="test">
            <button type="submit" class="btn btn--ghost">接続テスト</button>
        </form>
        <form method="post" style="display:inline-block;" data-confirm="Stripe キーの登録を解除します。よろしいですか？（解除後は事前決済を受け付けられません）">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn--danger">解除</button>
        </form>
    <?php else: ?>
        <p class="muted">まだ登録されていません。</p>
    <?php endif; ?>

    <form method="post" style="margin-top:18px;">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save">
        <label><?= $hasKey ? '鍵を更新する' : '鍵を登録する' ?>（<code>sk_…</code> または <strong>制限付きキー <code>rk_…</code>（推奨）</strong>）</label>
        <input type="password" name="stripe_key" autocomplete="off" placeholder="rk_live_… または sk_live_…">
        <p class="hint">入力後、自動で接続テストを行ってから保存します。保存後は画面に全体を表示しません（末尾のみ表示）。</p>
        <p style="margin-top:14px;"><button type="submit" class="btn"><?= $hasKey ? '更新する' : '登録する' ?></button></p>
    </form>
</div>

<div class="card">
    <div class="card__title">ヒント</div>
    <ul class="muted" style="line-height:1.9;">
        <li><strong>制限付きキー(rk_)</strong>を推奨します。Stripe ダッシュボード → 開発者 → API キー → 「制限付きキーを作成」で、Checkout / PaymentIntents / Customers / Refunds / Balance を「書き込み」許可にして発行してください。漏洩時の被害を最小化できます。</li>
        <li>カード以外（<strong>PayPay・コンビニ決済</strong>等）は、Stripe ダッシュボードの「設定 → 支払い方法」で有効化すると Checkout に自動で表示されます（地域・審査の条件あり）。</li>
        <li>テスト決済は test モードの鍵＋テストカード <code>4242 4242 4242 4242</code>（有効期限=未来・CVC=任意3桁）で確認できます。</li>
        <li>参加費は<strong>あなたの Stripe アカウントへ直接入金</strong>されます。カード情報は Stripe 上で入力され、当サービスは保持しません。</li>
    </ul>
</div>
<?php require __DIR__ . '/_app_footer.php'; ?>
