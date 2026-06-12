<?php

/**
 * 「クレジットカード決済の安全性について」ボタン＋ポップアップ（モーダル）。
 * 参加者の不安解消のため、Stripe のセキュリティを客観的事実ベースで説明する。
 * 任意の公開ページから include して使える自己完結部品（CSS/JS 同梱）。
 */
?>
<button type="button" class="safety-trigger" onclick="document.getElementById('stripeSafetyModal').classList.add('is-open')">
    事前決済について
</button>

<div id="stripeSafetyModal" class="safety-modal" role="dialog" aria-modal="true" aria-labelledby="safetyTitle"
     onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="safety-modal__box">
        <button type="button" class="safety-modal__close" aria-label="閉じる"
                onclick="document.getElementById('stripeSafetyModal').classList.remove('is-open')">×</button>
        <h2 id="safetyTitle">事前決済について</h2>
        <p class="safety-lead">このイベントのカード決済は、世界的な決済代行サービス <strong>Stripe（ストライプ）</strong> を通じて行われます。
        以下は、その安全性を客観的な事実にもとづいて説明したものです。</p>

        <h3>1. カード情報は主催者にも当サイトにも渡りません</h3>
        <p>カード番号・有効期限・セキュリティコード（CVC）の入力は、<strong>Stripe がホストする決済ページ上</strong>で直接行われます。
        主催者のサーバーや当サイトのデータベースを<strong>一切通過せず、保存もされません</strong>。
        そのため、万一このサイトのデータが漏れても、カード情報は含まれません。</p>

        <h3>2. 国際的なセキュリティ基準「PCI DSS 準拠レベル1」</h3>
        <p>Stripe は、クレジットカード業界の国際セキュリティ基準 <strong>PCI DSS の最上位（Service Provider Level 1）</strong>に認定されています。
        これは年間数百万件以上を扱う決済事業者に求められる、最も厳格なレベルです。</p>

        <h3>3. 通信の暗号化とトークン化</h3>
        <p>すべての通信は <strong>TLS（HTTPS）で暗号化</strong>されます。さらにカード情報は「トークン化」され、
        実際のカード番号を直接やり取りしない仕組みになっています。</p>

        <h3>4. 不正利用対策（Radar・3Dセキュア）</h3>
        <p>機械学習による不正検知 <strong>Stripe Radar</strong> や、本人認証の <strong>3Dセキュア（EMV 3-D Secure）</strong>に対応し、
        なりすまし・不正利用のリスクを低減します。</p>

        <h3>5. 世界的な実績と規制対応</h3>
        <p>Stripe は世界中の多数の企業に採用されている大手決済基盤で、各国の金融規制に準拠して運営されています
        （日本でも資金決済・割賦販売に関する法令に対応）。</p>

        <h3>6. 返金・トラブル時の保護</h3>
        <p>支払い後の返金は正規のフローで処理され、記録が残ります。身に覚えのない請求にはカード会社の
        チャージバック（異議申立）制度も利用できます。</p>

        <p class="safety-note">出典・詳細は Stripe 公式の情報をご確認ください：
            <a href="https://stripe.com/jp/privacy" target="_blank" rel="noopener">プライバシー</a> ／
            <a href="https://stripe.com/docs/security" target="_blank" rel="noopener">セキュリティ</a>。<br>
            ※ 本説明は決済代行（Stripe）の仕組みに関する一般的な解説です。
        </p>
    </div>
</div>

<style>
    .safety-trigger { background:#fff; color:var(--accent,#2563eb); border:1px solid var(--accent,#2563eb);
        border-radius:8px; padding:8px 14px; font-weight:600; cursor:pointer; font-size:.9rem; }
    .safety-trigger:hover { background:#eff6ff; }
    .safety-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55);
        z-index:1000; align-items:flex-start; justify-content:center; padding:24px; overflow-y:auto; }
    .safety-modal.is-open { display:flex; }
    .safety-modal__box { background:#fff; border-radius:14px; max-width:600px; width:100%;
        padding:28px 28px 24px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.3); line-height:1.8; }
    .safety-modal__box h2 { font-size:1.25rem; margin:0 0 12px; }
    .safety-modal__box h3 { font-size:1rem; margin:18px 0 4px; color:#1f2937; }
    .safety-modal__box p { margin:4px 0; font-size:.95rem; color:#374151; }
    .safety-lead { color:#4b5563 !important; }
    .safety-note { margin-top:18px !important; font-size:.82rem !important; color:#6b7280 !important;
        border-top:1px solid #e5e7eb; padding-top:12px; }
    .safety-note a { color:var(--accent,#2563eb); }
    .safety-modal__close { position:absolute; top:10px; right:14px; background:none; border:none;
        font-size:1.6rem; line-height:1; cursor:pointer; color:#6b7280; }
    .safety-modal__close:hover { color:#111; }
    @media (max-width: 480px) {
        .safety-modal { padding: 10px; align-items: flex-start; }
        .safety-modal__box { padding: 20px 18px 18px; border-radius: 12px; }
        .safety-modal__box h2 { font-size: 1.1rem; padding-right: 28px; }
    }
</style>
<script>
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var m = document.getElementById('stripeSafetyModal');
            if (m) m.classList.remove('is-open');
        }
    });
</script>
