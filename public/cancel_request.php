<?php

/**
 * 参加者向け・キャンセル連絡ページ（公開・未ログイン）。
 * メール＋氏名で名簿を照合し、一致した場合のみ手続きを進める。
 *  - 当日払い: キャンセルポリシー（開催日からの逆算）でキャンセル料を自動算定し、
 *    料金が発生するなら決済画面へ誘導＋メール送信。料金0なら受付のみ。
 *  - 事前決済: 返金は主催者の承認制。ここでは主催者へ通知＋名簿に「キャンセル希望」を付ける。
 *  - 一致しない場合はエラー表示。
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$eventId = (string) ($_GET['event_id'] ?? ($_POST['event_id'] ?? ''));
$event   = $eventId !== '' ? find_event($eventId) : null;
$owner   = $event !== null ? find_tenant_by_id((string) ($event['tenant_id'] ?? '')) : null;
$account = $owner !== null ? stripe_resolve_tenant($owner) : null;

$error = '';
$done  = '';
$inEmail = '';
$inName  = '';

if ($event !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inEmail = trim((string) ($_POST['email'] ?? ''));
    $inName  = trim((string) ($_POST['name'] ?? ''));

    if (!rate_limit_check('cancel_request', 6, 3600)) {
        $error = '手続きの試行が多すぎます。しばらく時間をおいて再度お試しください。';
    } elseif ($inEmail === '' || $inName === '') {
        $error = 'メールアドレスと氏名を入力してください。';
    } elseif ($owner === null || !stripe_ready_for_tenant($owner)) {
        $error = '現在この主催者はオンラインでのキャンセル手続きを受け付けていません。お手数ですが主催者へ直接ご連絡ください。';
    } else {
        $p = find_event_participant_by_email_name($eventId, $account, $inEmail, $inName);
        if ($p === null) {
            $error = 'ご入力の情報（メールアドレスと氏名）に一致するお申し込みが見つかりませんでした。入力内容をご確認のうえ、再度お試しください。';
        } else {
            // 名簿に「キャンセル希望」の印を付け、主催者へ通知（承認制の起点）。
            mark_cancel_requested($account, (string) ($p['customer_id'] ?? ''));
            $ownerMail = (string) ($owner['email'] ?? '');
            if ($ownerMail !== '') {
                $bodyOwner = ($p['name'] ?? '') . '（' . $inEmail . '）さんから、「' . ($event['name'] ?? '') . '」のキャンセルのご連絡がありました。' . "\n"
                    . '支払方法：' . ((($p['payment_type'] ?? '') === 'onsite') ? '当日払い' : '事前決済') . "\n"
                    . '参加者管理の画面でご確認ください（事前決済の返金は主催者の操作が必要です）。' . "\n";
                send_mail($ownerMail, '【キャンセル希望】' . ($event['name'] ?? 'イベント'), $bodyOwner);
            }

            if (($p['payment_type'] ?? '') === 'onsite') {
                // 当日払い: ポリシー逆算でキャンセル料を自動算定
                $rate = cancellation_fee_rate_for_event((string) ($event['date'] ?? ''));
                $fee = (int) round(((int) ($p['amount'] ?? 0)) * (float) $rate['rate']);
                $currency = strtolower((string) ($event['currency'] ?? 'jpy'));
                if ($fee > 0) {
                    $session = create_cancel_fee_checkout($account, $event, $inEmail, (string) ($p['name'] ?? ''), (string) ($p['customer_id'] ?? ''), $fee);
                    if ($session !== null && ((string) ($session->url ?? '')) !== '') {
                        $bodyFee = (($p['name'] ?? '') !== '' ? $p['name'] . ' 様' : 'ご参加予定者様') . "\n\n"
                            . '「' . ($event['name'] ?? '') . '」のキャンセルを承りました。キャンセルポリシーに基づき、キャンセル料のお支払いをお願いいたします。' . "\n\n"
                            . '金額：' . format_amount($fee, $currency) . "\n"
                            . 'お支払いはこちら（Stripe の安全な決済ページ）：' . "\n" . $session->url . "\n\n"
                            . '※ このリンクの有効期限は発行からおおよそ24時間です。期限切れの場合は主催者へご連絡ください。' . "\n";
                        send_mail($inEmail, '【キャンセル料のお支払い】' . ($event['name'] ?? 'イベント'), $bodyFee);
                        audit_log('cancel_request.onsite_fee', ['event' => $eventId, 'amount' => (string) $fee]);
                        header('Location: ' . $session->url, true, 303); // 決済画面へ
                        exit;
                    }
                    $error = '決済ページの作成に失敗しました。時間をおいて再度お試しください。';
                } else {
                    audit_log('cancel_request.onsite_free', ['event' => $eventId]);
                    $done = 'キャンセルを受け付けました。キャンセルポリシー上、キャンセル料は発生しません（' . $rate['label'] . '）。主催者にもご連絡が届いています。';
                }
            } else {
                // 事前決済: 返金は主催者の承認制。ここでは受付のみ。
                audit_log('cancel_request.prepay', ['event' => $eventId]);
                $done = 'キャンセルのご連絡を承りました。ご返金は、キャンセルポリシーに従い、主催者の確認後に対応いたします。以後の手続きは主催者からのご連絡をお待ちください。';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャンセルのご連絡</title>
    <link rel="stylesheet" href="/assets/app.css?v=5">
</head>
<body>
<div class="container">
    <div class="brandbar">決済くん</div>
    <?php if ($event === null): ?>
        <div class="card"><p class="err">イベントが見つかりませんでした。リンクをご確認ください。</p></div>
    <?php elseif ($done !== ''): ?>
        <div class="card">
            <div class="card__title">キャンセルのご連絡</div>
            <p class="flash flash--ok" style="margin:0;"><?= e($done) ?></p>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card__title">キャンセルのご連絡</div>
            <p class="muted" style="margin-top:0;">お申し込み時の<strong>氏名とメールアドレス</strong>をご入力ください。</p>
            <?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="event_id" value="<?= e($eventId) ?>">
                <label>氏名（お申し込み時のお名前）</label>
                <input type="text" name="name" required maxlength="100" value="<?= e($inName) ?>">
                <label>メールアドレス（お申し込み時のもの）</label>
                <input type="email" name="email" required autocomplete="email" value="<?= e($inEmail) ?>">
                <p class="hint">当日払いの方：キャンセルポリシーに応じたキャンセル料が発生する場合、お支払い画面に進みます。<br>事前決済の方：返金はキャンセルポリシーに従い、主催者の確認後に対応します。</p>
                <p style="margin-top:14px;"><button type="submit" class="btn btn--block btn--lg">キャンセルを申請する</button></p>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
