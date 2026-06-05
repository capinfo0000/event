<?php

/**
 * 運営者ダッシュボード（ログイン後のトップ）。
 * Stripe キーの設定状況、申込状況のグラフ、各管理へのリンクを表示する。
 * 集計は運営者自身の Stripe（事前=Checkoutセッション／当日=顧客）から行う。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$stripeReady = env('STRIPE_SECRET_KEY') !== null;
$events = tenant_events($tenant['id']);
$usedEvents = count($events);
$publicUrl = base_url() . '/o.php?t=' . urlencode($tenant['id']);

// ---- 申込状況の集計（自分の Stripe から。失敗・未設定でも画面は出す） ----
$byDate = [];          // 'Y-m-d' => 申込件数
$prepayCount = 0;
$onsiteCount = 0;
$collected = 0;        // 事前決済の入金（返金差引）合計
$onsiteDue = 0;        // 当日・未収合計
$statsError = false;
if ($stripeReady) {
    try {
        foreach ($events as $ev) {
            foreach (fetch_event_participants($ev['id'], null) as $p) {
                $day = date('Y-m-d', (int) ($p['created'] ?? 0));
                $byDate[$day] = ($byDate[$day] ?? 0) + 1;
                if (($p['payment_type'] ?? 'prepay') === 'onsite') {
                    $onsiteCount++;
                    if (empty($p['collected'])) {
                        $onsiteDue += (int) $p['amount'];
                    }
                } else {
                    $prepayCount++;
                    $collected += max(0, (int) $p['amount'] - (int) $p['amount_refunded']);
                }
            }
        }
    } catch (\Throwable $e) {
        $statsError = true;
        error_log('ダッシュボード集計失敗: ' . $e->getMessage());
    }
}
$totalApplied = $prepayCount + $onsiteCount;

// 申込推移（日別の累積）をチャート用に整形
ksort($byDate);
$trendLabels = [];
$trendData = [];
$run = 0;
foreach ($byDate as $day => $cnt) {
    $run += $cnt;
    $trendLabels[] = substr($day, 5); // MM-DD
    $trendData[] = $run;
}

$pageTitle = 'ダッシュボード';
$pageSub = 'ようこそ、' . $tenant['display_name'] . ' さん';
$topActions = '<a class="btn" href="events.php">＋ イベントを作成</a>';
require __DIR__ . '/_app_header.php';
?>
<div class="stat-grid">
    <div class="stat"><span class="stat__num accent"><?= $totalApplied ?></span><span class="stat__label">総申込数（事前<?= $prepayCount ?>・当日<?= $onsiteCount ?>）</span></div>
    <div class="stat"><span class="stat__num"><?= e(format_amount($collected, 'jpy')) ?></span><span class="stat__label">事前入金合計</span></div>
    <div class="stat"><span class="stat__num"><?= e(format_amount($onsiteDue, 'jpy')) ?></span><span class="stat__label">当日・未収合計</span></div>
    <div class="stat"><span class="stat__num"><?= $usedEvents ?></span><span class="stat__label">登録イベント</span></div>
</div>

<div class="charts">
    <div class="card chart-card">
        <div class="card__title"><span class="ic">📈</span> 申込推移（累計）</div>
        <?php if ($totalApplied > 0): ?>
            <div class="chart-box"><canvas id="chartTrend"></canvas></div>
        <?php else: ?>
            <div class="chart-empty"><?= $statsError ? 'Stripe から集計できませんでした。' : ($stripeReady ? 'まだ申込がありません。' : 'Stripe キーを設定すると申込状況が表示されます。') ?></div>
        <?php endif; ?>
    </div>
    <div class="card chart-card">
        <div class="card__title"><span class="ic">🍩</span> 支払い方法の内訳</div>
        <?php if ($totalApplied > 0): ?>
            <div class="chart-box"><canvas id="chartMethods"></canvas></div>
        <?php else: ?>
            <div class="chart-empty">データがありません</div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card__title"><span class="ic">💳</span> Stripe（決済）</div>
    <?php if ($stripeReady): ?>
        <p>✅ Stripe キー設定済み。参加費はあなたの Stripe アカウントへ直接入金されます。</p>
        <p class="muted">クレジットカード（事前決済）と現金（当日支払い）の両方に対応します。</p>
    <?php else: ?>
        <p>⚠️ Stripe キーが未設定です。<code>.env</code> の <code>STRIPE_SECRET_KEY</code> にご自身の Stripe シークレットキー（<code>sk_...</code>）を設定すると、クレジットカード決済を受け付けられます。</p>
        <p class="muted">未設定でも「当日支払い（現金）」のみのイベントは利用できます。</p>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card__title"><span class="ic">🔗</span> 公開イベントページ</div>
    <p class="muted" style="margin-top:0;">この1つのリンクを参加者に共有すれば、開催中のイベントを一覧から選んで申し込めます。</p>
    <input type="text" readonly value="<?= e($publicUrl) ?>" onclick="this.select()">
    <p style="margin: 16px 0 0;">
        <a class="btn" href="events.php">イベント管理</a>
        <a class="btn btn--ghost" href="index.php">参加者管理</a>
    </p>
</div>

<?php if ($totalApplied > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
    const ACCENT = '#2563eb';
    const trendCtx = document.getElementById('chartTrend');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: '累計申込',
                    data: <?= json_encode($trendData) ?>,
                    borderColor: ACCENT,
                    backgroundColor: 'rgba(37,99,235,.12)',
                    fill: true, tension: .35, pointRadius: 3, borderWidth: 2,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }
    const methodsCtx = document.getElementById('chartMethods');
    if (methodsCtx) {
        new Chart(methodsCtx, {
            type: 'doughnut',
            data: {
                labels: ['事前決済', '当日支払い'],
                datasets: [{
                    data: [<?= $prepayCount ?>, <?= $onsiteCount ?>],
                    backgroundColor: [ACCENT, '#f59e0b'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                cutout: '62%',
            },
        });
    }
</script>
<?php endif; ?>
<?php require __DIR__ . '/_app_footer.php'; ?>
