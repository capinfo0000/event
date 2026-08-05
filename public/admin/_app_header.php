<?php

/**
 * ログイン後の管理画面シェル（サイドバー＋トップバー）。
 * 使い方：ページ側で require_tenant() 済みの $tenant を用意し、
 *   $pageTitle / $pageSub / $topActions（任意）を設定してから require する。
 *   末尾で _app_footer.php を require して閉じる。
 */

declare(strict_types=1);

$pageTitle = $pageTitle ?? '';
$pageSub   = $pageSub ?? '';
$topActions = $topActions ?? '';
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');

/** ナビ項目（active 判定用に対象スクリプト名の配列を持つ）。 */
$navItems = [
    ['dashboard.php', '', 'ダッシュボード', ['dashboard.php']],
    ['events.php',    '', 'イベント管理',   ['events.php']],
    ['index.php',     '', '参加者管理',     ['index.php']],
    ['stripe.php',    '', 'Stripe設定',    ['stripe.php', 'setup.php']],
    ['legal_edit.php', '', '規約・ポリシー', ['legal_edit.php', 'policy_edit.php']],
    ['account.php',   '', 'アカウント設定', ['account.php']],
    ['twofa_setup.php', '', '2段階認証', ['twofa_setup.php']],
];
if ((int) ($tenant['is_admin'] ?? 0) === 1) {
    $navItems[] = ['invites.php', '', 'アカウント発行', ['invites.php']];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle !== '' ? $pageTitle . ' - ' : '') ?>決済くん</title>
    <link rel="stylesheet" href="/assets/app.css?v=5">
    <script src="/assets/app.js?v=3" defer></script>
</head>
<body>
<div class="appshell">
    <header class="brandbar-top">
        <div class="brandbar-top__logo"><img src="/assets/logo-wide.webp?v=1" alt="決済くん"></div>
        <div class="brandbar-top__page">
            <div>
                <h1 class="topbar__title"><?= e($pageTitle) ?></h1>
                <?php if ($pageSub !== ''): ?><p class="topbar__sub"><?= e($pageSub) ?></p><?php endif; ?>
            </div>
            <?php if ($topActions !== ''): ?><div class="topbar__actions"><?= $topActions ?></div><?php endif; ?>
        </div>
    </header>
    <div class="app">
    <aside class="sidebar">
        <nav class="nav">
            <?php foreach ($navItems as [$href, $icon, $label, $match]): ?>
                <a href="<?= e($href) ?>" class="<?= in_array($current, $match, true) ? 'active' : '' ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <div class="nav__sep"></div>
            <a href="../o.php?t=<?= e(urlencode($tenant['id'])) ?>" target="_blank">公開ページを見る</a>
            <a href="logout.php">ログアウト</a>
        </nav>
        <div class="sidebar__foot"><?= e($tenant['display_name'] ?? '') ?><br><?= e($tenant['email'] ?? '') ?></div>
    </aside>
    <div class="content">
        <main class="page">
        <?php if (is_demo_tenant($tenant)): ?>
            <div class="flash" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;">
                <strong>デモモード（ポートフォリオ）:</strong>
                これはサンプルデータの体験用アカウントです。決済は無効で、外部への送信は行われません。イベントは自由に編集できますが、内容は再ログイン時にリセットされます。
            </div>
        <?php endif; ?>
        <?php foreach (security_warnings() as $__w): ?>
            <div class="flash flash--ng">
                <strong><?= $__w['level'] === 'critical' ? '🔴 重大なセキュリティ警告' : '⚠️ セキュリティ警告' ?>:</strong>
                <?= e($__w['msg']) ?>
            </div>
        <?php endforeach; ?>
