<?php

/**
 * イベントの新規登録・更新を DB に保存する（ログイン中テナント単位）。
 * 管理画面（events.php）からの POST のみを受ける。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

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
// datetime-local は「2026-07-20T11:00」の形で届く。表示・既存形式に合わせて T を空白へ。
$date     = str_replace('T', ' ', trim((string) ($_POST['date'] ?? '')));
$place    = trim((string) ($_POST['place'] ?? ''));
$amount   = (string) ($_POST['amount'] ?? '');
$amountOnsite = trim((string) ($_POST['amount_onsite'] ?? ''));
// 通貨は日本円のみ（フォームからは受け取らず固定）
$currency = 'jpy';
$capacity = trim((string) ($_POST['capacity'] ?? ''));
$allowPrepay = !empty($_POST['allow_prepay']);
$allowOnsite = !empty($_POST['allow_onsite']);

// 料金タイプ: flat（一律）/ gender（男女別）。
$pricingMode = ((string) ($_POST['pricing_mode'] ?? 'flat')) === 'gender' ? 'gender' : 'flat';
$priceTiersJson = '';

/** 男女別の1性別ぶんの金額を検証して返す（当日空欄は事前と同額）。 */
$parseGender = static function (string $label, string $prepayKey, string $onsiteKey) use ($id): array {
    $prepayRaw = trim((string) ($_POST[$prepayKey] ?? ''));
    $onsiteRaw = trim((string) ($_POST[$onsiteKey] ?? ''));
    if (!ctype_digit($prepayRaw)) {
        back_to_events($label . 'の事前決済の金額は0以上の整数で入力してください。', 'ng', $id);
    }
    if ($onsiteRaw !== '' && !ctype_digit($onsiteRaw)) {
        back_to_events($label . 'の当日支払いの金額は0以上の整数で入力してください。', 'ng', $id);
    }
    $prepay = (int) $prepayRaw;
    $onsite = $onsiteRaw !== '' ? (int) $onsiteRaw : $prepay;
    return ['label' => $label, 'amount' => $prepay, 'amount_onsite' => $onsite];
};

if ($pricingMode === 'gender') {
    $tiers = [
        $parseGender('男性', 'male_prepay', 'male_onsite'),
        $parseGender('女性', 'female_prepay', 'female_onsite'),
    ];
    $priceTiersJson = json_encode($tiers, JSON_UNESCAPED_UNICODE);
    // 男女別のときは単一料金は使わないが、NOT NULL 列のため 0 を入れておく。
    $amount = '0';
    $amountOnsite = '';
}

// 追加の入力項目（名前・年齢など）。ラベル・種別・必須をそれぞれ対で受け取る。
$cfLabels = (array) ($_POST['cf_label'] ?? []);
$cfTypes  = (array) ($_POST['cf_type'] ?? []);
$cfReqs   = (array) ($_POST['cf_required'] ?? []);
$customFields = [];
foreach ($cfLabels as $i => $rawLabel) {
    $label = trim((string) $rawLabel);
    if ($label === '') {
        continue; // 空ラベルの行はスキップ
    }
    $type = (string) ($cfTypes[$i] ?? 'text');
    if (!in_array($type, ['text', 'number', 'tel', 'textarea'], true)) {
        $type = 'text';
    }
    $customFields[] = [
        'label'    => mb_substr($label, 0, 40),
        'type'     => $type,
        'required' => ((string) ($cfReqs[$i] ?? '0')) === '1',
    ];
    if (count($customFields) >= 20) {
        break; // 上限20項目
    }
}
$customFieldsJson = $customFields !== [] ? json_encode($customFields, JSON_UNESCAPED_UNICODE) : null;

// 入力チェック
if ($name === '' || $date === '' || $place === '') {
    back_to_events('イベント名・日時・場所は必須です。', 'ng', $id);
}
if ($amount === '' || !ctype_digit($amount)) {
    back_to_events('事前決済の参加費は0以上の整数（最小通貨単位）で入力してください。', 'ng', $id);
}
if ($amountOnsite !== '' && !ctype_digit($amountOnsite)) {
    back_to_events('当日支払いの参加費は0以上の整数で入力してください。', 'ng', $id);
}
if (!$allowPrepay && !$allowOnsite) {
    back_to_events('支払い方法を少なくとも1つ選んでください（事前決済／当日支払い）。', 'ng', $id);
}

// 日時は年月日が判定できる形式であることを確認（カレンダー入力なら常に満たす）
if (event_month($date) === null) {
    back_to_events('日時は「2026-07-20」のように開催年月日が分かる形式で入力してください。', 'ng', $id);
}

$data = [
    'name'          => mb_substr($name, 0, 100),
    'description'   => mb_substr($desc, 0, 500),
    'date'          => mb_substr($date, 0, 50),
    'place'         => mb_substr($place, 0, 100),
    'amount'        => (int) $amount,
    // 当日料金は未指定なら事前と同額にフォールバック
    'amount_onsite' => $amountOnsite !== '' ? (int) $amountOnsite : (int) $amount,
    'currency'      => 'jpy',
    'capacity'      => ($capacity !== '' && ctype_digit($capacity)) ? (int) $capacity : 0,
    'allow_prepay'  => $allowPrepay,
    'allow_onsite'  => $allowOnsite,
    'price_tiers'   => $priceTiersJson,
    'custom_fields' => $customFieldsJson,
];

try {
    if ($id !== '') {
        // 既存イベント：自分の所有か確認してから更新
        $existing = find_event($id);
        if ($existing === null || $existing['tenant_id'] !== $tenant['id']) {
            back_to_events('対象イベントが見つかりません。', 'ng');
        }
        update_event($tenant['id'], $id, $data);
        back_to_events('イベントを更新しました。', 'ok');
    } else {
        // 濫用対策: 短時間の大量作成による DB 肥大化（DoS）を IP 単位で抑止（作成のみ）。
        if (!rate_limit_check('event_create', 30, 3600)) {
            back_to_events('短時間に多くのイベントを作成しています。時間をおいてから再度お試しください。', 'ng');
        }
        // デモは共有アカウントのため、保有イベント数に上限を設けて肥大化を防ぐ。
        if (is_demo_tenant($tenant) && tenant_event_count($tenant['id']) >= 12) {
            back_to_events('デモではイベント数の上限に達しました。既存のイベントを削除してからお試しください。', 'ng');
        }
        create_event($tenant['id'], $data);
        back_to_events('イベントを登録しました。', 'ok');
    }
} catch (\Throwable $ex) {
    error_log('イベント保存失敗: ' . $ex->getMessage());
    back_to_events('保存に失敗しました: ' . $ex->getMessage(), 'ng', $id);
}
