# event — マルチテナント型 イベント事前決済アプリ

小規模イベント向けに、参加費を **事前決済（前払い）** または **当日支払い** で集める PHP Web アプリです。
複数の主催者（テナント）が各自のアカウントでイベントを運営でき、**入金は各主催者の Stripe アカウントへ直接** 入ります。
当日欠席・ドタキャンで「キャンセル料を後から取り立てられない」問題を、前払い運用で解消します。

## 設計の要点（安全性）

- **クレジットカード情報はこのサーバーで一切扱いません。** カード入力はすべて Stripe がホストする
  決済ページ（Stripe Checkout）上で行われます（PCI DSS 準拠は Stripe 側の責任範囲）。
- **主催者の Stripe 秘密鍵も預かりません。** 連携は **Stripe Connect（OAuth）** で行い、当方は
  接続アカウントID（`acct_...`）だけを保持します。決済は各主催者の接続アカウント上で作成します。
- **参加者名簿はDBに持ちません。** 名簿は各テナントの Stripe（事前=Checkout セッション、
  当日=課金なしの顧客）から都度読み出して表示します。
- DB（SQLite）に保存するのは **主催者アカウント・招待・イベント定義** のみです。

## 構成

| パス | 役割 |
|---|---|
| `public/index.php` | ランディング（参加者は主催者発行の申込リンクから申込） |
| `public/apply.php?event_id=…` | 参加申込フォーム（事前/当日の選択・人数・氏名等） |
| `public/checkout.php` | 申込を検証し、主催者の接続アカウントで Checkout 作成 or 当日申込を記録 |
| `public/success.php` / `cancel.php` / `onsite.php` | 決済成功 / 中断 / 当日申込完了 |
| `public/policy.php` | キャンセル・返金ポリシー |
| `public/admin/login.php` / `signup.php` / `logout.php` | 主催者ログイン・招待制サインアップ |
| `public/admin/dashboard.php` | 主催者トップ（Stripe 連携状況） |
| `public/admin/connect.php` / `connect_callback.php` | Stripe Connect 連携（OAuth） |
| `public/admin/events.php` ほか | イベントの登録・編集・削除（テナント別・DB保存） |
| `public/admin/index.php` | 参加者名簿（事前決済の返金、当日支払いの集金確認・取消、CSV） |
| `public/admin/invites.php` | 招待コード発行（プラットフォーム管理者のみ） |
| `src/db.php` / `src/tenant.php` | SQLite データ層 / アカウント・招待・認証 |
| `bin/console.php` | 運用CLI（DB初期化・管理者作成・招待発行） |

## セットアップ（ローカル開発）

前提: PHP 8.1+（`pdo_sqlite` / `curl` / `json` / `mbstring` / `sodium`）, Composer。

```bash
composer install
cp .env.example .env          # 下記の環境変数を設定

# DB を初期化し、プラットフォーム管理者を作成、招待を発行
php bin/console.php init
php bin/console.php create-admin you@example.com あなたのパスワード
php bin/console.php make-invite you@example.com   # 表示される招待URLから主催者登録

php -S localhost:8000 -t public
# 参加者: 主催者がイベント管理で発行する申込リンク（/apply.php?event_id=…）から申込
# 主催者: http://localhost:8000/admin/login.php
```

### .env の主な項目
- `STRIPE_SECRET_KEY` … プラットフォームの秘密鍵（Connect 経由の決済作成に使用）
- `STRIPE_CONNECT_CLIENT_ID` … Connect の `client_id`（`ca_...`）
- `APP_BASE_URL` … 公開URL（Connect の戻り先・success/cancel に使用）
- `DB_PATH` … SQLite の保存先（任意・既定 `data/app.sqlite`）

## Stripe Connect の準備（プラットフォーム側）

1. Stripe ダッシュボードで **Connect を有効化** し、**`client_id`（`ca_...`）** を取得。
2. OAuth の **リダイレクトURI** に `{APP_BASE_URL}/admin/connect_callback.php` を登録。
3. `.env` に `STRIPE_SECRET_KEY` と `STRIPE_CONNECT_CLIENT_ID` を設定。
4. 主催者はダッシュボードの「Stripe と連携する」から自分のアカウントを接続（テスト時はテストモードで）。

## 支払いフロー

- **事前決済**：申込フォーム → 主催者の接続アカウントで Checkout 作成 → Stripe決済画面 → 完了。
- **当日支払い**：決済は発生させず、課金なしの Stripe 顧客として申込を記録 → 当日に会場で集金、
  名簿の「集金確認済み」ボタンでチェック。
- イベントごとに事前/当日の **有効・無効** と **金額** を別々に設定できます（例：事前¥3,000／当日¥4,000）。

## 既知の制限

- **定員の自動制御は行いません**（`capacity` は表示・申込人数の上限目安）。
- 領収書の発行は各主催者の Stripe ダッシュボードで行います。
- 本番運用では HTTPS 必須。`data/`（SQLite）は Web 公開領域外に置き、バックアップしてください。
