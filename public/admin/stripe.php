<?php

/**
 * Stripe 設定（ログイン中テナント専用）。
 * 主催者が自分の Stripe 鍵を画面から登録・接続テスト・削除する。
 * 鍵は DB ではなく「公開フォルダ外のファイル」に保存する（APP_KEY があれば暗号化も併用）。
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
            // 空で保存＝削除
            set_tenant_stripe_key($tenant['id'], null);
            $msg = 'Stripe 鍵を削除しました。';
            $tenant = find_tenant_by_id($tenant['id']);
        } elseif (!preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $key)) {
            $msg = '鍵の形式が正しくありません（sk_… または rk_… で始まる文字列）。';
            $msgType = 'ng';
        } else {
            // 形式OKなら保存。接続テストは結果通知のみ（失敗してもネットワーク要因がありうるため保存は維持）。
            set_tenant_stripe_key($tenant['id'], $key);
            [$okTest, $detail] = stripe_test_key($key);
            $msg = 'Stripe 鍵を保存しました。' . $detail;
            $msgType = $okTest ? 'ok' : 'ng';
            $tenant = find_tenant_by_id($tenant['id']);
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
        $msg = '保存した鍵を削除しました。';
        $tenant = find_tenant_by_id($tenant['id']);
    }
}

// 現在の状態（マスク表示。全体は出さない）
$hasKey = tenant_has_stripe_key($tenant);
$masked = '';
$modeLabel = '';
if ($hasKey) {
    $plain = (string) get_tenant_stripe_key($tenant);
    if ($plain !== '') {
        $masked = (strlen($plain) > 12 ? substr($plain, 0, 8) . '••••••••' . substr($plain, -4) : '（登録済み）');
        $modeLabel = str_contains($plain, '_live_') ? '本番キー（live）' : (str_contains($plain, '_test_') ? 'テストキー（test）' : '');
    } else {
        $masked = '（登録済み・復号不可：APP_KEY を確認）';
    }
}

$token = csrf_token();
$pageTitle = 'Stripe 設定';
$pageSub = 'クレジットカード決済（事前決済）に使う鍵を設定します';
require __DIR__ . '/_app_header.php';
?>
<?php if ($msg !== ''): ?>
    <div class="flash <?= $msgType === 'ok' ? 'flash--ok' : 'flash--ng' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__title">APIキーの取得・登録</div>
    <details>
        <summary>事前決済について</summary>
        <p class="muted">参加費を <strong>あなた自身の Stripe アカウント</strong> へ直接入金するため、ご自身の API キーを登録します。カード情報は Stripe 上で入力され、当サービスは保持しません。</p>
    </details>
    <p>Stripe にログイン →「開発者」→「APIキー」を開く（下のボタン）。</p>
    <ul class="muted" style="line-height:1.9;">
        <li>まずは標準のシークレットキー（<code>sk_test_…</code>）を使うのが簡単です。本番は <code>sk_live_…</code> に差し替え。</li>
        <li><strong>制限付きキー（Restricted key）</strong>を使う場合は、次の権限だけを設定してください：
            <ul>
                <li><strong>Core</strong>: Charges and Refunds … 書き込み ／ Customers … 書き込み ／ Payment Intents … 読み取り</li>
                <li><strong>Accounts</strong>: Accounts（Basic Business Contact Information）… 読み取り</li>
                <li><strong>Checkout Sessions</strong>: Checkout Sessions … 書き込み</li>
            </ul>
            上記以外はすべて「なし」でOK。
        </li>
    </ul>
    <p>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">テスト用APIキーを開く</a>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">本番用APIキーを開く</a>
        <a class="btn btn--ghost" href="https://stripe.com/docs/keys#create-restricted-api-secret-key" target="_blank" rel="noopener">制限付きキーの作り方（詳細手順）</a>
    </p>

    <form method="post" style="margin-top:18px;">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save">
        <label>Stripe 秘密鍵（<code>sk_…</code> または 制限付き <code>rk_…</code>）</label>
        <input type="password" name="stripe_key" autocomplete="off" placeholder="sk_test_xxxxx">
        <p class="hint">※ 鍵は DB には保存せず、<strong>公開フォルダ外のファイル</strong>にのみ保存します（APP_KEY があれば暗号化も併用）。空で保存すると削除します。</p>
        <p style="margin-top:14px;">
            <button type="submit" class="btn">保存する</button>
        </p>
    </form>
    <?php if ($hasKey): ?>
        <form method="post" style="display:inline-block; margin-right:8px;">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="test">
            <button type="submit" class="btn btn--ghost">接続確認</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card__title">対応するお支払い方法（PayPay・コンビニ 等）</div>
    <p class="muted">決済画面（Stripe Checkout）には、Stripe 側で有効にしたお支払い方法が自動で表示されます（このアプリ側の追加設定・コード変更は不要です）。</p>
    <ul class="muted" style="line-height:1.9;">
        <li>クレジットカード／Apple Pay／Google Pay：対応端末・ブラウザなら自動表示（基本的に追加設定は不要）。</li>
        <li>PayPay／コンビニ払い／銀行振込など：使うには Stripe ダッシュボードでの<strong>有効化</strong>が必要です（未有効だと決済画面に出ません）。</li>
    </ul>
    <details>
        <summary>PayPay を有効にする手順</summary>
        <ol class="muted" style="line-height:1.9;">
            <li>下のボタンから Stripe の「設定 → 支払い方法」を開く。</li>
            <li>一覧から <strong>PayPay</strong> を探して「有効にする」（通貨が日本円・日本のアカウントが条件）。</li>
            <li>必要に応じて「コンビニ決済（Konbini）」なども同様に有効化。</li>
            <li>有効化すると、このアプリの決済画面に自動で表示されます（再設定不要）。</li>
        </ol>
    </details>
    <p>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/test/settings/payment_methods" target="_blank" rel="noopener">支払い方法（テスト）を開く</a>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/settings/payment_methods" target="_blank" rel="noopener">支払い方法（本番）を開く</a>
    </p>
    <p class="hint">※ PayPay はテストモードでも有効化でき、テスト決済を試せます。利用可否は Stripe 側の対応条件（国・通貨・審査状況）により異なります。</p>
</div>

<div class="card">
    <div class="card__title">テスト用カード番号（テストモード時）</div>
    <p class="muted">テストキー（<code>sk_test_…</code>／<code>rk_test_…</code>）のときは、次の番号で動作確認できます。実際の請求は発生しません。</p>
    <ul class="muted" style="line-height:1.9;">
        <li>成功（Visa）：<code>4242 4242 4242 4242</code></li>
        <li>成功（Mastercard）：<code>5555 5555 5555 4444</code> ／（JCB）<code>3530 1113 3330 0000</code> ／（Amex）<code>3782 822463 10005</code></li>
        <li>有効期限：未来の日付なら何でも（例 12/34）／ CVC：任意の3桁（Amexは4桁）／ 郵便番号：任意</li>
        <li>失敗をテスト：<code>4000 0000 0000 0002</code>（拒否）／ <code>4000 0000 0000 9995</code>（残高不足）</li>
    </ul>
    <p class="hint">※ 本番（live）モードではテストカードは使えません。詳細：
        <a href="https://stripe.com/docs/testing" target="_blank" rel="noopener">Stripe のテスト情報</a></p>
</div>

<div class="card">
    <div class="card__title">現在の状態</div>
    <?php if ($hasKey): ?>
        <p>✅ 設定済み：<code><?= e($masked) ?></code><?= $modeLabel !== '' ? '　' . e($modeLabel) : '' ?></p>
        <form method="post" style="display:inline-block; margin-right:8px;">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="test">
            <button type="submit" class="btn btn--ghost">接続テスト</button>
        </form>
        <form method="post" style="display:inline-block;" data-confirm="保存した鍵を削除します。よろしいですか？（削除後は事前決済を受け付けられません）">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn--danger">鍵の削除</button>
        </form>
    <?php else: ?>
        <p class="muted">まだ登録されていません。</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_app_footer.php'; ?>
