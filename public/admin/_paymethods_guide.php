<?php

/**
 * 「PayPay 等を有効にする手順（詳細）」ボタン＋ポップアップ。
 * Stripe ダッシュボードの「決済手段（支払い方法）」での有効化手順を、
 * Stripe風UIのミニ図解で示す。スタイルは _stripe_setup_guide.php の .sg- を流用。
 */
?>
<button type="button" class="btn btn--ghost sg-trigger"
        onclick="document.getElementById('pmGuideModal').classList.add('is-open')">
    PayPay 等を有効にする手順（詳細）
</button>

<div id="pmGuideModal" class="sg-modal" role="dialog" aria-modal="true"
     onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="sg-box">
        <button type="button" class="sg-close" aria-label="閉じる"
                onclick="document.getElementById('pmGuideModal').classList.remove('is-open')">×</button>
        <h2>PayPay・コンビニ払い等を有効にする手順</h2>
        <p class="sg-muted">決済画面には「Stripe で有効にした支払い方法」が自動で表示されます。PayPay 等は既定でオフのことがあるため、ダッシュボードで有効化します（テスト環境でも可）。</p>

        <div class="sg-step">
            <div class="sg-no">1</div>
            <div class="sg-body">
                <h3>「決済手段」設定を開く</h3>
                <p>右上 <strong>⚙設定</strong> →「サービス・プロダクト設定」の <strong>Payments（決済・チェックアウト・決済手段）</strong> →「<strong>決済手段</strong>」。</p>
                <div class="sg-mock sg-bc">⚙ 設定 <span>›</span> Payments <span>›</span> 決済手段</div>
                <p class="sg-muted">※ 下の「支払い方法（テスト）を開く」ボタンからも直接開けます。</p>
            </div>
        </div>

        <div class="sg-step">
            <div class="sg-no">2</div>
            <div class="sg-body">
                <h3>一覧から「PayPay」を探す</h3>
                <p>「デジタルウォレット」タイプ・地域「日本」にあります。検索枠で <strong>PayPay</strong> と入力すると速いです。</p>
                <div class="sg-mock">
                    <div class="sg-prow"><span>　Apple Pay</span><span><i class="on" style="font-style:normal;border-radius:6px;padding:2px 8px;background:#dcfce7;color:#166534;">有効</i></span></div>
                    <div class="sg-prow"><span>　Google Pay</span><span><i class="on" style="font-style:normal;border-radius:6px;padding:2px 8px;background:#dcfce7;color:#166534;">有効</i></span></div>
                    <div class="sg-prow"><span><strong>　PayPay</strong></span><span><i style="font-style:normal;border-radius:6px;padding:2px 8px;border:1px solid #cbd5e1;color:#64748b;">無効</i></span></div>
                </div>
            </div>
        </div>

        <div class="sg-step">
            <div class="sg-no">3</div>
            <div class="sg-body">
                <h3>PayPay を有効にする</h3>
                <p>PayPay の行をクリック（または右の <strong>…</strong>）→ <strong>有効にする</strong> を押す。</p>
                <div class="sg-mock sg-token">
                    <span>PayPay（デジタルウォレット／日本）</span>
                    <span class="sg-btn">有効にする</span>
                </div>
                <p class="sg-muted">※ 利用には「通貨＝日本円・日本のアカウント」などStripe側の条件があります。テストでは <i style="font-style:normal;border-radius:6px;padding:1px 7px;background:#dcfce7;color:#166534;">プレビューで有効</i> と表示される場合がありますが、テスト決済は可能です。</p>
            </div>
        </div>

        <div class="sg-step">
            <div class="sg-no">4</div>
            <div class="sg-body">
                <h3>必要なら他の方法も有効化</h3>
                <p><strong>コンビニ決済（Konbini）</strong>・<strong>銀行振込</strong> なども同じ手順で有効にできます。</p>
            </div>
        </div>

        <div class="sg-step">
            <div class="sg-no">5</div>
            <div class="sg-body">
                <h3>完了（アプリ側の作業は不要）</h3>
                <p>有効にした方法は、このアプリの決済画面に<strong>自動で表示</strong>されます。コード変更や再設定は要りません。</p>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.sg-modal.is-open').forEach(function (m) { m.classList.remove('is-open'); });
        }
    });
</script>
