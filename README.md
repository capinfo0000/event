# event — 小規模イベントの事前決済（前払い）申込アプリ

BBQ・お花見・スポーツ等の小規模イベント向けに、**参加費を事前決済（前払い）**で集めるための
最小構成の PHP Web アプリです。当日欠席・ドタキャンによる「キャンセル料を後から取り立てられない」
問題を、**先に支払ってもらう**ことで解消します。

## 設計の要点（安全性）

- **クレジットカード情報はこのサーバーで一切扱いません。** カード番号・有効期限・セキュリティ
  コードの入力はすべて **Stripe がホストする決済ページ（Stripe Checkout）** 上で行われ、当方の
  PHP・サーバー・ログには渡りません（PCI DSS 準拠は Stripe 側の責任範囲）。
- **データベースを持ちません。** 参加者の氏名・メールは Stripe 側で収集・保管され、
  **Stripe ダッシュボードの決済一覧がそのまま参加者名簿**になります。
- このアプリが行うのは「イベント一覧の表示」「申込→Stripe決済ページへの受け渡し」「結果表示」だけです。

## 画面・処理

| ファイル | 役割 |
|---|---|
| `public/index.php` | イベント一覧と「申し込む（前払い）」ボタン |
| `public/checkout.php` | Stripe Checkout セッションを作成し決済ページへリダイレクト |
| `public/success.php` | 決済成功後の申込完了ページ |
| `public/cancel.php` | 決済を中断した場合のページ（未請求） |
| `public/policy.php` | キャンセル・返金ポリシーの表示 |
| `public/webhook.php` | （任意）支払い完了を受信しローカルログに記録 |
| `config/events.json` | 提供するイベントの定義（料金・日時・場所） |

## セットアップ（ローカル開発）

前提: PHP 8.1+（`curl` / `json` / `mbstring` 拡張）, Composer。

```bash
# 1. 依存をインストール
composer install

# 2. 環境変数を用意
cp .env.example .env
#   .env を編集し、Stripe ダッシュボードのテスト用秘密鍵 (sk_test_...) を設定

# 3. 開発サーバー起動
php -S localhost:8000 -t public
#   ブラウザで http://localhost:8000 を開く
```

## Stripe の準備

1. [Stripe](https://dashboard.stripe.com/) に個人で登録（日本の個人でも利用可）。
2. **テストモード**の「APIキー」から秘密鍵 `sk_test_...` を取得し `.env` の `STRIPE_SECRET_KEY` に設定。
3. テストカード `4242 4242 4242 4242`（有効期限は未来の任意、CVC任意）で決済フローを確認。
4. 本番運用時は本番キー `sk_live_...` に差し替え、口座情報の登録（入金先）を済ませる。

### Webhook（任意・推奨）

支払い完了をアプリ側で記録したい場合:

```bash
# Stripe CLI を使ってローカルへ転送
stripe listen --forward-to localhost:8000/webhook.php
# 表示される whsec_... を .env の STRIPE_WEBHOOK_SECRET に設定
```

記録は `logs/payments.log`（JSON Lines）に追記されます。**カード情報は含まれません。**

## イベントの追加・編集

`config/events.json` を編集します。`amount` は最小通貨単位（JPY は円そのまま。例: `3000` = ¥3,000）。

```json
{
  "events": [
    { "id": "bbq-2026-summer", "name": "夏のBBQ大会 2026",
      "description": "...", "date": "2026-07-20 11:00",
      "place": "多摩川河川敷", "amount": 3000, "currency": "jpy", "capacity": 20 }
  ]
}
```

## 既知の制限

- **定員の自動制御は行いません。** `capacity` は表示・目安用です（DB を持たないため）。
  小規模運用では Stripe ダッシュボードの申込数を見て手動管理してください。
- 参加者名簿・領収書・返金は **Stripe ダッシュボード**で操作します。

詳しい運用手順は [`docs/運用手順.md`](docs/運用手順.md) を参照してください。
