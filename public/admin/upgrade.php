<?php

/**
 * プランのアップグレード（主催者 → プラットフォーム運営者への課金）。
 *
 * 参加費（Connect の接続アカウント）とは別物。プラン利用料は
 * **プラットフォーム本体の Stripe アカウント**に入金される（stripe_account 指定なし）。
 * Stripe のサブスクリプション（Checkout subscription モード）で課金する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();
$priceIds = plan_price_ids();
$catalog = plan_catalog();
$currentPlan = $tenant['plan'] ?? 'free';

// プラン選択 → サブスクリプションの Checkout を作成して Stripe へ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $plan = (string) ($_POST['plan'] ?? '');
    if (!isset($priceIds[$plan])) {
        http_response_code(400);
        exit('選択されたプランは利用できません。');
    }

    init_stripe(); // プラットフォーム本体の鍵（接続アカウント指定なし＝本体に入金）
    try {
        $params = [
            'mode' => 'subscription',
            'line_items' => [['price' => $priceIds[$plan], 'quantity' => 1]],
            'client_reference_id' => $tenant['id'],
            'metadata' => ['tenant_id' => $tenant['id'], 'plan' => $plan],
            'subscription_data' => ['metadata' => ['tenant_id' => $tenant['id'], 'plan' => $plan]],
            'success_url' => base_url() . '/admin/billing_return.php?status=success',
            'cancel_url' => base_url() . '/admin/upgrade.php',
        ];
        // 既存の課金顧客があれば紐付け、無ければメールで作成
        if (!empty($tenant['stripe_customer_id'])) {
            $params['customer'] = $tenant['stripe_customer_id'];
        } else {
            $params['customer_email'] = $tenant['email'];
        }
        $session = \Stripe\Checkout\Session::create($params);
    } catch (\Throwable $e) {
        error_log('プラン課金セッション作成失敗: ' . $e->getMessage());
        http_response_code(502);
        exit('決済ページの作成に失敗しました。時間をおいて再度お試しください。');
    }
    header('Location: ' . $session->url, true, 303);
    exit;
}

// 開催月の上限に当たって誘導された場合の案内
$reason = (string) ($_GET['reason'] ?? '');
$limitMonth = (string) ($_GET['month'] ?? '');
$limitNotice = '';
if ($reason === 'month_limit') {
    $cur = plan_max_events($currentPlan);
    $mLabel = preg_match('/^(\d{4})-(\d{2})$/', $limitMonth, $mm)
        ? sprintf('%d年%d月', (int) $mm[1], (int) $mm[2])
        : 'その月';
    $limitNotice = sprintf(
        '現在のプラン（%s）では同じ開催月に登録できるイベントは %d件までです。%sはすでに上限に達しています。'
            . 'もっと登録するには、下記のプランにアップグレードしてください。',
        plan_label($currentPlan),
        $cur,
        $mLabel
    );
}

$token = csrf_token();
require __DIR__ . '/_auth_header.php';
?>
<h1>プランのアップグレード</h1>
<p class="muted"><a href="dashboard.php">← ダッシュボード</a> ／ <a href="events.php">イベント管理</a></p>
<?php if ($limitNotice !== ''): ?>
    <div class="err" style="background:#fef9c3;color:#854d0e;"><?= e($limitNotice) ?></div>
<?php endif; ?>
<p>現在のプラン：<strong><?= e(plan_label($currentPlan)) ?></strong></p>

<?php if (empty($priceIds)): ?>
    <div class="err">現在オンラインでのアップグレードは準備中です。運営者にお問い合わせください。</div>
<?php else: ?>
    <?php foreach ($catalog as $key => $info): ?>
        <?php if ($key === 'free' || !isset($priceIds[$key])) { continue; } ?>
        <div class="card">
            <h2 style="font-size:1.1rem; margin:0 0 4px;"><?= e($info['label']) ?></h2>
            <p>同じ開催月に登録できるイベント数：<?= $info['max_events'] === PHP_INT_MAX ? '無制限' : $info['max_events'] . ' 件' ?>
               ／ 月額 <?= e(format_amount($info['price'], 'jpy')) ?></p>
            <?php if ($currentPlan === $key): ?>
                <span class="muted">利用中のプランです</span>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="plan" value="<?= e($key) ?>">
                    <button type="submit" class="btn" style="width:auto;">このプランにする</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($tenant['stripe_customer_id'])): ?>
        <p class="muted"><a href="portal.php">お支払い方法の変更・解約はこちら（Stripe）</a></p>
    <?php endif; ?>
    <p class="muted" style="font-size:.8rem;">※ プラン反映は決済後、数十秒ほどで自動更新されます（Webhook 経由）。</p>
<?php endif; ?>
<?php require __DIR__ . '/_auth_footer.php'; ?>
