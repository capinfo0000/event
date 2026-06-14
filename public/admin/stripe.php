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

/**
 * 鍵を Stripe で疎通確認する。アプリが実際に使う「Checkout Sessions の取得」で検証するため、
 * 案内している制限付きキー権限（Checkout Sessions 書き込み）だけで通る（Balance 権限は不要）。
 * モード（test/live）は鍵の接頭辞から判定する。成功で [true, 説明]。
 */
function stripe_test_key(string $key): array
{
    try {
        \Stripe\Stripe::setApiKey($key);
        \Stripe\Checkout\Session::all(['limit' => 1]);
        $mode = str_contains($key, '_live_') ? '本番(live)' : 'テスト(test)';
        return [true, "接続成功（{$mode} モード）"];
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
            try {
                set_tenant_stripe_key($tenant['id'], $key);
                [$okTest, $detail] = stripe_test_key($key);
                $msg = 'Stripe 鍵を保存しました。' . $detail;
                $msgType = $okTest ? 'ok' : 'ng';
                $tenant = find_tenant_by_id($tenant['id']);
            } catch (\Throwable $e) {
                // 公開フォルダ内への保存拒否など
                $msg = $e->getMessage();
                $msgType = 'ng';
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
        <button type="button" class="btn btn--ghost" data-modal-open="rkGuide">制限付きキー（rk_）の作り方</button>
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
    <p>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/test/settings/payment_methods" target="_blank" rel="noopener">支払い方法（テスト）を開く</a>
        <a class="btn btn--ghost" href="https://dashboard.stripe.com/settings/payment_methods" target="_blank" rel="noopener">支払い方法（本番）を開く</a>
        <button type="button" class="btn btn--ghost" data-modal-open="paypayGuide">PayPay 等を有効にする手順（詳細）</button>
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
<!-- 制限付きキー（rk_）の作り方モーダル -->
<div class="modal" id="rkGuide" role="dialog" aria-modal="true">
    <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
    <div class="modal__box">
        <div class="modal__title">制限付きキー（rk_）の作り方</div>
        <p class="muted">権限を絞ったキーです。万一漏れても被害を限定できます。テスト環境（sandbox）でそのまま作れます。</p>

        <div class="modal__step">1. APIキー画面を開く</div>
        <p>右上の ⚙（設定） →「開発者」→「APIキーの管理」。<br>
           「制限付きのキー」の右上 <strong>＋ 制限付きのキーを作成</strong> を押す。</p>

        <div class="modal__step">2. テンプレートを選ぶ</div>
        <p><strong>「One-time payments」</strong>を選択 → 続ける。<br>
           <span class="muted">（チェックアウト/決済リンク等での支払い受付）</span></p>

        <div class="modal__step">3. キーの名前を入力</div>
        <p><code>event-app</code> など。</p>

        <div class="modal__step">4. 権限を設定（下記だけ／他は「なし」）</div>
        <ul>
            <li><strong>Core</strong>
                <ul>
                    <li>Charges and Refunds … <strong>書込</strong></li>
                    <li>Customers … <strong>書込</strong></li>
                    <li>Payment Intents … <strong>読取</strong></li>
                </ul>
            </li>
            <li><strong>Accounts</strong>
                <ul><li>Accounts … <strong>読取</strong></li></ul>
            </li>
            <li><strong>Checkout Sessions</strong>
                <ul><li>Checkout Sessions … <strong>読取/書込</strong></li></ul>
            </li>
        </ul>
        <p class="hint">※「Accounts＝読取」も入れておくと確実です。項目は Ctrl+F で検索すると速い。</p>

        <div class="modal__step">5. 作成してトークンをコピー</div>
        <p>一番下の <strong>キーを作成</strong> → 表示される <code>rk_test_…</code> の長い文字をコピー。</p>

        <div class="modal__step">6. このページに貼り付けて確認</div>
        <p>「Stripe 秘密鍵」欄に貼り付け → <strong>保存する</strong>。✅「接続成功」でOK。<br>
           <span class="muted">権限エラーが出たら、表示された権限（例：Checkout Sessions／Accounts）を追加して再確認。</span></p>

        <div class="modal__actions">
            <a class="btn" href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">APIキー画面を開く（テスト）</a>
            <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
        </div>
    </div>
</div>

<!-- PayPay 等を有効にする手順モーダル -->
<div class="modal" id="paypayGuide" role="dialog" aria-modal="true">
    <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
    <div class="modal__box">
        <div class="modal__title">PayPay・コンビニ払い等を有効にする手順</div>
        <p class="muted">決済画面には「Stripe で有効にした支払い方法」が自動で表示されます。PayPay 等は既定でオフのことがあるため、ダッシュボードで有効化します（テスト環境でも可）。</p>

        <div class="modal__step">1. 「決済手段」設定を開く</div>
        <p>右上 ⚙設定 →「サービス・プロダクト設定」の <strong>Payments</strong> →「決済手段」。<br>
           <span class="muted">下のボタンからも直接開けます。</span></p>

        <div class="modal__step">2. 一覧から「PayPay」を探す</div>
        <p>「デジタルウォレット」タイプ・地域「日本」にあります。検索枠で <code>PayPay</code> と入力すると速いです。</p>

        <div class="modal__step">3. PayPay を有効にする</div>
        <p>PayPay の行をクリック（または右の …）→ <strong>有効にする</strong> を押す。<br>
           <span class="muted">利用には「通貨＝日本円・日本のアカウント」等の条件があります。テストでも決済可。</span></p>

        <div class="modal__step">4. 必要なら他の方法も有効化</div>
        <p>コンビニ決済（Konbini）・銀行振込 なども同じ手順で有効にできます。</p>

        <div class="modal__step">5. 完了（アプリ側の作業は不要）</div>
        <p>有効にした方法は、このアプリの決済画面に自動で表示されます。コード変更や再設定は要りません。</p>

        <div class="modal__actions">
            <a class="btn" href="https://dashboard.stripe.com/test/settings/payment_methods" target="_blank" rel="noopener">決済手段（テスト）を開く</a>
            <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_app_footer.php'; ?>
