<?php

/**
 * 返金（キャンセル）実行。管理ダッシュボードからの POST のみを受ける。
 *
 * - 全額返金 = 実質キャンセル（金額未指定のとき）
 * - 一部返金 = amount を指定したとき（キャンセルポリシーの 50% 返金などに使用）
 *
 * 自前DBは持たないため、返金は Stripe の PaymentIntent に対して直接行う。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST のみ許可されています。');
}

csrf_verify($_POST['csrf_token'] ?? null);

$eventId = (string) ($_POST['event_id'] ?? '');
$paymentIntent = (string) ($_POST['payment_intent'] ?? '');
$amountRaw = trim((string) ($_POST['amount'] ?? ''));

/** 管理ダッシュボードへ結果を持って戻る。 */
function back_to_admin(string $eventId, string $msg, string $type): never
{
    $q = http_build_query(['event_id' => $eventId, 'msg' => $msg, 'type' => $type]);
    header('Location: index.php?' . $q, true, 303);
    exit;
}

// 対象イベントが自分のものか確認し、自分の接続アカウントに対して返金する
$event = $eventId !== '' ? find_event($eventId) : null;
if ($event === null || $event['tenant_id'] !== $tenant['id']) {
    audit_log('authz.deny', ['action' => 'refund', 'tenant' => $tenant['id'], 'event' => $eventId]);
    back_to_admin($eventId, '対象イベントが見つかりません。', 'ng');
}
$account = stripe_resolve_tenant($tenant); // 画面登録鍵→Connect→プラットフォームの順で文脈確立
if (!stripe_ready_for_tenant($tenant)) {
    back_to_admin($eventId, 'Stripe キー未設定のため返金できません。', 'ng');
}

if ($paymentIntent === '') {
    back_to_admin($eventId, '返金対象が不正です。', 'ng');
}

// IDOR対策: 指定 payment_intent が「このイベントの事前決済」であることを Stripe 側で検証する。
// （単一 Stripe 共有のため、検証しないと他テナントの決済に返金できてしまう。）
$participant = find_event_participant_by_payment_intent($eventId, $account, $paymentIntent);
if ($participant === null) {
    audit_log('authz.deny', ['action' => 'refund.pi', 'tenant' => $tenant['id'], 'event' => $eventId]);
    back_to_admin($eventId, '返金対象の決済が見つかりません。', 'ng');
}

init_stripe();
$opts = stripe_opts($account);

// 手数料・実受取額・既返金額を Stripe から権威的に取得（返金額の算定に使う）。
// 全額返金は「手数料を除いた実受取額」を返金し、主催者が手数料を負担しないようにする。
try {
    $pi = \Stripe\PaymentIntent::retrieve(
        ['id' => $paymentIntent, 'expand' => ['latest_charge.balance_transaction']],
        $opts
    );
} catch (\Throwable $ex) {
    error_log('PI 取得失敗: ' . $ex->getMessage());
    back_to_admin($eventId, '返金対象の確認に失敗しました。', 'ng');
}
$currency    = strtolower((string) ($pi->currency ?? ($participant['currency'] ?? 'jpy')));
$charge      = $pi->latest_charge ?? null;
$amountTotal = is_object($charge) ? (int) ($charge->amount ?? 0) : (int) ($pi->amount ?? 0);
$already     = is_object($charge) ? (int) ($charge->amount_refunded ?? 0) : 0;
$bt          = is_object($charge) ? ($charge->balance_transaction ?? null) : null;
$fee         = is_object($bt) ? (int) ($bt->fee ?? 0) : 0;
$net          = max(0, $amountTotal - $fee);   // 主催者の実受取額（＝amount − Stripe手数料）
$remainingNet = max(0, $net - $already);        // まだ返金できる実受取額

if ($remainingNet <= 0) {
    back_to_admin($eventId, '返金できる残額がありません（すでに実受取額まで返金済みです）。', 'ng');
}

$refundParams = ['payment_intent' => $paymentIntent];
$isPartial = ($amountRaw !== '');

if ($isPartial) {
    // 一部返金: 指定額をそのまま返金（手数料は含めない）。ただし実受取額の残りを上限にする。
    if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
        back_to_admin($eventId, '返金額の指定が不正です。', 'ng');
    }
    // 入力（表示通貨）→ 最小単位へ。JPY はそのまま、その他は 100 倍。
    $requested = ($currency === 'jpy')
        ? (int) round((float) $amountRaw)
        : (int) round((float) $amountRaw * 100);
    $refundParams['amount'] = min($requested, $remainingNet);
} else {
    // 全額返金（キャンセル）: 手数料を除いた実受取額（の残り）を返金。主催者が手数料を負担しない。
    $refundParams['amount'] = $remainingNet;
}

if ((int) $refundParams['amount'] <= 0) {
    back_to_admin($eventId, '返金額が0円になるため実行できません。', 'ng');
}

try {
    $refund = \Stripe\Refund::create($refundParams, $opts);
    audit_log('refund', [
        'tenant' => $tenant['id'], 'event' => $eventId,
        'pi' => substr($paymentIntent, 0, 10) . '…',
        'partial' => $isPartial ? '1' : '0',
        'amount' => (string) $refundParams['amount'],
    ]);
    $amtText = format_amount((int) $refundParams['amount'], $currency);
    $msg = $isPartial
        ? ('一部返金を実行しました（' . $amtText . '／返金ID: ' . $refund->id . '）。')
        : ('全額返金（キャンセル）を実行しました。手数料を除いた実受取額 ' . $amtText . ' を返金しました（返金ID: ' . $refund->id . '）。');
    back_to_admin($eventId, $msg, 'ok');
} catch (\Throwable $ex) {
    error_log('返金失敗: ' . $ex->getMessage());
    back_to_admin($eventId, '返金に失敗しました: ' . $ex->getMessage(), 'ng');
}
