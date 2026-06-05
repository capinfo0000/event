<?php

/**
 * イベント管理画面（ログイン中テナント専用）。
 * ログインした主催者（共同主催者含む）が自分のイベントを登録・編集・削除できる。
 * イベントは DB（events テーブル）にテナント単位で保存する。
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tenant = require_tenant();

$events = tenant_events($tenant['id']);

// 編集対象（?edit=ID）。新規のときは空のひな形。他テナントのものは編集不可。
$editId = (string) ($_GET['edit'] ?? '');
$editing = $editId !== '' ? find_event($editId) : null;
if ($editing !== null && $editing['tenant_id'] !== $tenant['id']) {
    $editing = null;
}
$form = $editing ?? [
    'id' => '', 'name' => '', 'description' => '', 'date' => '',
    'place' => '', 'amount' => '', 'currency' => 'jpy', 'capacity' => '',
    'amount_onsite' => '', 'allow_prepay' => true, 'allow_onsite' => false,
];

$flash = (string) ($_GET['msg'] ?? '');
$flashType = (string) ($_GET['type'] ?? '');
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>イベント管理</title>
    <style>
        :root { --accent: #2563eb; --border: #e5e7eb; --muted: #6b7280; --danger: #dc2626; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
               line-height: 1.6; color: #1f2937; max-width: 920px; margin: 0 auto; padding: 24px; background: #f9fafb; }
        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.1rem; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px 24px; margin: 16px 0; }
        label { display: block; font-weight: 600; margin: 12px 0 4px; font-size: .9rem; }
        input, textarea { width: 100%; font-size: .95rem; padding: 9px 11px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; }
        textarea { min-height: 60px; resize: vertical; }
        .row { display: flex; gap: 14px; flex-wrap: wrap; }
        .row > div { flex: 1; min-width: 160px; }
        .btn { background: var(--accent); color: #fff; border: none; cursor: pointer; font-weight: 600; padding: 10px 16px; border-radius: 8px; font-size: .95rem; }
        .btn:hover { background: #1d4ed8; }
        .btn-ghost { background: #fff; color: var(--accent); border: 1px solid var(--accent); text-decoration: none; display: inline-block; }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: #b91c1c; }
        table { border-collapse: collapse; width: 100%; background: #fff; border-radius: 10px; overflow: hidden; }
        th, td { border-bottom: 1px solid var(--border); padding: 10px 12px; text-align: left; font-size: .9rem; }
        th { background: #f3f4f6; }
        .muted { color: var(--muted); font-size: .85rem; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .flash { padding: 10px 14px; border-radius: 8px; margin: 12px 0; }
        .flash-ok { background: #dcfce7; color: #166534; }
        .flash-ng { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h1>イベント管理</h1>
    <p class="muted">
        <?= e($tenant['display_name']) ?> さん ／ <a href="dashboard.php">ダッシュボード</a>
        ／ <a href="index.php">参加者管理</a> ／ <a href="logout.php">ログアウト</a>
    </p>
    <?php if (empty($tenant['stripe_account_id'])): ?>
        <div class="flash flash-ng">⚠️ Stripe 未連携です。連携するまで参加者は申込・決済できません。<a href="dashboard.php">連携する</a></div>
    <?php endif; ?>

    <?php if ($flash !== ''): ?>
        <div class="flash <?= $flashType === 'ok' ? 'flash-ok' : 'flash-ng' ?>"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2><?= $editing ? 'イベントを編集' : 'イベントを新規登録' ?></h2>
        <form method="post" action="event_save.php">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="id" value="<?= e((string) $form['id']) ?>">

            <label>イベント名 <span class="muted">必須</span></label>
            <input type="text" name="name" required maxlength="100" value="<?= e((string) $form['name']) ?>" placeholder="夏のBBQ大会 2026">

            <label>説明</label>
            <textarea name="description" maxlength="500" placeholder="食材・ドリンク込み。雨天時は..."><?= e((string) $form['description']) ?></textarea>

            <div class="row">
                <div>
                    <label>日時 <span class="muted">必須</span></label>
                    <input type="text" name="date" required maxlength="50" value="<?= e((string) $form['date']) ?>" placeholder="2026-07-20 11:00">
                </div>
                <div>
                    <label>場所 <span class="muted">必須</span></label>
                    <input type="text" name="place" required maxlength="100" value="<?= e((string) $form['place']) ?>" placeholder="多摩川河川敷">
                </div>
            </div>

            <div class="row">
                <div>
                    <label>事前決済の参加費（1名・最小通貨単位 / JPYは円） <span class="muted">必須</span></label>
                    <input type="number" name="amount" required min="0" step="1" value="<?= e((string) $form['amount']) ?>" placeholder="3000">
                </div>
                <div>
                    <label>当日支払いの参加費（1名）<span class="muted">空欄なら事前と同額</span></label>
                    <input type="number" name="amount_onsite" min="0" step="1" value="<?= e((string) $form['amount_onsite']) ?>" placeholder="4000">
                </div>
            </div>

            <div class="row">
                <div>
                    <label>通貨</label>
                    <input type="text" name="currency" maxlength="10" value="<?= e((string) ($form['currency'] ?: 'jpy')) ?>" placeholder="jpy">
                </div>
                <div>
                    <label>定員目安（申込人数の上限にも使用）</label>
                    <input type="number" name="capacity" min="0" step="1" value="<?= e((string) $form['capacity']) ?>" placeholder="20">
                </div>
            </div>

            <label>受け付ける支払い方法</label>
            <div style="display:flex; gap:20px; margin-top:4px;">
                <label style="font-weight:400; margin:0;"><input type="checkbox" name="allow_prepay" value="1" <?= !empty($form['allow_prepay']) ? 'checked' : '' ?> style="width:auto;"> 事前決済（Stripeで前払い）</label>
                <label style="font-weight:400; margin:0;"><input type="checkbox" name="allow_onsite" value="1" <?= !empty($form['allow_onsite']) ? 'checked' : '' ?> style="width:auto;"> 当日支払い（現地で集金）</label>
            </div>

            <p style="margin-top:16px;">
                <button type="submit" class="btn"><?= $editing ? '更新する' : '登録する' ?></button>
                <?php if ($editing): ?><a class="btn btn-ghost" href="events.php">新規登録に切り替え</a><?php endif; ?>
            </p>
        </form>
    </div>

    <div class="card">
        <h2>登録済みイベント（<?= count($events) ?>件）</h2>
        <?php if (empty($events)): ?>
            <p class="muted">まだイベントがありません。上のフォームから登録してください。</p>
        <?php else: ?>
            <table>
                <thead><tr><th>イベント名</th><th>日時</th><th>場所</th><th>参加費</th><th>申込リンク</th><th>操作</th></tr></thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                        <?php $applyUrl = base_url() . '/apply.php?event_id=' . urlencode($ev['id']); ?>
                        <tr>
                            <td><?= e($ev['name'] ?? '') ?></td>
                            <td class="muted"><?= e($ev['date'] ?? '') ?></td>
                            <td class="muted"><?= e($ev['place'] ?? '') ?></td>
                            <td>
                                事前 <?= e(format_amount((int) ($ev['amount'] ?? 0), $ev['currency'] ?? 'jpy')) ?>
                                <?php if (!empty($ev['allow_onsite'])): ?><br><span class="muted">当日 <?= e(format_amount((int) ($ev['amount_onsite'] ?? 0), $ev['currency'] ?? 'jpy')) ?></span><?php endif; ?>
                            </td>
                            <td><input type="text" readonly value="<?= e($applyUrl) ?>" onclick="this.select()" style="width:200px; font-size:.8rem; padding:4px 6px;"></td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-ghost" href="events.php?edit=<?= e($ev['id']) ?>">編集</a>
                                    <form method="post" action="event_delete.php"
                                          onsubmit="return confirm('「<?= e(addslashes($ev['name'] ?? '')) ?>」を削除します。よろしいですか？（過去の申込・決済データは Stripe に残ります）');">
                                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                        <input type="hidden" name="id" value="<?= e($ev['id']) ?>">
                                        <button type="submit" class="btn btn-danger">削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p><a href="../index.php">← 申込トップへ</a></p>
</body>
</html>
