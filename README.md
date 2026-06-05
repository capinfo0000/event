# event — 小規模イベントの事前決済（前払い）申込アプリ

BBQ・お花見・スポーツ等の小規模イベント向けに、**参加費を事前決済（前払い）**で集めるための
最小構成の PHP Web アプリです。当日欠席・ドタキャンによる「キャンセル料を後から取り立てられない」
問題を、**先に支払ってもらう**ことで解消します。

## 設計の要点（安全性）

- **クレジットカード情報はこのサーバーで一切扱いません。** カード番号・有効期限・セキュリティ
  コードの入力はすべて **Stripe がホストする決済ページ（Stripe Checkout）** 上で行われ、当方の
  PHP・サーバー・ログには渡りません（PCI DSS 準拠は Stripe 側の責任範囲）。
- **データベースを持ちません。** 参加者の氏名・メールは Stripe 側で収集・保管されます。
  アプリの**参加者管理画面（`/admin/`）は、その Stripe の決済データを読み出して名簿として表示**します。
- このアプリが行うのは「イベント一覧の表示」「申込→Stripe決済ページへの受け渡し」「結果表示」
  「（管理者向け）名簿の閲覧・返金・CSV出力」です。

## 画面・処理

| ファイル | 役割 |
|---|---|
| `public/index.php` | イベント一覧と「申し込む（前払い）」ボタン |
| `public/checkout.php` | Stripe Checkout セッションを作成し決済ページへリダイレクト |
| `public/success.php` | 決済成功後の申込完了ページ |
| `public/cancel.php` | 決済を中断した場合のページ（未請求） |
| `public/policy.php` | キャンセル・返金ポリシーの表示 |
| `public/webhook.php` | （任意）支払い完了を受信しローカルログに記録 |
| `public/admin/index.php` | **参加者管理ダッシュボード**（要 ID＋PW）。名簿・集計・返金 |
| `public/admin/refund.php` | 返金（全額＝キャンセル／一部）の実行 |
| `public/admin/export.php` | 名簿の CSV ダウンロード |
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

## 参加者管理画面（`/admin/`）

申込者の名簿閲覧・返金・CSV出力を行う管理者向け画面です。**自前DBは持たず、Stripe の
決済データを読み出して**表示します（=常に Stripe が最新の正）。

- アクセス: `http://localhost:8000/admin/`
- 認証: `.env` の `ADMIN_USER` / `ADMIN_PASS`（ID＋パスワード）。**必ず推測されにくい値に変更**してください。
- できること:
  - イベントごとの**支払い済み参加者一覧**（氏名・メール・電話・金額・状態）
  - **集計**（申込数・入金合計・返金合計・差引）
  - **返金**＝金額未指定で全額返金（実質キャンセル）、金額指定で一部返金（ポリシーの50%返金等）
  - **CSV ダウンロード**（Excel 文字化け対策の UTF-8 BOM 付き）

> 個人情報を表示するため、本番では HTTPS 必須・強固なパスワードを設定してください。

## 既知の制限

- **定員の自動制御は行いません。** `capacity` は表示・目安用です（DB を持たないため）。
  管理画面の申込数を見て手動管理してください。
- 領収書の発行は **Stripe ダッシュボード**で操作します。

詳しい運用手順は [`docs/運用手順.md`](docs/運用手順.md) を参照してください。
