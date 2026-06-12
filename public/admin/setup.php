<?php

/**
 * 初期設定ウィザード。
 * 「①Stripe鍵 → ②イベント作成 → ③公開リンク共有」の手順と進捗を表示する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

$hasKey = stored_stripe_key() !== null;
$events = tenant_events($tenant['id']);
$hasEvent = count($events) > 0;
$publicUrl = base_url() . '/o.php?t=' . urlencode($tenant['id']);

$done = (int) $hasKey + (int) $hasEvent; // 0〜2（③は共有のみなので進捗には数えない）

$pageTitle = '初期設定';
$pageSub = 'はじめの3ステップ（' . $done . '/2 完了）';
require __DIR__ . '/_app_header.php';

/** ステップカードのヘッダ（番号・状態バッジ）を出力する。 */
function step_badge(bool $ok): string
{
    return $ok
        ? '<span class="tag tag--ok">完了</span>'
        : '<span class="tag tag--todo">未完了</span>';
}
?>
<style>
    .step { display:flex; gap:14px; align-items:flex-start; }
    .step__no { flex:0 0 36px; height:36px; border-radius:50%; background:var(--accent); color:#fff;
                display:flex; align-items:center; justify-content:center; font-weight:700; }
    .step__no.done { background:#16a34a; }
    .tag { display:inline-block; padding:2px 10px; border-radius:999px; font-size:.78rem; font-weight:700; }
    .tag--ok { background:#dcfce7; color:#166534; }
    .tag--todo { background:#fef9c3; color:#854d0e; }
    .step__body { flex:1; }
</style>

<div class="card">
    <div class="step">
        <div class="step__no <?= $hasKey ? 'done' : '' ?>">1</div>
        <div class="step__body">
            <div class="card__title" style="margin:0;">Stripe を設定する <?= step_badge($hasKey) ?></div>
            <p class="muted">ご自身の Stripe API キーを登録します。カード決済だけでなく、当日支払いの申込受付・参加者管理（名簿）にも Stripe を使うため、現金のみの運用でも設定が必要です。</p>
            <p><a class="btn" href="stripe.php"><?= $hasKey ? 'Stripe設定を確認' : 'Stripeキーを登録する' ?></a></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="step">
        <div class="step__no <?= $hasEvent ? 'done' : '' ?>">2</div>
        <div class="step__body">
            <div class="card__title" style="margin:0;">イベントを作成する <?= step_badge($hasEvent) ?></div>
            <p class="muted">日時・場所・参加費（事前／当日）・定員を設定します。<?= $hasEvent ? '（' . count($events) . '件 登録済み）' : '' ?></p>
            <p><a class="btn" href="events.php"><?= $hasEvent ? 'イベント管理へ' : 'イベントを作成する' ?></a></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="step">
        <div class="step__no">3</div>
        <div class="step__body">
            <div class="card__title" style="margin:0;">申込リンクを共有する</div>
            <p class="muted">この公開リンクを参加者に渡せば、一覧から選んで申し込めます。</p>
            <input type="text" readonly value="<?= e($publicUrl) ?>" onclick="this.select()">
            <p style="margin-top:10px;"><a class="btn btn--ghost" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">公開ページを開く</a></p>
        </div>
    </div>
</div>

<?php if ($hasKey && $hasEvent): ?>
    <div class="flash flash--ok">基本セットアップは完了です。あとは申込リンクを共有するだけ！</div>
<?php endif; ?>
<?php require __DIR__ . '/_app_footer.php'; ?>
