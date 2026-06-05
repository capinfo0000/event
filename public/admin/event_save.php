<?php

/**
 * イベントの新規登録・更新を config/events.json に保存する。
 * 管理画面（events.php）からの POST のみを受ける。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_admin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST のみ許可されています。');
}

csrf_verify($_POST['csrf_token'] ?? null);

/** events.php へ結果を持って戻る。 */
function back_to_events(string $msg, string $type, string $editId = ''): never
{
    $params = ['msg' => $msg, 'type' => $type];
    if ($editId !== '') {
        $params['edit'] = $editId;
    }
    header('Location: events.php?' . http_build_query($params), true, 303);
    exit;
}

$id       = trim((string) ($_POST['id'] ?? ''));
$name     = trim((string) ($_POST['name'] ?? ''));
$desc     = trim((string) ($_POST['description'] ?? ''));
$date     = trim((string) ($_POST['date'] ?? ''));
$place    = trim((string) ($_POST['place'] ?? ''));
$amount   = (string) ($_POST['amount'] ?? '');
$currency = strtolower(trim((string) ($_POST['currency'] ?? 'jpy'))) ?: 'jpy';
$capacity = trim((string) ($_POST['capacity'] ?? ''));

// 入力チェック
if ($name === '' || $date === '' || $place === '') {
    back_to_events('イベント名・日時・場所は必須です。', 'ng', $id);
}
if ($amount === '' || !ctype_digit($amount)) {
    back_to_events('参加費は0以上の整数（最小通貨単位）で入力してください。', 'ng', $id);
}

$record = [
    'id'          => $id !== '' ? $id : generate_event_id(),
    'name'        => mb_substr($name, 0, 100),
    'description' => mb_substr($desc, 0, 500),
    'date'        => mb_substr($date, 0, 50),
    'place'       => mb_substr($place, 0, 100),
    'amount'      => (int) $amount,
    'currency'    => preg_replace('/[^a-z]/', '', $currency) ?: 'jpy',
    'capacity'    => ($capacity !== '' && ctype_digit($capacity)) ? (int) $capacity : 0,
];

$events = load_events();
$found = false;
foreach ($events as $i => $ev) {
    if (($ev['id'] ?? null) === $record['id']) {
        $events[$i] = $record;
        $found = true;
        break;
    }
}
if (!$found) {
    $events[] = $record;
}

try {
    save_events($events);
} catch (\Throwable $ex) {
    error_log('イベント保存失敗: ' . $ex->getMessage());
    back_to_events('保存に失敗しました: ' . $ex->getMessage(), 'ng', $id);
}

back_to_events($found ? 'イベントを更新しました。' : 'イベントを登録しました。', 'ok');
