# event — 自己ホスト型 イベント事前決済アプリ（単独運営者向け）

小規模イベント向けに、参加費を **事前決済（クレジットカード前払い）** または **当日支払い（現金）** で
集める PHP Web アプリです。運営者は **自分の Stripe アカウント** を1つ用意するだけで、
**参加費は自分の口座へ直接入金** されます。自分（または自団体）のイベントを運営するためのツールとして
自己ホストして使う構成です（Stripe Connect は使いません）。

## 設計の要点（安全性）

- **クレジットカード情報はこのサーバーで一切扱いません。** カード入力はすべて Stripe がホストする
  決済ページ（Stripe Checkout）上で行われます（PCI DSS 準拠は Stripe 側の責任範囲）。
- **決済は運営者自身の Stripe アカウントで直接作成します。** `.env` の `STRIPE_SECRET_KEY` に
  自分の Stripe シークレットキー（`sk_...`）を設定するだけです。
- **参加者名簿はDBに持ちません。** 名簿は自分の Stripe（事前=Checkout セッション、
  当日=課金なしの顧客）から都度読み出して表示します。
- DB（SQLite）に保存するのは **運営アカウント・イベント定義** のみです。

## 構成

| パス | 役割 |
|---|---|
| `public/index.php` | ランディング（参加者は主催者発行の申込リンクから申込） |
| `public/apply.php?event_id=…` | 参加申込フォーム（事前/当日の選択・人数・氏名等） |
| `public/checkout.php` | 申込を検証し、運営者の Stripe で Checkout 作成 or 当日申込を記録 |
| `public/success.php` / `cancel.php` / `onsite.php` | 決済成功 / 中断 / 当日申込完了 |
| `public/o.php?t=…` | 公開イベント一覧（参加者に共有する1リンク） |
| `public/policy.php` | キャンセル・返金ポリシー |
| `public/admin/login.php` / `signup.php` / `logout.php` | ログイン・オープン登録（メール＋パスワード） |
| `public/admin/dashboard.php` | 運営者トップ（Stripe キー設定状況） |
| `public/admin/events.php` ほか | イベントの登録・編集・削除（DB保存） |
| `public/admin/index.php` | 参加者名簿（事前決済の返金、当日支払いの集金確認・取消、CSV） |
| `public/admin/account.php` | アカウント設定（表示名・パスワード変更） |
| `public/admin/forgot.php` / `reset.php` | パスワード再設定（メールでリンク送付） |
| `public/tokushoho.php` / `terms.php` / `privacy.php` | 特商法表記・利用規約・プライバシーポリシー（要内容確定） |
| `src/db.php` / `src/tenant.php` / `src/mail.php` | SQLite データ層 / アカウント・認証 / メール送信 |
| `bin/console.php` | 運用CLI（DB初期化・管理者作成） |

## セットアップ（ローカル開発）

前提: PHP 8.1+（`pdo_sqlite` / `curl` / `json` / `mbstring`）, Composer。

```bash
composer install
cp .env.example .env          # 下記の環境変数を設定（自分の Stripe キーを入れる）

# DB を初期化し、ログイン用の管理者を作成
php bin/console.php init
php bin/console.php create-admin you@example.com あなたのパスワード

php -S localhost:8000 -t public
# 運営者: http://localhost:8000/admin/login.php
# 参加者: イベント管理で発行する申込リンク（/apply.php?event_id=…）から申込
```

### .env の主な項目
- `STRIPE_SECRET_KEY` … **自分の** Stripe シークレットキー（`sk_...`）。参加費はこの口座へ直接入金
- `APP_BASE_URL` … 公開URL（success/cancel のリンク生成に使用）
- `DB_PATH` … SQLite の保存先（任意・既定 `data/app.sqlite`）
- `MAIL_FROM` / `MAIL_FROM_NAME` … 送信メールの差出人（再設定・申込確認メール）

## Stripe の準備

1. Stripe ダッシュボードで自分のアカウントを用意（テストは `sk_test_`、本番は `sk_live_`）。
2. 管理画面の「Stripe 設定」で API キーを登録（または `.env` の `STRIPE_SECRET_KEY`）。
3. これでカード決済が使えます。なお、**当日支払い（現金）の申込受付と参加者管理（名簿）も Stripe に記録・取得する**ため、現金のみの運用でも API キーの設定が必要です。

## 支払いフロー

- **事前決済（クレカ）**：申込フォーム → 運営者の Stripe で Checkout 作成 → Stripe決済画面 → 完了。
- **当日支払い（現金）**：決済は発生させず、課金なしの Stripe 顧客として申込を記録 → 当日に会場で集金、
  名簿の「受領にする」ボタンでチェック。
- イベントごとに事前/当日の **有効・無効** と **金額** を別々に設定できます（例：事前¥3,000／当日¥4,000）。

## 既知の制限

- 定員（`capacity`）は申込時点の人数で締め切ります（厳密な在庫ロックではないため、ごく短時間の同時申込では超過の可能性）。
- 領収書の発行は運営者の Stripe ダッシュボードで行います。
- メール送信は `mail()`＋`logs/mail.log` の最小実装です（本番は SMTP 等へ差し替え推奨）。
- 法務ページはテンプレートです。公開前に内容を確定してください。
- 本番運用では HTTPS 必須。`src/` `vendor/` `data/` `.env` は Web 公開領域外に置き（公開フォルダは `public/`）、`data/`（SQLite）はバックアップしてください。

## 関連ドキュメント

- `docs/システム概要.md` … サービスの概要（一言説明・仕組み）
- `docs/開発の記録.md` … 開発の背景・意思決定・課題と対応・方針転換の記録
- `設置手順-CoreServer.txt`（配布ZIP同梱） … 共有サーバーへの設置手順
