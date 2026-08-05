<?php

/**
 * 規約・ポリシー設定（ログイン中テナント専用）。
 * キャンセルポリシー／特定商取引法に基づく表記／利用規約／プライバシーポリシーの
 * 4種の文面を1画面でまとめて編集する。
 * 各項目は「空のまま保存すると既定テンプレート」に戻る。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$msg = '';

// 編集対象（DBカラム => 画面ラベル・入力名）。
$fields = [
    'cancel_policy'   => ['label' => 'キャンセル・返金ポリシー', 'preview' => 'policy',    'anchor' => 'cancel'],
    'legal_tokushoho' => ['label' => '特定商取引法に基づく表記', 'preview' => 'tokushoho', 'anchor' => 'tokushoho'],
    'legal_terms'     => ['label' => '利用規約',                 'preview' => 'terms',     'anchor' => 'terms'],
    'legal_privacy'   => ['label' => 'プライバシーポリシー',     'preview' => 'privacy',   'anchor' => 'privacy'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    foreach (array_keys($fields) as $col) {
        $text = (string) ($_POST[$col] ?? '');
        $text = mb_substr($text, 0, 8000);
        set_tenant_policy_text($tenant['id'], $col, $text);
    }
    $tenant = find_tenant_by_id($tenant['id']);
    $msg = '規約・ポリシーを保存しました（空欄の項目は既定テンプレートに戻ります）。';
}

// 各項目のプレビュー先（公開ページ）。自分のテナントIDを付けて自分の文面を表示。
$tt = urlencode($tenant['id']);

$cancelSample = "本イベントは事前決済（前払い）制です。お支払い後のキャンセルは、以下の規定を適用します。\n\n"
    . "【返金率（開催日基準）】\n"
    . "・開催8日前まで：全額返金（決済手数料を除く）\n"
    . "・開催7〜2日前：50%返金\n"
    . "・開催前日・当日／無連絡不参加：返金なし\n\n"
    . "※ 返金はStripeを通じて、お支払いに使用されたカードへ行います。決済手数料は返金されません。\n"
    . "※ 主催者都合での中止（荒天等）の場合は全額返金します。";

$placeholders = [
    'cancel_policy'   => $cancelSample,
    'legal_tokushoho' => "販売事業者：〇〇イベント事務局\n運営責任者：山田 太郎\n所在地：（請求があれば遅滞なく開示します）\n連絡先：info@example.com\n販売価格：各イベントページに表示\n支払方法：クレジットカード（決済代行：Stripe）／当日現金\n支払時期：申込時（事前決済）または当日（当日支払い）\n提供時期：決済完了後ただちに参加受付\n返品・キャンセル：キャンセル・返金ポリシーに準じます",
    'legal_terms'     => "第1条（本サービス）…\n第2条（アカウント）…\n第3条（料金）…\n第4条（禁止事項）…\n第5条（免責）…",
    'legal_privacy'   => "1. 取得する情報…\n2. 利用目的…\n3. 第三者提供・委託（決済のため Stripe に提供）…\n4. 保管・安全管理…\n5. 開示・訂正・削除の請求先…",
];

$token = csrf_token();
$pageTitle = '規約・ポリシー設定';
$pageSub = '参加者・公開ページに表示される規約や表記をまとめて編集します';
require __DIR__ . '/_app_header.php';
?>
<style nonce="<?= e(csp_nonce()) ?>">
    .legal-nav { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 16px; }
    .legal-nav a { display: inline-block; padding: 6px 12px; border: 1px solid var(--border); border-radius: 999px;
                   font-size: .85rem; font-weight: 600; color: var(--text); text-decoration: none; background: var(--surface); }
    .legal-sec { scroll-margin-top: 16px; }
    .legal-sec .card__title { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
</style>

<?php if ($msg !== ''): ?>
    <div class="flash flash--ok"><?= e($msg) ?></div>
<?php endif; ?>

<p class="muted">
    4種類の文面を1画面で編集できます。<strong>空のまま保存すると、その項目は既定のテンプレート</strong>が表示されます。
    改行はそのまま反映され、HTMLタグは使えません（安全のため自動でエスケープされます）。
</p>

<div class="legal-nav">
    <?php foreach ($fields as $meta): ?>
        <a href="#sec-<?= e($meta['anchor']) ?>"><?= e($meta['label']) ?></a>
    <?php endforeach; ?>
</div>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
    <?php foreach ($fields as $col => $meta): ?>
        <?php $val = (string) ($tenant[$col] ?? ''); ?>
        <div class="card legal-sec" id="sec-<?= e($meta['anchor']) ?>">
            <div class="card__title">
                <span><?= e($meta['label']) ?></span>
                <a class="btn btn--ghost" style="font-size:.8rem; padding:4px 10px;"
                   href="../<?= e($meta['preview']) ?>.php?t=<?= $tt ?>" target="_blank" rel="noopener">プレビュー（公開ページ）</a>
            </div>
            <p class="muted" style="margin:0 0 8px;">
                <?php if ($val === ''): ?>
                    現在は<strong>既定テンプレート</strong>を表示中です。下に入力して保存すると、その内容に差し替わります。
                <?php else: ?>
                    設定済みです。空にして保存すると既定テンプレートに戻ります。
                <?php endif; ?>
            </p>
            <textarea name="<?= e($col) ?>" rows="10" maxlength="8000"
                      placeholder="<?= e($placeholders[$col] ?? '') ?>"><?= e($val) ?></textarea>
        </div>
    <?php endforeach; ?>

    <p style="margin-top:6px;">
        <button type="submit" class="btn">すべて保存する</button>
    </p>
</form>

<p class="muted" style="margin-top:10px;">
    ※ これらのページは、公開ページ（イベント一覧）や申込導線から参加者が閲覧できます。
    決済を伴うため、<strong>特定商取引法に基づく表記</strong>は実運用前に必ず内容を確定してください。
</p>
<?php require __DIR__ . '/_app_footer.php'; ?>
