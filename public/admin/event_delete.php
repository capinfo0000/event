<?php

/**
 * イベントを config/events.json から削除する。管理画面からの POST のみ。
 * （過去の申込・決済データは Stripe 側に残るため、名簿・返金は引き続き参照可能）
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_admin_auth();

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

$events = load_events();
$before = count($events);
$events = array_filter($events, static fn ($ev) => ($ev['id'] ?? null) !== $id);

if (count($events) === $before) {
    back('対象のイベントが見つかりませんでした。', 'ng');
}

try {
    save_events(array_values($events));
} catch (\Throwable $ex) {
    error_log('イベント削除失敗: ' . $ex->getMessage());
    back('削除に失敗しました: ' . $ex->getMessage(), 'ng');
}

back('イベントを削除しました。', 'ok');
