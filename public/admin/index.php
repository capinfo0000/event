<?php

/**
 * 参加者管理ダッシュボード（要 ID＋パスワード）。
 *
 * 自前DBは持たず、名簿は Stripe の Checkout セッションから取得する。
 * - イベントを選んで支払い済み参加者の一覧を表示
 * - 各参加者に対して返金（全額＝キャンセル扱い／一部）を実行
 * - CSV ダウンロード
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

require_admin_auth();

$events = load_events();
$selectedId = (string) ($_GET['event_id'] ?? ($events[0]['id'] ?? ''));
$selectedEvent = $selectedId !== '' ? find_event($selectedId) : null;

// 直前の返金操作の結果メッセージ（リダイレクトで引き継ぎ）
$flash = (string) ($_GET['msg'] ?? '');
$flashType = (string) ($_GET['type'] ?? '');

$participants = [];
$fetchError = '';
$paidCount = 0;
$collected = 0;
$refunded = 0;

if ($selectedEvent !== null) {
    try {
        $participants = fetch_event_participants($selectedId);
        $paidCount = count($participants);
        foreach ($participants as $p) {
            $collected += $p['amount'];
            $refunded += $p['amount_refunded'];
        }
    } catch (\Throwable $ex) {
        $fetchError = 'Stripe から名簿を取得できませんでした: ' . $ex->getMessage();
        error_log('参加者取得失敗: ' . $ex->getMessage());
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>参加者管理</title>
    <style>
        :root { --accent: #2563eb; --border: #e5e7eb; --muted: #6b7280; --danger: #dc2626; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.6; color: #1f2937; max-width: 1040px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        h1 { font-size: 1.4rem; }
        .bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin: 16px 0; }
        select, button, input[type=number] { font-size: .95rem; padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; }
        .btn { background: var(--accent); color: #fff; border: none; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
        .btn-ghost { background: #fff; color: var(--accent); border: 1px solid var(--accent); text-decoration: none; display: inline-block; }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: #b91c1c; }
        .stats { display: flex; gap: 12px; flex-wrap: wrap; margin: 12px 0; }
        .stat { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; min-width: 140px; }
        .stat .num { font-size: 1.3rem; font-weight: 700; color: var(--accent); }
        .stat .lbl { font-size: .8rem; color: var(--muted); }
        table { border-collapse: collapse; width: 100%; background: #fff; border-radius: 10px; overflow: hidden; }
        th, td { border-bottom: 1px solid var(--border); padding: 10px 12px; text-align: left; font-size: .9rem; vertical-align: middle; }
        th { background: #f3f4f6; }
        .tag { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .78rem; font-weight: 600; }
        .tag-paid { background: #dcfce7; color: #166534; }
        .tag-partial { background: #fef9c3; color: #854d0e; }
        .tag-refunded { background: #fee2e2; color: #991b1b; }
        .refund-form { display: flex; gap: 6px; align-items: center; }
        .refund-form input { width: 90px; }
        .muted { color: var(--muted); font-size: .85rem; }
        .flash { padding: 10px 14px; border-radius: 8px; margin: 12px 0; }
        .flash-ok { background: #dcfce7; color: #166534; }
        .flash-ng { background: #fee2e2; color: #991b1b; }
        .err { background: #fee2e2; color: #991b1b; padding: 12px 14px; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>参加者管理</h1>
    <p class="muted">名簿は Stripe の決済データから取得しています（このアプリは参加者DBを持ちません）。</p>

    <?php if ($flash !== ''): ?>
        <div class="flash <?= $flashType === 'ok' ? 'flash-ok' : 'flash-ng' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <form method="get" class="bar">
        <label>イベント：
            <select name="event_id" onchange="this.form.submit()">
                <?php foreach ($events as $ev): ?>
                    <option value="<?= e($ev['id']) ?>" <?= $ev['id'] === $selectedId ? 'selected' : '' ?>>
                        <?= e($ev['name'] ?? $ev['id']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button type="submit" class="btn">表示</button></noscript>
        <?php if ($selectedEvent !== null): ?>
            <a class="btn btn-ghost" href="export.php?event_id=<?= e($selectedId) ?>">CSV ダウンロード</a>
        <?php endif; ?>
    </form>

    <?php if ($selectedEvent !== null): ?>
        <p class="muted">
            📅 <?= e($selectedEvent['date'] ?? '') ?>　📍 <?= e($selectedEvent['place'] ?? '') ?>
            <?php if (!empty($selectedEvent['capacity'])): ?>　／ 定員目安: <?= (int) $selectedEvent['capacity'] ?> 名<?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($fetchError !== ''): ?>
        <p class="err"><?= e($fetchError) ?></p>
    <?php elseif ($selectedEvent === null): ?>
        <p class="err">イベントが選択されていません。</p>
    <?php else: ?>
        <div class="stats">
            <div class="stat"><div class="num"><?= $paidCount ?></div><div class="lbl">申込（支払い済み）</div></div>
            <div class="stat"><div class="num"><?= e(format_amount($collected, $selectedEvent['currency'] ?? 'jpy')) ?></div><div class="lbl">入金合計</div></div>
            <div class="stat"><div class="num"><?= e(format_amount($refunded, $selectedEvent['currency'] ?? 'jpy')) ?></div><div class="lbl">返金合計</div></div>
            <div class="stat"><div class="num"><?= e(format_amount(max(0, $collected - $refunded), $selectedEvent['currency'] ?? 'jpy')) ?></div><div class="lbl">差引（手取り目安）</div></div>
        </div>

        <?php if ($paidCount === 0): ?>
            <p class="muted">まだ支払い済みの申込はありません。</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>申込日時</th><th>お名前</th><th>メール</th><th>電話</th>
                        <th>金額</th><th>状態</th><th>キャンセル / 返金</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $p): ?>
                        <?php
                            $cur = $p['currency'];
                            if ($p['fully_refunded']) {
                                $statusHtml = '<span class="tag tag-refunded">全額返金（キャンセル済）</span>';
                            } elseif ($p['amount_refunded'] > 0) {
                                $statusHtml = '<span class="tag tag-partial">一部返金 ' . e(format_amount($p['amount_refunded'], $cur)) . '</span>';
                            } else {
                                $statusHtml = '<span class="tag tag-paid">支払い済み</span>';
                            }
                            $remaining = $p['amount'] - $p['amount_refunded'];
                        ?>
                        <tr>
                            <td class="muted"><?= e(date('Y-m-d H:i', $p['created'])) ?></td>
                            <td><?= e($p['name'] !== '' ? $p['name'] : '（未入力）') ?></td>
                            <td><?= e($p['email']) ?></td>
                            <td><?= e($p['phone']) ?></td>
                            <td><?= e(format_amount($p['amount'], $cur)) ?></td>
                            <td><?= $statusHtml ?></td>
                            <td>
                                <?php if ($p['fully_refunded'] || $remaining <= 0): ?>
                                    <span class="muted">—</span>
                                <?php else: ?>
                                    <form method="post" action="refund.php" class="refund-form"
                                          onsubmit="return confirm('「<?= e(addslashes($p['name'])) ?>」さんへ返金します。よろしいですか？');">
                                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                        <input type="hidden" name="event_id" value="<?= e($selectedId) ?>">
                                        <input type="hidden" name="payment_intent" value="<?= e($p['payment_intent']) ?>">
                                        <?php if (strtolower($cur) === 'jpy'): ?>
                                            <input type="number" name="amount" min="1" max="<?= (int) $remaining ?>"
                                                   placeholder="一部¥" title="一部返金する円。空欄なら全額返金。">
                                        <?php else: ?>
                                            <input type="number" name="amount" step="0.01" min="0.01"
                                                   placeholder="一部" title="一部返金額。空欄なら全額返金。">
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-danger">返金</button>
                                    </form>
                                    <span class="muted">空欄＝全額返金（＝キャンセル）</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <p style="margin-top:24px;"><a href="../index.php">← 申込トップへ</a></p>
</body>
</html>
