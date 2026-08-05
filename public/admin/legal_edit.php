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
    4種類の文面を1画面で編集できます。各欄には<strong>現在公開ページに表示されている文面（既定テンプレート）</strong>をあらかじめ読み込んでいます。
    そのまま編集して保存してください。<strong>空にして保存すると、その項目は既定テンプレートに戻ります。</strong>
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
        <?php
            $saved = (string) ($tenant[$col] ?? '');
            $isCustom = trim($saved) !== '';
            // 未設定なら既定文面を初期表示（＝いま公開ページに出ている文章を編集できる）。
            $val = $isCustom ? $saved : default_policy_text($col);
        ?>
        <div class="card legal-sec" id="sec-<?= e($meta['anchor']) ?>">
            <div class="card__title">
                <span><?= e($meta['label']) ?></span>
                <a class="btn btn--ghost" style="font-size:.8rem; padding:4px 10px;"
                   href="../<?= e($meta['preview']) ?>.php?t=<?= $tt ?>" target="_blank" rel="noopener">プレビュー（公開ページ）</a>
            </div>
            <p class="muted" style="margin:0 0 8px;">
                <?php if ($isCustom): ?>
                    <strong>設定済み</strong>の文面です。空にして保存すると既定テンプレートに戻ります。
                <?php else: ?>
                    <strong>既定テンプレート</strong>を読み込んでいます。編集して保存すると、その内容に差し替わります。
                <?php endif; ?>
            </p>
            <textarea name="<?= e($col) ?>" rows="12" maxlength="8000"><?= e($val) ?></textarea>
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
