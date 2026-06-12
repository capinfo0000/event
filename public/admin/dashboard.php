<?php

/**
 * 運営者ダッシュボード（ログイン後のトップ）。
 * Stripe キーの設定状況、申込状況のグラフ、各管理へのリンクを表示する。
 * 集計は運営者自身の Stripe（事前=Checkoutセッション／当日=顧客）から行う。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$stripeReady = stored_stripe_key() !== null;
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
$topActions = '<a class="btn" href="events.php">イベントを作成</a>';
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
        <div class="card__title">申込推移（累計）</div>
        <?php if ($totalApplied > 0): ?>
            <div class="chart-box"><canvas id="chartTrend"></canvas></div>
        <?php else: ?>
            <div class="chart-empty"><?= $statsError ? 'Stripe から集計できませんでした。' : ($stripeReady ? 'まだ申込がありません。' : 'Stripe キーを設定すると申込状況が表示されます。') ?></div>
        <?php endif; ?>
    </div>
    <div class="card chart-card">
        <div class="card__title">支払い方法の内訳</div>
        <?php if ($totalApplied > 0): ?>
            <div class="chart-box"><canvas id="chartMethods"></canvas></div>
        <?php else: ?>
            <div class="chart-empty">データがありません</div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card__title">Stripe（決済）</div>
    <?php if ($stripeReady): ?>
        <p>✅ Stripe キー設定済み。参加費はあなたの Stripe アカウントへ直接入金されます。</p>
        <p class="muted">クレジットカード（事前決済）と現金（当日支払い）の両方に対応します。</p>
        <p style="margin:14px 0 0;"><a class="btn btn--ghost" href="stripe.php">Stripe 設定・接続テスト</a></p>
    <?php else: ?>
        <p>⚠️ まだ Stripe の API キーが未設定です。<strong>初期設定</strong>から登録してください。</p>
        <p class="muted">※ 当日支払い（現金）の申込受付や参加者管理（名簿）も Stripe を使って記録・取得するため、現金のみの運用でも API キーの設定が必要です。</p>
        <p style="margin:14px 0 0;">
            <a class="btn" href="stripe.php">Stripe 設定へ進む</a>
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card__title">公開イベントページ</div>
    <p class="muted" style="margin-top:0;">この1つのリンクを参加者に共有すれば、開催中のイベントを一覧から選んで申し込めます。</p>
    <input type="text" readonly value="<?= e($publicUrl) ?>" onclick="this.select()">
    <p style="margin: 16px 0 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <a class="btn" href="events.php">イベント管理</a>
        <a class="btn btn--ghost" href="index.php">参加者管理</a>
        <?php include dirname(__DIR__) . '/_stripe_safety.php'; ?>
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

<?php if (!$stripeReady): ?>
<!-- Stripe未設定のあいだは毎回この初期設定ポップアップを表示 -->
<div class="setup-modal is-open" id="setupModal" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="setup-box">
        <button type="button" class="setup-close" aria-label="閉じる" onclick="document.getElementById('setupModal').classList.remove('is-open')">×</button>
        <h2>はじめの設定をしましょう</h2>
        <p class="muted" style="margin-top:0;">クレジットカード決済・参加者管理を使うには、最初に Stripe の API キーを登録します（当日支払い＝現金のみの運用でも必要です）。</p>
        <ol style="padding-left:1.2em; line-height:1.9;">
            <li><strong>Stripe 設定</strong>で API キーを登録（保存 → 接続確認）</li>
            <li><strong>イベント管理</strong>でイベントを作成</li>
            <li>発行された<strong>申込リンク</strong>を参加者に共有</li>
        </ol>
        <p style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn" href="stripe.php">Stripe 設定へ進む</a>
            <button type="button" class="btn btn--ghost" onclick="document.getElementById('setupModal').classList.remove('is-open')">あとで</button>
        </p>
    </div>
</div>
<style>
    .setup-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:1000;
        align-items:flex-start; justify-content:center; padding:24px; overflow-y:auto; }
    .setup-modal.is-open { display:flex; }
    .setup-box { background:#fff; border-radius:14px; max-width:520px; width:100%; padding:26px 26px 22px;
        position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .setup-box h2 { font-size:1.2rem; margin:0 0 8px; }
    .setup-close { position:absolute; top:8px; right:14px; background:none; border:none; font-size:1.7rem;
        line-height:1; cursor:pointer; color:#6b7280; }
    @media (max-width:480px){ .setup-modal{ padding:10px; } .setup-box{ padding:20px 16px; } }
</style>
<script>
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { var m=document.getElementById('setupModal'); if(m) m.classList.remove('is-open'); }
    });
</script>
<?php endif; ?>
<?php require __DIR__ . '/_app_footer.php'; ?>
