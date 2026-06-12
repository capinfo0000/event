<?php

/**
 * イベントを DB から削除する（ログイン中テナント単位）。管理画面からの POST のみ。
 * （過去の申込・決済データは Stripe 側に残るため、名簿・返金は引き続き参照可能）
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST のみ許可されています。');
}

csrf_verify($_POST['csrf_token'] ?? null);

$id = trim((string) ($_POST['id'] ?? ''));

function back(string $msg, string $type): never
{
    header('Location: events.php?' . http_build_query(['msg' => $msg, 'type' => $type]), true, 303);
    exit;
}

if ($id === '') {
    back('削除対象が指定されていません。', 'ng');
}

// 自分のイベントか確認（削除前に取得しておく：参加者通知に使う）
$event = find_event($id);
if ($event === null || $event['tenant_id'] !== $tenant['id']) {
    back('対象のイベントが見つかりませんでした。', 'ng');
}

// 削除前に、現在の参加者へ中止のお知らせを送る
$body = ($event['name']) . " にお申し込みの皆さまへ\n\n"
    . "誠に勝手ながら、本イベントは中止（取消）となりました。\n\n"
    . '日時：' . $event['date'] . "\n"
    . '場所：' . $event['place'] . "\n\n"
    . "事前にお支払い済みの方には、主催者より返金のご案内をいたします。\n"
    . "ご迷惑をおかけし申し訳ございません。ご不明点は主催者までご連絡ください。\n";
$n = notify_event_participants($id, '【イベント中止のお知らせ】' . $event['name'], $body);

if (!delete_event($tenant['id'], $id)) {
    back('対象のイベントが見つかりませんでした。', 'ng');
}

back('イベントを削除しました。' . ($n > 0 ? "（参加者 {$n} 名に中止を通知しました）" : ''), 'ok');
