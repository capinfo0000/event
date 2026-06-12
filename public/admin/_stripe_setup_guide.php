<?php

/**
 * 「制限付きキーの作り方（詳細手順）」ボタン＋ポップアップ。
 * 実際のダッシュボードのスクショは秘密鍵が写るため使わず、Stripe風のUIを
 * 再現したミニ図解で手順を示す自己完結部品（CSS/JS 同梱）。
 */
?>
<button type="button" class="btn btn--ghost sg-trigger"
        onclick="document.getElementById('rkGuideModal').classList.add('is-open')">
    制限付きキーの作り方（詳細手順）
</button>

<div id="rkGuideModal" class="sg-modal" role="dialog" aria-modal="true"
     onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="sg-box">
        <button type="button" class="sg-close" aria-label="閉じる"
                onclick="document.getElementById('rkGuideModal').classList.remove('is-open')">×</button>
        <h2>制限付きキー（rk_）の作り方</h2>
        <p class="sg-muted">権限を絞ったキーです。万一漏れても被害を限定できます。テスト環境（sandbox）でそのまま作れます。</p>

        <!-- STEP 1 -->
        <div class="sg-step">
            <div class="sg-no">1</div>
            <div class="sg-body">
                <h3>APIキー画面を開く</h3>
                <p>右上の <strong>⚙（設定）</strong> →「<strong>開発者</strong>」→「<strong>APIキーの管理</strong>」。</p>
                <div class="sg-mock sg-bc">⚙ 設定 <span>›</span> 開発者 <span>›</span> APIキー</div>
                <p>「制限付きのキー」の右上 <strong>＋ 制限付きのキーを作成</strong> を押す。</p>
                <div class="sg-mock"><span class="sg-btn">＋ 制限付きのキーを作成</span></div>
            </div>
        </div>

        <!-- STEP 2 -->
        <div class="sg-step">
            <div class="sg-no">2</div>
            <div class="sg-body">
                <h3>テンプレートを選ぶ</h3>
                <p>「<strong>One-time payments</strong>」を選択 → <strong>続ける</strong>。</p>
                <div class="sg-mock">
                    <div class="sg-tmpl on">☑ One-time payments<br><small>チェックアウト/決済リンク等での支払い受付</small></div>
                    <div class="sg-tmpl">Recurring subscriptions and billing</div>
                    <div class="sg-tmpl">In-person payments with Terminal</div>
                </div>
            </div>
        </div>

        <!-- STEP 3 -->
        <div class="sg-step">
            <div class="sg-no">3</div>
            <div class="sg-body">
                <h3>キーの名前を入力</h3>
                <div class="sg-mock"><div class="sg-input">event-app</div></div>
            </div>
        </div>

        <!-- STEP 4 -->
        <div class="sg-step">
            <div class="sg-no">4</div>
            <div class="sg-body">
                <h3>権限を設定（下記だけ／他は「なし」）</h3>
                <div class="sg-mock">
                    <div class="sg-cat">Core</div>
                    <div class="sg-prow"><span>Charges and Refunds</span><span class="sg-perm"><i>なし</i><i>読取</i><i class="on">書込</i></span></div>
                    <div class="sg-prow"><span>Customers</span><span class="sg-perm"><i>なし</i><i>読取</i><i class="on">書込</i></span></div>
                    <div class="sg-prow"><span>Payment Intents</span><span class="sg-perm"><i>なし</i><i class="on">読取</i><i>書込</i></span></div>
                    <div class="sg-cat">Accounts</div>
                    <div class="sg-prow"><span>Accounts</span><span class="sg-perm"><i>なし</i><i class="on">読取</i></span></div>
                    <div class="sg-cat">Checkout Sessions</div>
                    <div class="sg-prow"><span>Checkout Sessions</span><span class="sg-perm"><i>なし</i><i>読取</i><i class="on">書込</i></span></div>
                </div>
                <p class="sg-muted">※「Accounts＝読取」は特に忘れずに（無いと接続確認で弾かれます）。項目は Ctrl+F で検索すると速い。</p>
            </div>
        </div>

        <!-- STEP 5 -->
        <div class="sg-step">
            <div class="sg-no">5</div>
            <div class="sg-body">
                <h3>作成してトークンをコピー</h3>
                <p>一番下の <strong>キーを作成</strong> → 表示される <strong>rk_test_… の長い文字</strong>をコピー。</p>
                <div class="sg-mock sg-token"><code>rk_test_51Teq…UuJCFWm</code><span class="sg-btn sg-btn--ghost">コピー</span></div>
            </div>
        </div>

        <!-- STEP 6 -->
        <div class="sg-step">
            <div class="sg-no">6</div>
            <div class="sg-body">
                <h3>このページに貼り付けて確認</h3>
                <p>下の「Stripe 秘密鍵」欄に貼り付け → <strong>接続確認</strong>。✅「接続成功」でOK。</p>
                <div class="sg-mock"><div class="sg-input">rk_test_••••••••</div>
                    <span class="sg-btn">保存する</span> <span class="sg-btn sg-btn--ghost">接続確認</span></div>
                <p class="sg-muted">※ 権限エラーが出たら、表示された権限（例：Checkout Sessions Read／Accounts）を追加して再確認。</p>
            </div>
        </div>
    </div>
</div>

<style>
    .sg-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:1000;
        align-items:flex-start; justify-content:center; padding:24px; overflow-y:auto; }
    .sg-modal.is-open { display:flex; }
    .sg-box { background:#fff; border-radius:14px; max-width:620px; width:100%; padding:26px 26px 22px;
        position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .sg-box h2 { font-size:1.25rem; margin:0 0 6px; }
    .sg-box h3 { font-size:1rem; margin:0 0 6px; }
    .sg-close { position:absolute; top:10px; right:14px; background:none; border:none; font-size:1.6rem;
        line-height:1; cursor:pointer; color:#6b7280; }
    .sg-muted { color:#6b7280; font-size:.85rem; }
    .sg-step { display:flex; gap:14px; padding:14px 0; border-top:1px solid #e5e7eb; }
    .sg-step:first-of-type { border-top:none; }
    .sg-no { flex:0 0 30px; height:30px; border-radius:50%; background:var(--accent,#2563eb); color:#fff;
        display:flex; align-items:center; justify-content:center; font-weight:700; }
    .sg-body { flex:1; min-width:0; }
    .sg-body p { margin:4px 0; font-size:.92rem; }
    .sg-mock { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; margin:8px 0; font-size:.85rem; }
    .sg-bc span { color:#9ca3af; margin:0 4px; }
    .sg-btn { display:inline-block; background:var(--accent,#2563eb); color:#fff; border-radius:7px;
        padding:5px 12px; font-size:.82rem; font-weight:700; }
    .sg-btn--ghost { background:#fff; color:var(--accent,#2563eb); border:1px solid var(--accent,#2563eb); }
    .sg-tmpl { border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px; margin:4px 0; background:#fff; color:#6b7280; }
    .sg-tmpl.on { border-color:var(--accent,#2563eb); color:#111; font-weight:700; box-shadow:0 0 0 2px rgba(37,99,235,.15); }
    .sg-tmpl small { font-weight:400; color:#6b7280; }
    .sg-input { display:inline-block; min-width:200px; background:#fff; border:1px solid #cbd5e1; border-radius:7px; padding:6px 10px; color:#334155; }
    .sg-cat { font-weight:700; margin:8px 0 4px; color:#111; }
    .sg-prow { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:5px 0; border-top:1px dashed #e5e7eb; }
    .sg-perm i { font-style:normal; display:inline-block; border:1px solid #e5e7eb; border-radius:6px; padding:2px 8px; margin-left:4px; color:#94a3b8; font-size:.78rem; }
    .sg-perm i.on { background:var(--accent,#2563eb); color:#fff; border-color:var(--accent,#2563eb); font-weight:700; }
    .sg-token { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .sg-token code { color:#7c3aed; word-break:break-all; }
    @media (max-width:480px){ .sg-modal{ padding:10px; } .sg-box{ padding:20px 16px; } .sg-prow{ flex-wrap:wrap; } }
</style>
<script>
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var m = document.getElementById('rkGuideModal');
            if (m) m.classList.remove('is-open');
        }
    });
</script>
