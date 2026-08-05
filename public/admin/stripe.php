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

    // デモアカウントでは共有される都合上、Stripe 鍵の登録・削除は受け付けない
    // （公開デモに実鍵を置かせない安全策）。閲覧・画面操作のみ可能。
    if (is_demo_tenant($tenant)) {
        $msg = 'デモモードでは Stripe 鍵の登録・変更はできません。実際の運用では、ここにご自身の Stripe キーを登録して決済を有効化します。';
        $msgType = 'ng';
    } elseif (connect_required()) {
        // Connect 必須モードでは、主催者の秘密鍵をサーバーに保存させない。
        // 手動登録は無効化し、ダッシュボードの「Stripe を接続」（OAuth）へ誘導する。
        $msg = 'この環境は Stripe 接続（Connect）必須モードです。秘密鍵の手動登録はできません。ダッシュボードの「Stripe を接続する」から連携してください（サーバーは秘密鍵を保存しません）。';
        $msgType = 'ng';
    } elseif ($action === 'save' && trim((string) ($_POST['stripe_key'] ?? '')) !== '' && !request_is_https()) {
        // 平文送信防止: 鍵の登録は HTTPS でのみ受け付ける。
        $msg = 'セキュリティのため、APIキーの登録は HTTPS 接続でのみ行えます。https:// のURLで開き直してください（SSL が有効か管理者にご確認ください）。';
        $msgType = 'ng';
    } elseif ($action === 'save') {
        $key = trim((string) ($_POST['stripe_key'] ?? ''));
        if ($key === '') {
            // 空で保存＝削除
            set_tenant_stripe_key($tenant['id'], null);
            audit_log('stripe.key.clear', ['tenant' => $tenant['id']]);
            notify_security_event($tenant, 'Stripe APIキーの削除');
            $msg = 'Stripe 鍵を削除しました。';
            $tenant = find_tenant_by_id($tenant['id']);
        } elseif (!preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $key)) {
            $msg = '鍵の形式が正しくありません（sk_… または rk_… で始まる文字列）。';
            $msgType = 'ng';
        } elseif (file_web_downloadable(tenant_key_path($tenant['id'])) === true) {
            // 鍵の保存先が Web から直接DL可能なら、保存せず中止（公開領域に鍵を置かせない）。
            $msg = '鍵の保存先が Web から直接ダウンロードできる状態のため、安全のため保存を中止しました。'
                . '.env の STRIPE_KEY_DIR を公開フォルダの外（例: /home/アカウント/private）に設定してください。';
            $msgType = 'ng';
        } else {
            // 形式OKなら保存。接続テストは結果通知のみ（失敗してもネットワーク要因がありうるため保存は維持）。
            try {
                set_tenant_stripe_key($tenant['id'], $key);
                [$okTest, $detail] = stripe_test_key($key);
                audit_log('stripe.key.save', [
                    'tenant' => $tenant['id'],
                    'mode' => str_contains($key, '_live_') ? 'live' : 'test',
                    'type' => str_starts_with($key, 'rk_') ? 'restricted' : 'full',
                    'fp' => substr($key, -4), // 末尾4桁（Stripeの鍵一覧と突合するための識別。秘密ではない）
                    'verify' => $okTest ? 'ok' : 'ng',
                ]);
                notify_security_event($tenant, 'Stripe APIキーの登録／変更');
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
        audit_log('stripe.key.clear', ['tenant' => $tenant['id']]);
        notify_security_event($tenant, 'Stripe APIキーの削除');
        $msg = '保存した鍵を削除しました。';
        $tenant = find_tenant_by_id($tenant['id']);
    }
}

// 現在の状態（マスク表示。全体は出さない）
$hasKey = tenant_has_stripe_key($tenant);
$masked = '';
$modeLabel = '';
$typeLabel = '';
$isLive = false;
$isFull = false;
$registeredAt = null;
$advisories = []; // 万一の漏えいに備えた注意（被害を小さく・追跡しやすくするため）
if ($hasKey) {
    $plain = (string) get_tenant_stripe_key($tenant);
    if ($plain !== '') {
        $masked = (strlen($plain) > 12 ? substr($plain, 0, 8) . '••••••••' . substr($plain, -4) : '（登録済み）');
        $isLive = str_contains($plain, '_live_');
        $isFull = str_starts_with($plain, 'sk_');
        $modeLabel = $isLive ? '本番キー（live）' : (str_contains($plain, '_test_') ? 'テストキー（test）' : '不明');
        $typeLabel = str_starts_with($plain, 'rk_') ? '制限付きキー（rk・推奨）' : 'フルアクセスキー（sk）';
        $mtime = @filemtime(tenant_key_path($tenant['id']));
        $registeredAt = $mtime !== false ? $mtime : null;
        if ($isFull && $isLive) {
            $advisories[] = '本番の「フルアクセスキー（sk_live）」が登録されています。より安全に運用するため、権限を絞った「制限付きキー（rk_live）」への差し替えをおすすめします。';
        } elseif ($isFull) {
            $advisories[] = 'フルアクセスキー（sk）です。本番運用では権限を絞った「制限付きキー（rk）」をおすすめします。';
        }
    } else {
        $masked = '（登録済み・復号不可：APP_KEY を確認）';
    }
}
if ($hasKey && app_key_on_disk()) {
    $advisories[] = '暗号化キー（APP_KEY）が .env に保存されています。より安全にするには、APP_KEY を .env ではなくサーバーの「実環境変数」に設定してください（鍵ファイルと .env を同時に盗まれても復号されなくなります）。';
}

$token = csrf_token();
$pageTitle = 'Stripe 設定';
$pageSub = 'クレジットカード決済（事前決済）に使う鍵を設定します';
require __DIR__ . '/_app_header.php';
?>
<style nonce="<?= e(csp_nonce()) ?>">
    .info-i { display:inline-flex; align-items:center; justify-content:center; width:19px; height:19px;
              border-radius:50%; border:1px solid var(--border); background:#fff; color:var(--muted);
              font-size:.72rem; font-weight:800; font-style:italic; cursor:pointer; vertical-align:middle;
              margin-left:6px; line-height:1; padding:0; }
    .info-i:hover { border-color:var(--accent); color:var(--accent); }
    .keyinfo h4 { margin:18px 0 6px; font-size:1rem; }
    .keyinfo h4:first-of-type { margin-top:4px; }
    .btnrow { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
</style>

<?php if ($msg !== ''): ?>
    <div class="flash <?= $msgType === 'ok' ? 'flash--ok' : 'flash--ng' ?>"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card__title">Stripe APIキー<button type="button" class="info-i" data-modal-open="keyInfo" aria-label="制限付きキーについて" title="制限付きキーについて詳しく">i</button></div>

    <?php if ($hasKey): ?>
        <p style="margin:0 0 8px;">
            ✅ 設定済み：<code><?= e($masked) ?></code>
            <span class="badge <?= $isFull ? 'badge--warn' : 'badge--ok' ?>" style="margin-left:6px;"><?= e($typeLabel) ?></span>
            <span class="badge"><?= e($modeLabel) ?><?= $isLive ? '・実課金' : '' ?></span>
        </p>
        <?php if ($registeredAt !== null): ?>
            <p class="muted" style="margin:0 0 10px; font-size:.85rem;">登録日時：<?= e(date('Y-m-d H:i', $registeredAt)) ?></p>
        <?php endif; ?>
        <?php foreach ($advisories as $a): ?>
            <div class="flash flash--ng" style="margin:8px 0;">⚠️ <?= e($a) ?> <button type="button" class="info-i" data-modal-open="keyInfo" title="詳しく">i</button></div>
        <?php endforeach; ?>
        <label style="margin-top:4px;">キーを変更／再登録（<code>rk_…</code> 推奨）</label>
        <form id="stripeSave" method="post" style="margin:0 0 10px;">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="save">
            <input type="password" name="stripe_key" autocomplete="off" placeholder="rk_test_xxxxx（変更するときだけ入力）">
        </form>
        <div class="btnrow">
            <button type="submit" form="stripeSave" class="btn">保存する</button>
            <form method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                <input type="hidden" name="action" value="test">
                <button type="submit" class="btn btn--ghost">接続確認</button>
            </form>
            <form method="post" style="margin:0;" data-confirm="保存した鍵を削除します。よろしいですか？（削除後は事前決済を受け付けられません）">
                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn--danger">鍵を削除</button>
            </form>
        </div>
        <p class="hint">キー欄を空のまま保存すると登録を解除します。</p>
    <?php else: ?>
        <p style="margin-top:0;">推奨は<strong>制限付きキー（<code>rk_</code>）</strong>です。必要な権限だけに絞れて安心です。<button type="button" class="info-i" data-modal-open="keyInfo" title="制限付きキーについて詳しく">i</button></p>
        <form method="post" style="margin-top:6px;">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="save">
            <label>Stripe キー（<code>rk_…</code> 推奨 ／ <code>sk_…</code> も可）</label>
            <input type="password" name="stripe_key" autocomplete="off" placeholder="rk_test_xxxxx（推奨）">
            <p class="hint">入力した鍵は安全に保管され、画面には再表示されません。</p>
            <p style="margin-top:12px;"><button type="submit" class="btn">保存する</button></p>
        </form>
    <?php endif; ?>

    <p class="btnrow" style="margin-top:14px;">
        <button type="button" class="btn btn--ghost" data-modal-open="keyKinds">キーの見分け方</button>
        <button type="button" class="btn btn--ghost" data-modal-open="rkGuide">制限付きキーの作り方</button>
        <button type="button" class="btn btn--ghost" data-modal-open="apiKeyOpen">StripeでAPIキーを開く</button>
    </p>
</div>

<div class="card">
    <div class="card__title">その他の設定・確認</div>
    <p class="muted" style="margin-top:0;">決済画面には、Stripe 側で有効化した支払い方法（カード・Apple/Google Pay・PayPay 等）が自動表示されます。アプリ側の追加設定は不要です。</p>
    <p class="btnrow">
        <button type="button" class="btn btn--ghost" data-modal-open="paypayGuide">支払い方法を追加する（PayPay等の手順）</button>
        <button type="button" class="btn btn--ghost" data-modal-open="testCards">テスト用カード番号</button>
    </p>
</div>

<!-- ⓘ 制限付きキーについて（メリット・できること・漏えい時の対応） -->
<div class="modal" id="keyInfo" role="dialog" aria-modal="true">
    <div class="modal__box keyinfo">
        <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
        <div class="modal__title">制限付きキー（rk_）について</div>
        <p class="modal__lead">安心して決済を使うための鍵の選び方と、万一のときの落ち着いた対処をまとめました。</p>

        <h4>制限付きキーにするメリット</h4>
        <ul class="muted" style="line-height:1.9;">
            <li>権限を「必要な操作だけ」に絞れます。<strong>送金（Payouts）や入金先口座の変更は権限に含めない</strong>ため、資金を動かす操作はできません。</li>
            <li>万一キーが第三者に渡っても、<strong>できることが付与した範囲に限定</strong>されます（被害を小さく保てます）。</li>
            <li>作成・差し替えはいつでも簡単。テスト環境でもそのまま作れます。</li>
        </ul>

        <h4>このアプリで制限付きキーができること（付与する権限）</h4>
        <ul class="muted" style="line-height:1.9;">
            <li>Charges and Refunds … 書込（決済・返金）</li>
            <li>Customers … 書込（参加者の記録）</li>
            <li>Payment Intents … 読取（入金状況の確認）</li>
            <li>Accounts … 読取（接続確認）</li>
            <li>Checkout Sessions … 書込（決済画面の作成）</li>
        </ul>
        <p class="muted">＝「参加者名簿の取得・決済の受付・返金」に必要な最小限だけ。これ以外は「なし」で大丈夫です。</p>

        <h4>万一キーが漏れたときは（落ち着いて対応できます）</h4>
        <p>影響が及ぶのは<strong>あなた自身の Stripe アカウントだけ</strong>です。他の主催者や当サービス全体、参加者のカード番号そのもの（Stripe が保持）には影響しません。制限付きキーなら、できることも付与した権限の範囲に限られ、<strong>別口座への送金や入金先の変更はできません</strong>。</p>
        <p>気づいたら、次の3ステップで数分で無効化できます。</p>
        <ol class="muted" style="line-height:1.9;">
            <li>Stripe ダッシュボード →「開発者」→「APIキー」で、該当キー（末尾4桁で照合）を<strong>失効（Roll）</strong>。</li>
            <li>この画面で<strong>新しいキーに差し替え</strong>。</li>
            <li>必要なら監査ログ（<code>logs/audit.log</code>）で <code>stripe.key.*</code>・<code>refund</code> などの記録を確認。</li>
        </ol>
        <p class="muted">※ フルアクセスキー（sk_）でも動きますが、漏れたときにできる範囲が広くなるため、制限付きキーをおすすめしています。</p>

        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
        </div>
    </div>
</div>

<!-- キーの見分け方（rk/sk・test/live） -->
<div class="modal" id="keyKinds" role="dialog" aria-modal="true">
    <div class="modal__box">
        <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
        <div class="modal__title">キーの見分け方（rk / sk・test / live）</div>
        <p class="modal__lead">Stripe のキーは先頭の文字で種類がわかります。形は <code>種別_モード_……</code> です。</p>

        <h4 style="margin:4px 0 6px;">① 最初の2文字＝権限の種別</h4>
        <ul class="muted" style="line-height:1.9;">
            <li><code>rk_</code> … <strong>制限付きキー</strong>（Restricted・権限を絞れる／<strong style="color:#16a34a">推奨</strong>）</li>
            <li><code>sk_</code> … <strong>フルアクセスキー</strong>（Secret・全権限）</li>
        </ul>

        <h4 style="margin:14px 0 6px;">② 次の語＝モード</h4>
        <ul class="muted" style="line-height:1.9;">
            <li><code>_test_</code> … <strong>テスト</strong>（本物の課金は発生しない・練習用）</li>
            <li><code>_live_</code> … <strong>本番</strong>（実際に課金される）</li>
        </ul>

        <h4 style="margin:14px 0 6px;">組み合わせ（4種類）</h4>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:.88rem;">
                <tr>
                    <th style="text-align:left; padding:8px 10px; border-bottom:1px solid var(--border);">先頭</th>
                    <th style="text-align:left; padding:8px 10px; border-bottom:1px solid var(--border);">意味</th>
                    <th style="text-align:left; padding:8px 10px; border-bottom:1px solid var(--border);">用途</th>
                </tr>
                <tr>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);"><code>rk_test_…</code></td>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);">制限付き × テスト</td>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);">練習・動作確認に最適</td>
                </tr>
                <tr>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);"><code>rk_live_…</code></td>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);">制限付き × 本番</td>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);"><strong style="color:#16a34a">本番運用のおすすめ ✅</strong></td>
                </tr>
                <tr>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);"><code>sk_test_…</code></td>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);">フルアクセス × テスト</td>
                    <td style="padding:8px 10px; border-bottom:1px solid var(--border);">動くが権限が広め</td>
                </tr>
                <tr>
                    <td style="padding:8px 10px;"><code>sk_live_…</code></td>
                    <td style="padding:8px 10px;">フルアクセス × 本番</td>
                    <td style="padding:8px 10px;">最も取り扱い注意</td>
                </tr>
            </table>
        </div>
        <p class="muted" style="margin-top:12px;">※ 迷ったら、本番は <code>rk_live_…</code> が安全です。<br>※ <code>pk_</code>（公開可能キー）はこのアプリでは使いません。</p>

        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
        </div>
    </div>
</div>

<!-- StripeのAPIキー画面を開く（説明＋テスト/本番へ移動） -->
<div class="modal" id="apiKeyOpen" role="dialog" aria-modal="true">
    <div class="modal__box">
        <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
        <div class="modal__title">Stripe の APIキー画面を開く</div>
        <p class="modal__lead">Stripe のダッシュボードで、決済に使う APIキー（<code>rk_</code>／<code>sk_</code>）を作成・確認できます。ボタンを押すと Stripe が<strong>別タブ</strong>で開きます。</p>
        <ul class="muted" style="line-height:1.9;">
            <li><strong>テスト</strong>：練習・動作確認用（<code>_test_</code>）。本物の課金は発生しません。</li>
            <li><strong>本番</strong>：実際に課金される環境（<code>_live_</code>）。</li>
        </ul>
        <p class="muted">※ 作成手順は「制限付きキーの作り方」、種類の違いは「キーの見分け方」をご覧ください。おすすめは制限付きキー（本番は <code>rk_live_…</code>）です。</p>
        <div class="modal__actions">
            <a class="btn" href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">Stripeでテスト用APIキーを開く ↗</a>
            <a class="btn" href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Stripeで本番用APIキーを開く ↗</a>
            <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
        </div>
    </div>
</div>

<!-- テスト用カード番号 -->
<div class="modal" id="testCards" role="dialog" aria-modal="true">
    <div class="modal__box">
        <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
        <div class="modal__title">テスト用カード番号</div>
        <p class="modal__lead">テストキー（<code>_test_</code>）のときは、次の番号で動作確認できます。実際の請求は発生しません。</p>
        <ul class="muted" style="line-height:1.9;">
            <li>成功（Visa）：<code>4242 4242 4242 4242</code> ／（Mastercard）<code>5555 5555 5555 4444</code> ／（JCB）<code>3530 1113 3330 0000</code> ／（Amex）<code>3782 822463 10005</code></li>
            <li>有効期限：未来の日付（例 12/34）／ CVC：任意の3桁（Amexは4桁）／ 郵便番号：任意</li>
            <li>失敗をテスト：<code>4000 0000 0000 0002</code>（拒否）／ <code>4000 0000 0000 9995</code>（残高不足）</li>
        </ul>
        <p class="hint">※ 本番（live）モードではテストカードは使えません。<a href="https://stripe.com/docs/testing" target="_blank" rel="noopener">Stripe のテスト情報</a></p>
        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
        </div>
    </div>
</div>

<!-- 制限付きキー（rk_）の作り方モーダル -->
<div class="modal" id="rkGuide" role="dialog" aria-modal="true">
    <div class="modal__box">
        <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
        <div class="modal__title">制限付きキー（rk_）の作り方</div>
        <p class="modal__lead">権限を絞ったキーです。万一漏れても被害を限定できます。テスト環境（sandbox）でそのまま作れます。</p>

        <div class="guide__row">
            <div class="guide__num">1</div>
            <div class="guide__body">
                <div class="gt">APIキー画面を開く</div>
                <p>右上の ⚙（設定） →「開発者」→「APIキーの管理」。</p>
                <p class="muted">⚙ 設定 › 開発者 › APIキー</p>
                <p>「制限付きのキー」の右上 <strong>＋ 制限付きのキーを作成</strong> を押す。</p>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">2</div>
            <div class="guide__body">
                <div class="gt">テンプレートを選ぶ</div>
                <p>「One-time payments」を選択 → 続ける。</p>
                <div class="tpl tpl--on">☑ One-time payments<small>チェックアウト/決済リンク等での支払い受付</small></div>
                <div class="tpl">Recurring subscriptions and billing</div>
                <div class="tpl">In-person payments with Terminal</div>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">3</div>
            <div class="guide__body">
                <div class="gt">キーの名前を入力</div>
                <div class="mockfield"><input type="text" value="event-app" readonly></div>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">4</div>
            <div class="guide__body">
                <div class="gt">権限を設定（下記だけ／他は「なし」）</div>
                <div class="perm">
                    <div class="perm__head">Core</div>
                    <div class="perm__row"><span>Charges and Refunds</span><span class="perm__pills"><span class="pill">なし</span><span class="pill">読取</span><span class="pill pill--on">書込</span></span></div>
                    <div class="perm__row"><span>Customers</span><span class="perm__pills"><span class="pill">なし</span><span class="pill">読取</span><span class="pill pill--on">書込</span></span></div>
                    <div class="perm__row"><span>Payment Intents</span><span class="perm__pills"><span class="pill">なし</span><span class="pill pill--on">読取</span><span class="pill">書込</span></span></div>
                    <div class="perm__head">Accounts</div>
                    <div class="perm__row"><span>Accounts</span><span class="perm__pills"><span class="pill">なし</span><span class="pill pill--on">読取</span><span class="pill">書込</span></span></div>
                    <div class="perm__head">Checkout Sessions</div>
                    <div class="perm__row"><span>Checkout Sessions</span><span class="perm__pills"><span class="pill">なし</span><span class="pill">読取</span><span class="pill pill--on">書込</span></span></div>
                </div>
                <p class="muted">※「Accounts＝読取」は特に忘れずに（無いと接続確認で弾かれます）。項目は Ctrl+F で検索すると速い。</p>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">5</div>
            <div class="guide__body">
                <div class="gt">作成してトークンをコピー</div>
                <p>一番下の <strong>キーを作成</strong> → 表示される <code>rk_test_…</code> の長い文字をコピー。</p>
                <div class="mockfield"><input type="text" value="rk_test_51Teq…UuJCFWm" readonly><span class="btn btn--ghost">コピー</span></div>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">6</div>
            <div class="guide__body">
                <div class="gt">このページに貼り付けて確認</div>
                <p>下の「Stripe 秘密鍵」欄に貼り付け → <strong>接続確認</strong>。✅「接続成功」でOK。</p>
                <p class="muted">※ 権限エラーが出たら、表示された権限（例：Checkout Sessions Read／Accounts）を追加して再確認。</p>
            </div>
        </div>

        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
        </div>
    </div>
</div>

<!-- PayPay 等を有効にする手順モーダル -->
<div class="modal" id="paypayGuide" role="dialog" aria-modal="true">
    <div class="modal__box">
        <button type="button" class="modal__close" data-modal-close aria-label="閉じる">×</button>
        <div class="modal__title">PayPay・コンビニ払い等を有効にする手順</div>
        <p class="modal__lead">決済画面には「Stripe で有効にした支払い方法」が自動で表示されます。PayPay 等は既定でオフのことがあるため、ダッシュボードで有効化します（テスト環境でも可）。</p>

        <div class="guide__row">
            <div class="guide__num">1</div>
            <div class="guide__body">
                <div class="gt">「決済手段」設定を開く</div>
                <p>右上 ⚙設定 →「サービス・プロダクト設定」の <strong>Payments</strong>（決済・チェックアウト・決済手段） →「決済手段」。</p>
                <p class="muted">⚙ 設定 › Payments › 決済手段</p>
                <p class="muted">※ 下の「Stripeで支払い方法を開く（テスト／本番）」ボタンからも直接開けます。</p>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">2</div>
            <div class="guide__body">
                <div class="gt">一覧から「PayPay」を探す</div>
                <p>「デジタルウォレット」タイプ・地域「日本」にあります。検索枠で <code>PayPay</code> と入力すると速いです。</p>
                <div class="perm">
                    <div class="perm__row"><span>Apple Pay</span><span class="pill pill--on">有効</span></div>
                    <div class="perm__row"><span>Google Pay</span><span class="pill pill--on">有効</span></div>
                    <div class="perm__row"><span>PayPay</span><span class="pill">無効</span></div>
                </div>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">3</div>
            <div class="guide__body">
                <div class="gt">PayPay を有効にする</div>
                <p>PayPay の行をクリック（または右の …）→ <strong>有効にする</strong> を押す。</p>
                <p class="muted">※ 利用には「通貨＝日本円・日本のアカウント」などStripe側の条件があります。テストでは「プレビューで有効」と表示される場合がありますが、テスト決済は可能です。</p>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">4</div>
            <div class="guide__body">
                <div class="gt">必要なら他の方法も有効化</div>
                <p>コンビニ決済（Konbini）・銀行振込 なども同じ手順で有効にできます。</p>
            </div>
        </div>
        <div class="guide__row">
            <div class="guide__num">5</div>
            <div class="guide__body">
                <div class="gt">完了（アプリ側の作業は不要）</div>
                <p>有効にした方法は、このアプリの決済画面に自動で表示されます。コード変更や再設定は要りません。</p>
            </div>
        </div>

        <div class="modal__actions">
            <a class="btn" href="https://dashboard.stripe.com/test/settings/payment_methods" target="_blank" rel="noopener">Stripeで支払い方法を開く（テスト）↗</a>
            <a class="btn" href="https://dashboard.stripe.com/settings/payment_methods" target="_blank" rel="noopener">Stripeで支払い方法を開く（本番）↗</a>
            <button type="button" class="btn btn--ghost" data-modal-close>閉じる</button>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/_prepay_info_modal.php'; ?>
<?php require __DIR__ . '/_app_footer.php'; ?>
