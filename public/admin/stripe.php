<?php

/**
 * Stripe 設定画面。
 * 運営者が自分で発行した Stripe の秘密鍵（できれば「制限付きキー」）を貼り付けて保存する。
 * 鍵は DB には保存せず、公開フォルダ外のファイル（app/stripe_secret.key）にのみ保存する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

$msg = '';
$msgType = 'ok';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $key = trim((string) ($_POST['stripe_key'] ?? ''));
        // ざっくり形式チェック（sk_ または rk_ で始まる）
        if ($key !== '' && !preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $key)) {
            $msg = '鍵の形式が正しくないようです（sk_test_… / sk_live_… / rk_… で始まります）。';
            $msgType = 'ng';
        } else {
            save_stripe_key($key);
            $msg = $key === '' ? '鍵を削除しました。' : '鍵を保存しました。「接続テスト」で確認してください。';
        }
    } elseif ($action === 'clear') {
        save_stripe_key('');
        $msg = '鍵を削除しました。';
    } elseif ($action === 'test') {
        try {
            init_stripe(); // 保存済みの鍵を使用
            // 権限確認を兼ねた軽い呼び出し（Checkout Sessions の読み取りで疎通確認）
            \Stripe\Checkout\Session::all(['limit' => 1]);
            $k = (string) stored_stripe_key();
            $testResult = [
                'ok' => true,
                'mode' => str_contains($k, '_live_') ? '本番（live）' : 'テスト（test）',
            ];
        } catch (\Throwable $e) {
            $testResult = ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

$key = stored_stripe_key();
$configured = $key !== null;
$masked = $configured ? (substr($key, 0, 8) . '••••••••' . substr($key, -4)) : '';
$isLive = $configured && str_contains($key, '_live_');

$token = csrf_token();
$pageTitle = 'Stripe 設定';
$pageSub = 'クレジットカード決済（事前決済）に使う鍵を設定します';
require __DIR__ . '/_app_header.php';
?>
<?php if ($msg !== ''): ?>
    <div class="flash <?= $msgType === 'ok' ? 'flash--ok' : 'flash--ng' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($testResult !== null): ?>
    <?php if ($testResult['ok']): ?>
        <div class="flash flash--ok">✅ 接続成功（<?= e($testResult['mode']) ?>）／ アカウント：<?= e((string) $testResult['name']) ?> <?= $testResult['country'] !== '' ? '（' . e($testResult['country']) . '）' : '' ?></div>
    <?php else: ?>
        <div class="flash flash--ng">❌ 接続できませんでした：<?= e($testResult['error']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<div class="card">
    <div class="card__title">APIキーの取得・登録</div>
    <ol class="muted" style="margin-top:0;">
        <li>Stripeにログイン →「開発者」→「APIキー」を開く（下のボタン）。</li>
        <li>まずは<strong>標準のシークレットキー（sk_test_…）</strong>を使うのが簡単です。本番は <strong>sk_live_…</strong> に差し替え。</li>
        <li><strong>制限付きキー（Restricted key）</strong>を使う場合は、次の権限だけを設定してください：
            <ul style="margin:6px 0 0;">
                <li><strong>Charges and Refunds</strong> … 書き込み</li>
                <li><strong>Checkout Sessions</strong> … 書き込み</li>
                <li><strong>Customers</strong> … 書き込み</li>
                <li><strong>Payment Intents</strong> … 読み取り</li>
                <li><strong>Accounts</strong>（Basic Business Contact Information）… 読み取り</li>
            </ul>
            <span style="display:inline-block;margin-top:4px;">上記以外はすべて「なし」でOK。</span>
        </li>
    </ol>
    <p>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">テスト用APIキーを開く</a>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener" style="margin-left:8px;">本番用APIキーを開く</a>
    </p>

    <form method="post" style="margin-top:16px;">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save">
        <label>Stripe 秘密鍵（sk_… または 制限付き rk_…）</label>
        <input type="password" name="stripe_key" autocomplete="off" placeholder="sk_test_xxxxx" value="">
        <p class="muted" style="margin:6px 0 0;">※ 鍵は DB には保存せず、公開フォルダ外のファイルにのみ保存します。空で保存すると削除します。</p>
        <p style="margin-top:14px;"><button type="submit" class="btn">保存する</button></p>
    </form>
</div>

<div class="card">
    <div class="card__title">対応するお支払い方法（PayPay・コンビニ 等）</div>
    <p style="margin-top:0;">決済画面（Stripe Checkout）には、<strong>Stripe 側で有効にしたお支払い方法が自動で表示</strong>されます（このアプリ側の追加設定・コード変更は不要です）。</p>
    <ul class="muted" style="margin:6px 0 0;">
        <li><strong>クレジットカード／Apple Pay／Google Pay</strong>：対応端末・ブラウザなら決済画面に自動表示（基本的に追加設定は不要）。</li>
        <li><strong>PayPay／コンビニ払い／銀行振込</strong>など：使うには Stripe ダッシュボードでの<strong>有効化が必要</strong>です（未有効だと決済画面に出ません）。</li>
    </ul>

    <p style="margin:14px 0 4px;"><strong>PayPay を有効にする手順</strong></p>
    <ol class="muted" style="margin-top:0;">
        <li>下のボタンから Stripe の「設定 → 支払い方法」を開く。</li>
        <li>一覧から <strong>PayPay</strong> を探して「有効にする」を押す（通貨が日本円・日本のアカウントであることが条件）。</li>
        <li>必要に応じて「コンビニ決済（Konbini）」なども同様に有効化。</li>
        <li>有効化すると、このアプリの決済画面に自動で表示されます（再設定不要）。</li>
    </ol>
    <p>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/test/settings/payment_methods" target="_blank" rel="noopener">支払い方法（テスト）を開く</a>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/settings/payment_methods" target="_blank" rel="noopener" style="margin-left:8px;">支払い方法（本番）を開く</a>
    </p>
    <p class="muted" style="font-size:.82rem;">※ PayPay はテストモードでも有効化でき、テスト決済を試せます。利用可否は Stripe 側の対応条件（国・通貨・審査状況）により異なります。</p>
</div>

<div class="card">
    <div class="card__title">現在の状態</div>
    <p style="margin-top:0;"><?php include dirname(__DIR__) . '/_stripe_safety.php'; ?></p>
    <?php if ($configured): ?>
        <p>設定済み：<code><?= e($masked) ?></code>　<?= $isLive ? '<strong style="color:#b91c1c;">本番キー（live）</strong>' : 'テストキー（test）' ?></p>
        <form method="post" style="margin-top:10px;">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="test">
            <button type="submit" class="btn">接続テスト</button>
        </form>
    <?php else: ?>
        <p>⚠️ 未設定です。下のフォームに Stripe の秘密鍵を貼り付けて保存してください。未設定の間は、カード決済はもちろん、<strong>当日支払いの申込受付・参加者管理（名簿）も利用できません</strong>（いずれも Stripe に記録・取得するため）。</p>
    <?php endif; ?>
</div>

<?php if ($configured): ?>
<div class="card">
    <div class="card__title">鍵の削除</div>
    <form method="post" onsubmit="return confirm('保存した鍵を削除します。カード決済は使えなくなります。よろしいですか？');">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="clear">
        <button type="submit" class="btn btn--ghost">保存した鍵を削除</button>
    </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/_app_footer.php'; ?>
