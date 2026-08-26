# 変更履歴（Changelog）

このリポジトリの全コミットを新しい順に記録したもの。チャットでの依頼はほぼ1コミット＝1対応として反映されています。
（生のチャット全文は保存していませんが、各対応の内容・意図はコミットメッセージに残しています。）

- 総コミット数: 93
- ブランチ: claude/loving-hawking-m22ci7
- 生成日時（コミット基準の最新）: 2026-08-03

---

## 2026-08-03 01:40 — security/ui: signupは有効招待なしでloginへ転送＋申込ページを外部遷移ゼロに

- signup.php: 有効な招待コードが無いアクセスは登録画面を見せず login.php へ 301/302 転送
  （登録画面の存在自体を隠す）。
- apply.php: 唯一残っていた「キャンセルポリシー」への別ページリンクをページ内モーダル化。
  申込ページからフロー外（TOP・一覧・他ページ）へ遷移する導線をゼロにした（配布リンク前提）。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 8860255)

## 2026-08-03 01:35 — fix: TOPページの「無料で新規登録」導線を撤去（登録は招待制）

index.php の主催者向けカードから signup へのリンクを削除し、ログインのみに。
アカウント登録は管理者発行の招待制である旨を明記（signup.php は招待必須で既に実装済み）。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit f80e36c)

## 2026-08-01 16:40 — harden: HTTPS強制を.htaccessに同梱＋バックアップスクリプト＋APP_KEY分離保管の明記

- .htaccess: http→https の301強制（localhost/127.* は除外しループ防止）。HSTSはコード側で送出済み。
  上書きデプロイでも消えないよう同梱。
- bin/backup.sh: 既定は機密除外バックアップ、ENCRYPT=1 でパスワード付き暗号化ZIP。
- docs: バックアップ時は APP_KEY を鍵ファイルと別保管（同時流出で復号されるため）を明記。

コード外（環境変数の設定・Cloudflareでの鍵取得）は自動化不可のため運用手順に委ねる。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 4bdd9ad)

## 2026-08-01 16:10 — ui: 受け付ける支払い方法をタグ(チップ)風の見た目に統一

事前決済/当日支払いのチェックを入力項目タグと同じチップUIに（選択でアクセント色）。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 721d308)

## 2026-08-01 16:09 — ui: 定員目安を数値入力からプルダウン(select)に

制限なし＋5〜500名の選択肢。既存の任意値も選択肢に含める。event_save は数値受けのまま互換。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 45dd816)

## 2026-08-01 16:07 — ui: 入力項目タグの説明文を削除

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 9eb5265)

## 2026-08-01 15:59 — ui/fix: 申込リンクのタップコピーを確実化＋タグを横並びに＋アセットにキャッシュ更新(?v=3)

- 申込リンク: 「📋 タップでコピー＋全文折り返し表示」をインラインスタイルで実装（CSSキャッシュに
  依存せず表示・コピー動作。JSは js-copy）。見切れ解消。
- 入力項目タグ(氏名/氏名フリガナ/年齢/紹介者): インラインで横並び(flex)に。CSS未反映でも一列表示。
- 全ページの app.css/app.js に ?v=3 を付与し、旧アセットのブラウザキャッシュを更新
  （＝新CSS/JSが確実に読まれ、コピー不発・縦積みを解消）。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 3c00690)

## 2026-08-01 15:31 — ui: 申込リンクをタップでコピー＋全文折り返し表示に

イベント一覧の申込リンクが狭い入力欄で見切れていた問題を解消。全文を折り返して表示し、
タップでクリップボードにコピー（Clipboard API・非対応環境は execCommand フォールバック、
「✓ コピーしました」表示）。汎用の .js-copy / .copy-link を追加。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit fa5bc8a)

## 2026-08-01 08:50 — docs: 漏えい時に起こり得ること（影響範囲の早見表）を手順書に追記

鍵種別(rk_/sk_・test/live)ごとの「できること/できないこと」、お金が抜かれる唯一の筋道
(Payout乗っ取り=sk_のリスク・rk_で封じられる)、二次被害(PII悪用)を早見表として整理。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit b3e0c9b)

## 2026-08-01 08:44 — ui(security): rk_推奨に「送金/入金先の権限は含めない=別口座送金・口座変更は不可」を明記

推奨する制限付きキーの権限には Payouts / External accounts を含めないため、漏えい時も
別口座への送金や入金先変更ができない旨を案内。sk_ は当該権限まで含む点も補足。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 0d8b6c5)

## 2026-08-01 08:41 — ui(security): 制限付きキー(rk_)を明確に推奨するよう強化

Stripe設定の登録カード先頭に「推奨: 制限付きキー(rk_)」の案内を表示し、必要権限だけを
付与する手順を主導線に。sk_（フルアクセス）は「可・非推奨」に格下げ、入力例も rk_ に。
万一の漏えい時の被害最小化を促す。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 9a424cb)

## 2026-08-01 08:35 — security: 重要操作の本人通知メールを追加（乗っ取り検知）

Stripe鍵の登録/変更/削除・2段階認証の有効化/解除・パスワード変更が行われたら、
本人のメールへベストエフォートで通知（日時・アクセス元IP・心当たりが無い場合の対処）。
秘密（鍵・コード）は本文に含めない。攻撃者がアカウントを乗っ取って鍵を差し替えても
本人が気づける。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 44c8367)

## 2026-08-01 08:30 — docs/tooling: APIキー漏えい時のインシデントレスポンス整備

- docs/incident-response-apikey.md: 攻撃者/主催者/管理者/法務の各視点での対応手順を追加
  （即時失効→差し替え、影響範囲の特定、サーバー侵害時の全体対応、通知・再発防止）。
- console.php: 緊急対応コマンドを追加
  - revoke-sessions: 全ログインセッションを失効（セッション窃取・乗っ取り対応）。
  - rotate-app-key: 現行キーで全秘密を復号→新 APP_KEY で再暗号化（APP_KEY漏えい/サーバー
    侵害対応）。再暗号化の復号一致・旧キーでの復号不可をローカル検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit acfe4d6)

## 2026-08-01 08:26 — security(apikey): 漏えい対策の可視化＋保存時ガードを追加

- 鍵の保存先が Web から直接DL可能な場合は保存を中止（公開領域に鍵を置かせない）。
- 監査ログに鍵の種別(full/restricted)と末尾4桁(fp)を記録＝どの鍵かを後から突合可能。
- Stripe設定画面に「現在の状態」を拡充（モード/種別/登録日時）＋注意（sk_liveなら
  rk_への差し替え推奨、APP_KEYが.envなら実環境変数化を推奨）。
- 「万一この鍵が漏えいした場合の影響範囲」カードを追加：対象は当該主催者のStripeのみ、
  されうること（名簿閲覧・返金等）、及ばない範囲（カード番号/他主催者/管理権限）、
  漏えい時の対応（Roll→差し替え→監査ログ確認）を明示。
- crypto: app_key_on_disk()（APP_KEYが.envに平置きか）を追加。

HTTP保存ガード・種別/登録日時/影響範囲の表示・監査ログのfp記録をローカル検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 476e502)

## 2026-07-31 18:36 — security(apikey): 本番エラー詳細の遮断＋鍵登録のHTTPS必須化

- bootstrap: 本番は display_errors=off・汎用例外ハンドラで、未捕捉例外のスタックトレース
  （復号済み Stripe 鍵が引数として載りうる）を画面に出さない。詳細はサーバーログへ。
  APP_DEBUG=1 のときだけ従来の詳細表示（開発用）。
- stripe.php: APIキーの手動登録を HTTPS 接続時のみ受け付け（平文送信防止）。
- .env.example: APP_DEBUG を追記（本番は 0）。

本番で例外が汎用メッセージのみ・秘密文字列が露出しないこと、APP_DEBUG=1で詳細表示を検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit bd606fd)

## 2026-07-31 18:33 — feat(security): 2FA(TOTP)・初回PW強制変更・ログイン抑制強化・セッション束縛を追加

- 2段階認証(TOTP, RFC6238自作): twofa_setup.php で有効化（セットアップキー＋otpauth URI、
  コード確認で有効化）、ログインは password→twofa.php の2段階に。秘密鍵は APP_KEY で暗号化保存。
  RFC6238テストベクトル一致（Google Authenticator 等と互換）。CLI disable-2fa で復旧可。
- 初回パスワード強制変更: 管理者発行アカウントに must_change_password を付与。require_tenant が
  変更ページ以外への遷移を遮断。account.php での変更でも解除。
- ログイン抑制強化: 既存のメール/IP失敗回数制限に加え、IP単位のバースト制限(15回/5分)を追加。
- セッション厳格化: ログイン時の User-Agent とセッションを束縛し不一致で破棄（窃取対策）。
  ログイン確定を complete_tenant_login に集約（ID再生成・UA記録）。

TOTPのRFC一致、強制PW変更フロー（遮断→変更→解除）、2FA有効化＆強制ログイン（正=通過/誤=拒否）を
ローカル検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 073367b)

## 2026-07-31 18:22 — feat: 管理者が初期アカウント（メール＋仮パスワード）を直接発行できるように

- invites.php: 「初期アカウント発行」を追加。メール（＋表示名）を指定すると仮パスワード付き
  アカウントを作成し、その場で一度だけ表示（保存はハッシュのみ）。受け取った人はログイン後、
  アカウント設定でパスワード・表示名を変更する。従来の招待リンク発行も併存。
- ナビ表記を「招待を発行」→「アカウント発行」に変更。
- いずれも require_admin_tenant で管理者専用。監査ログに admin.create_account を記録。

管理者ログイン→初期アカウント発行→表示された仮PWで新主催がログイン成功をローカル検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 0b2179e)

## 2026-07-31 18:19 — feat(security): 新規登録を管理者発行の招待制に変更

- signup.php: 登録に管理者発行の招待コード（?invite=）を必須化。招待が無効/期限切れ/
  使用済みならフォームを出さず登録不可。招待にメール指定があればその宛先のみ許可。
  登録成功時に招待を consume（多重利用防止）。
- login.php: 「新規登録（無料）」導線を撤去し、招待制である旨を明記。
- console.php: 既存アカウントを管理者に昇格する make-admin <email> を追加。

招待なし=フォーム非表示、有効招待=登録可、使用済み招待=登録不可（アカウント未作成）を
ローカル検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 39ccbcc)

## 2026-07-31 18:13 — ui: テキストエリアを入力量に応じて自動で縦に伸ばす

長文入力欄など textarea が、文章が長くなると高さも自動で広がるようにした
（input で scrollHeight に追従・初期値にも反映・overflowY 非表示）。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 347e2cb)

## 2026-07-31 18:11 — feat: 自由項目の追加機能を復活（タグ選択＋自由項目の併用）

- 編集画面に「自由項目」ビルダーを追加（ラベル/種別/必須・行の追加削除）。
  タグ（氏名/氏名フリガナ/年齢/紹介者）に加え、任意の項目を定義できる。
- 自由項目は申込フォームの末尾（メール・紹介者の後）に表示（未知ラベルの slot を post に）。
- 自由項目は必須/任意を選べる（タグは常に必須）。カタログと重複するラベルは無視。
- 自由項目の行は .cf-row でスマホ縦積みにして枠の重なりを防止。

タグ＋自由項目の保存順・申込フォームの並び（氏名→年齢→性別→メール→会社名→アンケート）・
必須/任意の反映をローカル検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit cd5b0ad)

## 2026-07-31 11:18 — feat/ui: 入力項目をタグ選択式に＋固定表示順、スマホの入力欄の重なりを解消

- 入力項目を「タグ（チップ）」で選ぶ方式に変更。選べる項目は 氏名/氏名フリガナ/
  年齢/紹介者。申込フォームの表示順は 氏名→氏名フリガナ→年齢→（性別）→メール→紹介者 に
  固定（性別・メールより前/後を slot で制御）。選んだ項目は必須。全部外すと性別＋メールのみ。
  メールは常に必須、性別は「男女別」料金のときに表示。1申込=1名（人数選択は廃止）。
- 旧・自由記述のカスタム項目UIと標準項目（氏名/電話/人数/備考の固定描画）を撤去。
  checkout はメールのみ必須にし、氏名相当のタグ値を Stripe 顧客名へ採用。
- スマホCSS: 横並び入力(.row)を縦積みにして枠の重なりを解消。タグ(.chip)のスタイル追加。

編集→保存→申込フォームの並び順（pre/性別/メール/post）、全外し=性別+メールのみ、1名固定を
ローカル検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 9d25e6e)

## 2026-07-28 18:04 — fix: 性別ラジオの金額表示を削除＋APP_BASE_URL が localhost なら自動推定

- apply: 性別（男性/女性）ラジオ横の金額表示を撤去（合計欄に正確な額を表示）。
- base_url: APP_BASE_URL が http://localhost 系のときは本番では無効とみなし、
  リクエストのスキーム+ホストから推定。.env に既定の localhost が残っていても
  申込リンクや Stripe の success/cancel が localhost に落ちない。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 2ab5f75)

## 2026-07-28 17:56 — feat/fix: 料金を「一律／男女別」トグル化＋支払いラベル整理＋URL自動推定

- 料金タイプを「一律」「男女別」から選べるトグルに変更。男女別では
  男性/女性それぞれの「事前決済額・当日支払い額」を設定でき、申込画面は
  性別選択に応じて請求額が変わる（男女×事前/当日の4額）。イベント編集画面は
  選択に応じて入力欄を出し分け（一律/男女別）。
- price_tiers を各区分 {label, amount(事前), amount_onsite(当日)} に拡張。
  checkout は選択区分×支払い方法でサーバー側確定（改ざん防止）。旧データは
  当日=事前として後方互換。
- 申込画面の支払い方法ラベルから「（…・¥xxx/名）」のカッコ書きを削除。性別ラジオ
  横には選択中の支払い方法での金額を表示。上部サマリは男女別に事前/当日を表示。
- base_url(): APP_BASE_URL 未設定時はリクエスト(スキーム+ホスト)から推定。設定漏れで
  申込リンクや Stripe の success/cancel が http://localhost に落ちる問題を解消
  （不正Hostは拒否）。
- 旧・自由記述の料金区分UIは男女別トグルに置き換え（保存構造は price_tiers を継続利用）。

料金保存/男女×方式の金額確定/URL自動推定をローカル検証（HTTP＋ロジック）。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 0559f85)

## 2026-07-28 17:38 — fix: イベント保存時の NOT NULL 制約エラー（events.custom_fields）を解消

一部環境の events テーブルで price_tiers/custom_fields 列が NOT NULL（デフォルト
無し）になっており、区分・カスタム未設定のイベント保存で NULL を入れて失敗して
いた。コード側で未設定時に NULL ではなく空文字を保存するよう変更（空文字は読取時
[] と同じ扱い）。あわせて新規スキーマの両列を TEXT NOT NULL DEFAULT '' に統一。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit ead22a6)

## 2026-07-28 17:19 — fix: 事前決済ボタンで画面遷移しない（CSP form-action が Stripe への遷移をブロック）

原因: CSP の form-action 'self' により、checkout.php からの Stripe Checkout
(checkout.stripe.com) へのリダイレクト遷移がブラウザ(Chrome/Safari)でブロック
され、「事前決済を押しても遷移しない」状態になっていた。

- bootstrap: form-action に https://checkout.stripe.com / https://*.stripe.com を許可。
- checkout: 当日支払いの内部リダイレクトを絶対URL(APP_BASE_URL依存)から同一オリジンの
  ルート相対に変更し、設定差や form-action の影響を受けないよう堅牢化。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit d47aa21)

## 2026-07-28 16:54 — feat: 入力項目のカスタム化（名前・年齢など主催者が自由定義／メール・性別は固定）

イベントごとに入力項目（ラベル・種別・必須）を主催者が自由に追加/削除できる。
カスタム項目を設定したイベントは、固定の標準項目（氏名/電話/人数/備考）を出さず、
「メールアドレス（固定）＋性別＝料金区分（設定時）＋定義した項目」だけになる。
1申込=1名。未設定のイベントは従来どおり。

- db: events.custom_fields 列（JSON [{label,type,required}]）を追加。
- bootstrap: event_normalize で custom_fields を復元。decode_custom_fields /
  event_has_custom_fields / extract_custom_meta を追加。create/update が保存。
  fetch_event_participants が各申込のカスタム回答(custom)を返す。
- events.php: 入力項目の動的エディタ（ラベル/種別[文字・数値・電話・長文]/必須）＋JS。
- event_save.php: cf_label[]/cf_type[]/cf_required[] を検証しJSON保存（最大20）。
- apply.php: カスタム設定時はメール＋性別＋定義項目のみ表示（標準項目は非表示）。
  種別に応じ input/textarea を出し、必須制御。
- checkout.php: カスタム回答をサーバー側で検証（必須）・収集し metadata(cf0..)へ格納。
  氏名相当の項目を Stripe 顧客名に採用。メールは常に必須。1名固定。
- index.php/export.php: 名簿・CSV にカスタム回答を表示（"ラベル: 値"）。

保存/復元/必須検証/氏名マッピング/表示復元（ロジック）と、保存→申込フォーム描画
（HTTP: メール固定・性別・カスタム項目のみ・標準項目非表示・1名固定）を検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit b1053b6)

## 2026-07-28 16:42 — feat: 料金区分（男性/女性など）を主催者が自由設定できる機能

イベントごとに「区分名＋金額」を任意個登録でき（例: 男性¥5000/女性¥3000/学生¥2000）、
参加者は申込時に区分を1つ選び、その金額で決済する（1申込=1名）。区分未設定の
イベントは従来どおり（単一料金＋人数選択）動作する。

- db: events.price_tiers 列（JSON配列 [{label,amount}]）を追加。
- bootstrap: event_normalize で tiers を復元。decode_price_tiers / event_has_tiers /
  event_tier_amount を追加。create/update_event が price_tiers を保存。
  fetch_event_participants が参加者の区分(category)を返す。
- events.php: 区分の動的追加/削除エディタ（CSP対応の nonce スクリプト）＋一覧に区分価格表示。
- event_save.php: 区分行(tier_label[]/tier_amount[])を検証しJSON保存（最大10・金額は整数）。
- apply.php: 区分ありなら区分ラジオを表示・人数は1固定・合計は選択区分で更新。
- checkout.php: 区分ありなら金額を「サーバー側の区分定義」から確定（改ざん防止）、
  1名固定、metadata と確認メール・Stripe表示に区分を反映。
- index.php / export.php: 名簿と CSV に区分を表示。
- o.php: 公開一覧でも区分価格を表示。

区分の保存/復元/改ざん拒否/更新/クリア（ロジック）と、編集→保存→一覧→申込フォーム
描画（HTTP）を検証。既存の単一料金イベントの回帰も確認。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 91d4c94)

## 2026-07-27 09:36 — Merge pull request #1 from capinfo0000/claude/loving-hawking-m22ci7

イベント予約・決済アプリ: デモモード追加・セキュリティ全面強化・FTPデプロイ対応
(commit 8e28c75)

## 2026-07-13 07:40 — feat(security): Stripe Connect 必須モードを追加（サーバーが秘密鍵を保持しない）

STRIPE_CONNECT_REQUIRED=1 で有効化するオプトイン（既定 0＝従来の手動鍵登録も可）。
必須モードでは主催者の秘密鍵をサーバーに一切保存させず、OAuth で接続した
アカウント（acct_...）に対してのみ操作する。入金・名簿(PII)・決済は主催者側に分離。

- bootstrap: connect_required() を追加。必須モードでは
  stripe_resolve_tenant/event は手動鍵を無視し接続アカウントのみ使用、未接続なら
  文脈拒否（プラットフォーム鍵へのフォールバックを封じる）。
  stripe_ready_for_tenant/event は接続済みのみ true。
- stripe.php: 必須モードでは秘密鍵の手動登録を無効化し、Connect 接続へ誘導。
- dashboard.php: 未接続時に「Stripe を接続する（Connect）」導線を追加（従来は
  connect.php?action=start へのリンクが画面に無かった）。モーダル文言もモード対応。
- .env.example: STRIPE_CONNECT_REQUIRED を追記。

必須モードのゲート（手動鍵無視・未接続拒否・接続済みは acct 利用）と通常モードの
回帰をローカル検証。OAuth 往復は実 client_id + プラットフォーム鍵での確認が必要。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit fc6471c)

## 2026-07-13 07:34 — security: 鍵 at-rest 保護の強化（APP_KEY を実環境変数で運用可能に）

- ensure_app_key(): APP_KEY を .env へ自動生成する際、ファイル権限を 0600 に絞る。
- .env.example: 最も堅牢な運用として「APP_KEY を .env に書かず実環境変数で渡す」
  方法を明記。実環境変数は .env より優先され、その場合アプリは .env に APP_KEY を
  書かないため、鍵ファイルや .env を読み取られても Stripe 秘密鍵を復号できない。

実環境変数モードで .env 非書込・復号可、自動生成モードで 0600・暗号往復を検証。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 45a3ae3)

## 2026-07-13 07:31 — security: デモ悪用の連鎖（DoS・列挙）を追加で封じる

- event_save: イベント作成に IP 単位のレート制限（30/時・作成のみ）を追加し、
  認証済み攻撃者による大量作成での DB 肥大化を抑止。デモは共有アカウントの
  ため保有イベント数の上限（12件）も設ける。
- login: 空欄デモログインに IP 単位のレート制限（20/時）を追加。連打による
  シード再作成・セッション生成の資源浪費を防ぐ。
- signup: デモ用予約メール（demo@demo.invalid）の通常登録を拒否。

ローカルで上限頭打ち・予約メール拒否を確認。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 5d05650)

## 2026-07-13 07:18 — security: デモモードの Stripe 到達経路を全面遮断（プラットフォーム鍵漏出対策）

デモ（＝鍵を持たないテナント）は、.env にプラットフォーム鍵がある構成だと
env フォールバックに乗ってプラットフォームの本番 Stripe へ到達し得た
（他者PII閲覧・返金・デモ公開イベント経由での実顧客/実決済作成）。
無認証の攻撃者がデモで摩擦ゼロに入れるため、脅威度が高い。塞いだ内容:

- init_stripe() に「文脈拒否」ゲート（stripe_context_denied）を追加。拒否時は
  env 鍵があっても例外にし、意図しないフォールバックを封じる。
- stripe_resolve_tenant()/stripe_resolve_event() はデモ主体を検出したら拒否を
  立てて null を返す（active key も未設定のまま）。呼び出し毎にフラグをリセット。
- stripe_ready_for_tenant()/stripe_ready_for_event() はデモを常に false に。
- connect.php: resolve 前に init_stripe() する構造のため、デモの Connect 連携を
  明示的にブロック。
- .htaccess: 直接配信拒否の拡張子に .key/.pem を追加（鍵ファイルの保険的防御）。

通常テナント（自鍵・env鍵フォールバック）の挙動は不変。ローカルで
13項目のロジック検証＋HTTPスモーク（デモ各画面200・公開イベントの事前決済
ブロック・CSVは制御下の502）を実施し、機能維持と遮断の両立を確認。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 2b54ee2)

## 2026-07-13 07:09 — feat: デモ（ポートフォリオ）モードを追加

ログイン画面でメール・パスワード空欄のまま送信すると、サンプルイベント
入りのデモ用アカウントに入れる。DEMO_MODE=1 のときのみ有効（本番は 0）。

- src/tenant.php: demo_mode_enabled()/is_demo_tenant()/demo_login()/
  demo_seed_events() を追加。デモは固定メール demo@demo.invalid の専用
  テナントで、ログイン毎にサンプル3件へリセット。
- login.php: 空欄デモログイン分岐（CAPTCHA・回数制限は対象外）と
  「デモを見る」導線を追加。
- _app_header.php: デモ中は上部に「デモモード」帯を表示。
- stripe.php: 共有されるデモアカウントでは Stripe 鍵の登録・変更を拒否
  （公開デモに実鍵を置かせない安全策）。
- .env.example: DEMO_MODE を追記（既定 0）。

デモは Stripe 鍵を持たず決済・外部送信は発生しない。書き込みはローカルDBの
自テナントのイベントに限定。ローカルで一連の動作（ログイン→帯表示→シード→
再ログインでリセット→鍵保存ブロック→DEMO_MODE=0で導線消滅）を確認済み。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 75ddd37)

## 2026-07-13 06:37 — UI: TOPページの参加者向け文言と各ページのTOPへ戻る導線を削除

参加者には申込リンクのみ配布する運用のため、参加者がTOP（主催者向け
ランディング）へ到達しないよう整理。
- index.php: 「参加者の方へ」カードを削除（TOPは主催者向けに一本化）
- apply/success/cancel/onsite/_legal_footer/policy: 「← トップへ戻る／
  イベント一覧へ戻る」リンクを削除

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit ae2c10a)

## 2026-07-13 03:46 — deploy: FTPフラット配置向けに docroot→public/ 内部ルーティングを追加

CORESERVER V2 は docroot が /public_html/<サブドメイン> に固定で、SSH の
symlink が張れない FTP 運用ではプロジェクト一式を docroot に置く形になる。
root .htaccess に mod_rewrite の内部転送を追加し、全リクエストを実体のある
public/ 配下へ回す。src/ 等は public/ 配下に無いため 404 で隠蔽される。
SSH 運用（docroot=public/）では root .htaccess は配信されず無害。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 618daae)

## 2026-07-13 03:28 — docs: ファイル名を ASCII 化して文字化けを解消

Windows 等での文字化け対策として docs/ 配下の日本語ファイル名を
ローマ字（ASCII）へリネームし、相互参照リンクも更新。

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015RPn26P51EZLpgyp2cw4rJ

(commit 81eb6f1)

## 2026-06-15 09:29 — 警告を能動チェックに統一＋鍵保存の過剰拒否を解除

- security_warnings: DB/鍵/.env を「実際にWebからDLできる時だけ」警告（.htaccessで403なら警告なし）
- remote_status_cached / file_web_downloadable を追加（APP_BASE_URL基準・1日キャッシュ・SSRF回避）
- set_tenant_stripe_key: 公開フォルダ内保存の即拒否を撤廃（.htaccess保護＋暗号化＋能動警告に一本化）→ DB_PATH未設定でも鍵保存可能に
- 検証: 鍵保存OK / DB公開フォルダ外→警告0 / 露出時のみ警告

(commit be20cb2)

## 2026-06-14 18:26 — 500修正: init_stripeを例外化＋準備判定を「復号できる鍵があるか」に統一

- init_stripe: 鍵なし時 exit(500)→RuntimeException（try/catchで受けて白画面500を回避）
- stripe_ready_for_tenant/event: tenant_has_stripe_key(ファイル存在)→get_tenant_stripe_key(復号可)で判定
- 再デプロイ等でAPP_KEYが失われ鍵が復号不能でも、index/dashboardは500ではなく『要再設定』案内に
- 検証: 復号不能の鍵ファイル状態で index.php=200・案内表示

(commit c552fec)

## 2026-06-14 18:20 — stripe.php(管理)にも「事前決済について」ポップアップを追加（簡易detailsを置換し共通モーダルを利用）


(commit 928bd76)

## 2026-06-14 18:17 — 事前決済(Stripe)の安全性説明をポップアップ化し公開ページに追加

- _prepay_info_modal.php を共通パーツ化（6項目の説明＋公式リンク）
- apply.php: 「事前決済（カード）の安全性について」ボタン→ポップアップ
- o.php(イベント公開ページ): 「事前決済について」ボタン→ポップアップ＋app.js読込
- 検証: 両ページで表示/CSPエラー0

(commit 860d900)

## 2026-06-14 10:46 — 修正: ポリシー編集の事前表示バグ（_app_header の $current と変数衝突）→ $policyText に改名


(commit 9b6dbeb)

## 2026-06-14 10:39 — 主催者ごとのキャンセルポリシー編集機能＋UIアイコン削除

- tenants.cancel_policy 追加。admin/policy_edit.php で主催者が文面編集（空で既定文面）
- policy.php は event_id/t から主催者を判定し、その主催者の文面を表示（無ければ既定・手数料非返金を明記）
- apply.php/o.php のポリシーリンクに event_id/t を付与
- 装飾アイコン（ナビ絵文字・ロゴ・カードタイトル・📅📍・📝・🎉）を削除（ステータス記号は残置）
- ナビに「キャンセルポリシー」追加

(commit 56cf1fb)

## 2026-06-14 10:31 — 参加者管理: 返金してもStripe手数料は返金されない旨を明記（注記＋確認ダイアログ）


(commit 4ac3a62)

## 2026-06-14 10:30 — 監査ログ(audit.log)を追加: 不正/漏えいの痕跡を非公開領域に記録

- audit_log() を追加（公開領域外。日時/IP/UA/操作種別。秘密・フルPIIは記録しない・メールはマスク）
- 記録箇所: login(ok/fail/blocked)/signup/account.password_change/stripe.key.save|clear/refund/csv.export/event.delete/authz.deny/authz.admin_deny/csrf.fail
- .env.example/責任分界ドキュメント更新。検証で鍵非混入を確認

(commit 315e751)

## 2026-06-14 10:24 — 説明責任用ドキュメント追加: 責任分界点・実施済み対策の記録・上流漏えい時の対応・運用チェックリスト


(commit bb1e5ee)

## 2026-06-14 10:21 — 多角的ハードニング: env露出チェックのSSRF修正＋セッション隔離

- env_web_exposed: HTTP_HOST(改ざん可)→APP_BASE_URL固定。HostヘッダによるSSRF/キャッシュ汚染を排除
- session_boot: セッションファイルを公開領域外の private/sessions に隔離（共有ホスティングでのセッション窃取→名簿PII流出を防止）
- 検証: ログイン/ダッシュボード正常・セッション隔離・Host偽装で外部取得しない

(commit 3c893b8)

## 2026-06-14 10:17 — 鍵を常に暗号化保存（平文ディスク保存を廃止）

- ensure_app_key(): APP_KEY未設定なら自動生成して.envへ保存しプロセスにも反映
- set_tenant_stripe_key: 保存前にensure_app_keyを呼び、可能な限りenc:で暗号化保存
- 効果: 鍵ファイル単体(バックアップ/他サイトのファイル読取等)が漏れても、別location(.env)のAPP_KEY無しでは復号不可
- 検証: APP_KEY無し→保存で自動生成＆enc:保存・平文出現なし

(commit 50be3eb)

## 2026-06-14 10:02 — Stripe鍵の保存注記を簡潔な表現に（実装詳細を出さない）


(commit 8188a7d)

## 2026-06-14 09:55 — .env露出警告を実測ベースに変更（誤検知解消）

- env_web_exposed(): 自サイトの /.env を取得し200で実配信される時だけ露出と判定（1日キャッシュ）
- フラット配置で.envは必ず公開フォルダ内になるためパス判定だと常時警告→.htaccessで保護(403/404)なら警告は出ないように
- 取得不能/保護時は警告なし。実際にDLできる時のみ重大警告

(commit 796c410)

## 2026-06-14 09:47 — db: DB_PATH のディレクトリが無ければ自動作成（private/ を手動で作らなくても動く）


(commit 61118cc)

## 2026-06-14 09:24 — モーダル文言を過去版どおりに（要約せず原文表記に統一）


(commit 94cdcf6)

## 2026-06-14 09:20 — モーダルを過去のステップ式デザインに刷新(番号バッジ/テンプレ選択/権限表/コピー欄)

- app.css: .guide__row/.guide__num/.tpl/.perm/.pill/.mockfield 等を追加、モーダルの×をボックス内に
- stripe.php: rk_作成手順とPayPay手順を図入りステップUIに（過去版に準拠）
- dashboard.php: 未設定ポップアップの×をボックス内へ
- 検証: 表示/開閉/CSPエラー0

(commit 5a8ede8)

## 2026-06-14 09:14 — UIをポップアップ化: 制限付きキー作成手順/PayPay有効化手順をモーダル表示、ダッシュボードのStripe未設定もポップアップ(自動表示)に

- app.css: .modal スタイル追加 / app.js: data-modal-open・data-modal-close・data-auto-open・ESC・背景クリックで開閉(CSP準拠・外部JS)
- stripe.php: 2つの詳細手順をモーダル化(ユーザー提供の手順内容を反映)
- dashboard.php: 未設定バナー→ポップアップ(is-openでJS無効でも表示・JSで閉じる)
- 検証: 自動表示/開閉/CSPエラー0

(commit 020cbcd)

## 2026-06-14 09:08 — 接続テストをCheckout Sessions取得に変更(Balance権限不要に)

- stripe_test_key: Balance::retrieve→Checkout\Session::all(limit1)。案内済みの制限付きキー権限だけで成功する
- モード(test/live)は鍵接頭辞から判定。'balance_read不足'エラーを解消

(commit 19bbb7b)

## 2026-06-14 09:00 — 鍵窃取を構造的に防止: 鍵の保存先が公開フォルダ内なら保存を拒否(警告→強制)

- set_tenant_stripe_key: 保存先が DOCUMENT_ROOT 配下なら例外で拒否し設定修正を促す
- stripe.php: 拒否メッセージを表示。当日支払い(鍵不要)は影響なし
- 検証: 公開内→保存拒否(ファイル0)/公開外→保存OK

(commit 6a716c1)

## 2026-06-14 08:54 — Stripe設定ページを旧仕様に復元＋APP_KEY必須をやめてエラー解消

- 鍵保存をDB暗号化→「公開フォルダ外のファイル」方式に変更(APP_KEY任意・あれば暗号化)
  → 'APP_KEY未設定で保存不可'エラーを解消。旧来の保存方式に整合
- stripe.php: 取得手順/制限付きキー権限/PayPay有効化手順/テストカード/状態(マスク)/接続テスト/削除 を復元
- 保存は形式チェックで保存し接続テストは結果通知のみ(保存はブロックしない)
- security_warnings: 鍵保存先(STRIPE_KEY_DIR/DB同階層)が公開領域内なら重大警告
- .env.example: APP_KEYを任意に, STRIPE_KEY_DIR追記

(commit e8a2cda)

## 2026-06-14 08:34 — 第2弾ペネトレ: 別経路での鍵窃取を多角検証(SQLi/IDOR/traversal/認証後露出/webhook=すべて失敗)＋記録

- success.php: 細工パラメータ+鍵未設定時の500をgraceful化(情報露出なし)
- docs/ペネトレーション結果-第2弾.md に試行と結果を記録

(commit 34e6542)

## 2026-06-14 08:02 — APIキー窃取(フラット配置でDB+.env露出)を塞ぐ: DBが公開領域内なら全管理画面で重大警告

- 実証: .htaccess無効環境で GET /.env + GET /data/app.sqlite → 復号で rk_live_ 回収可能
- 対策: security_warnings() が DB/.env の DOCUMENT_ROOT 配下を検知し _app_header で重大警告表示
- current_db_path() を追加。DB_PATHを公開領域外にすれば窃取連鎖を遮断(DB無→APP_KEY漏れても復号不可)
- 検証: docroot内DB→警告/外出し→警告消滅・/data/app.sqlite 404・機能正常

(commit 163285d)

## 2026-06-14 07:44 — Stripe未設定の判定を新方式(画面鍵登録)に統一: events.php警告をテナント基準に修正＋ダッシュボードに未設定誘導バナー追加


(commit 81a57de)

## 2026-06-14 07:39 — o.php: 鍵未設定の主催者が定員付き公開ページで500になる退行を修正（残席計算をStripe文脈ありに限定）


(commit 6356c63)

## 2026-06-14 07:31 — 画面でのStripe鍵登録(主催者ごと)を復活: 暗号化保存+設定/初期設定ページ

- src/crypto.php: APP_KEYでAES-256-GCM暗号化(at-rest)
- tenants.stripe_secret_enc 追加、set/get_tenant_stripe_key、tenant_has_stripe_key
- init_stripe を active key 対応にし stripe_resolve_tenant/event で 画面鍵→Connect→プラットフォーム の順に解決
- admin/stripe.php(鍵登録・接続テスト・マスク表示・解除・rk_/PayPay/テストカード案内)
- admin/setup.php(初期設定ウィザード)、ナビに Stripe設定 追加
- dashboard/checkout/apply/o/success/index/export/refund/attend/onsite_* を新方式に接続
- .env.example に APP_KEY 追加。実ブラウザで退行なし・CSP0件を確認

(commit 1335086)

## 2026-06-13 04:23 — 本番ペネトレーションテスト結果（読み取りのみ）を記録: 直接露出なし・本番が別系統コードである旨


(commit d469228)

## 2026-06-12 21:23 — CSPにconnect-srcを追加（CAPTCHA有効時のTurnstile通信を許可・無効時はself）

第4ラウンド点検: 公開/CLIファイルに反射型XSS・既定資格情報なし。
管理フロー(ログイン→作成→削除確認→各ページ)の機能退行なし・CSP/JSエラー0件を実ブラウザで確認。

(commit df4e46c)

## 2026-06-12 21:07 — 第3ラウンド対策: CAPTCHA fail-closed・信頼プロキシIP・ログイン列挙対策・ヘッダ拡充ほか

- captcha_verify に failClosed を追加し signup/login は到達不可時に拒否
- client_ip: TRUSTED_PROXY=1 時のみ XFF 先頭を採用（誤ロック/回避を是正）
- login_tenant: 未知メールでもダミーbcryptで応答時間を平準化（アカウント列挙対策）
- success.php: 購入者メールをマスク表示
- mail.log: 件名のイベント名を伏字化
- ヘッダ: object-src 'none' / Permissions-Policy / COOP / CORP を追加
- rate_events/headcount_cache の掃除、イベント削除時にキャッシュ除去
- ドキュメント更新（プラン課金は無料運用方針で意図的に未実装と明記）

(commit e34470d)

## 2026-06-12 20:53 — 対応総まとめ資料(セキュリティ対応サマリー)を追加


(commit 3cc768f)

## 2026-06-12 20:52 — 第2ラウンド対策: mail.logのPII最小化・ログイン強化・公開GETの保護

- mail.log: 本文/トークン/完全アドレスを記録せず、マスク宛先+件名+送信可否のみ(MAIL_LOG=0で無効化)
- login: CAPTCHA適用 + IP単位の失敗回数制限(メール横断スプレー対策)
- apply/o(公開GET): 残席計算をheadcount_cache(60秒)経由にしStripe連打を抑止 + IP単位レート制限
- .env.example更新

(commit b048e95)

## 2026-06-12 20:42 — データガバナンス方針を明文化（マルチテナント維持・管理者最小権限・参加者PIIはシステム非保持）


(commit 3984b55)

## 2026-06-12 20:41 — CSP厳格化: script-srcからunsafe-inlineを撤廃（nonce化・ハンドラ全廃・Chart.js自己ホスト）

- bootstrap: csp_nonce()追加、CSPをscript-src 'self' nonce / style-src 'self' nonce + style-src-attr に
- Chart.jsを自己ホスト(public/assets/chart.umd.min.js)しCDN依存を排除
- 動的<script>(dashboard/apply)をnonce化
- インラインハンドラ11箇所を撤去し共通 public/assets/app.js (.js-select/.js-autosubmit/data-confirm)へ集約
- 全<style>ブロックにnonce付与
- 実ブラウザでCSP違反0件・JS動作正常を確認

(commit 8ea0d0a)

## 2026-06-12 20:33 — Stripe Connect で主催者ごとに決済・PIIを物理分離（後方互換）

- connect.php: OAuth接続/解除フロー、stateでCSRF検証、tenants.stripe_account_idに保存
- bootstrap.php: connect_enabled/effective_stripe_account追加、init_stripeでclient_id設定
- 全Stripe呼び出しを接続アカウント優先・未接続はプラットフォーム鍵にフォールバック
  (checkout/apply/o/success/dashboard/index/export/refund/attend/onsite_*)
- dashboard: 接続状況の表示と接続/解除ボタン
- .env.example/ドキュメント更新。OAuth交換は実Connect資格情報で要本番検証

(commit 3d4f0c9)

## 2026-06-12 20:26 — CAPTCHA(Cloudflare Turnstile)を導入し未認証フォームをボット対策

- src/captcha.php: キー未設定なら無効(素通り)、設定時にsignup/forgot/申込で検証
- CSP: CAPTCHA有効時のみ challenges.cloudflare.com を許可
- .env.example/ドキュメント更新

(commit 364ed42)

## 2026-06-12 20:20 — セキュリティ脅威分析の指摘を全面対策（CSV無害化・IDOR封じ・レート制限・ヘッダ等）

- CSV数式インジェクション対策: csv_cell()で参加者由来フィールドを無害化
- テナント間IDOR封じ: 出席/集金/当日取消/返金で対象がそのイベントの参加者か検証
- レート制限: rate_events追加し signup/forgot/apply をIP単位で制限
- docroot保険: .htaccess(ルート/logs/data)で .env・DB・ログの直配信を拒否
- mail.log: 再設定リンク/トークンをマスク＋サイズローテーション
- Cookie Secure常時付与＋セッションアイドルタイムアウト30分
- セキュリティヘッダ(CSP/X-Frame-Options/X-Content-Type-Options/Referrer-Policy/HSTS)
- パスワード強度チェック(弱PW・反復拒否)
- ALLOW_SIGNUPでオープン登録を閉じる選択肢を追加
- ドキュメントに実装状況と鍵ローテーション手順を追記

(commit 5a9ee49)

## 2026-06-12 20:11 — セキュリティ脅威分析ドキュメントを追加（漏洩リスク・攻撃者視点・対策優先度）


(commit c9071c6)

## 2026-06-12 19:45 — 確認用スクショ等の一時成果物(.shots/)をgit管理対象外に追加


(commit 202d453)

## 2026-06-09 17:39 — 別環境での再開手順とバックアップ方法のドキュメントを追加

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>

(commit 0d9b19d)

## 2026-06-09 17:35 — デプロイ手順と編集・更新手順のドキュメントを追加

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>

(commit 7055000)

## 2026-06-05 18:01 — 単独運営者向けに再構成し、UIを刷新（サイドバー＋グラフ）

- Stripe Connect を廃止し、運営者自身の Stripe キーで直接課金する単独運営者モデルへ変更
- サブスク/プラン機能と関連画面（connect/connect_callback/upgrade/portal/billing_return）を削除
- クレジットカード（事前決済）と現金（当日支払い）の両対応は維持
- 共通デザインシステム public/assets/app.css とサイドバー型の管理シェルを新設
- 管理・認証・参加者・法務の全画面を新デザインへ刷新
- ダッシュボードに申込推移（折れ線）・支払い方法内訳（ドーナツ）グラフを追加（Chart.js）
- イベント日時をカレンダー入力（datetime-local）、通貨を日本円固定に

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>

(commit feee419)

## 2026-06-05 03:21 — 同月の上限到達時はエラーではなく課金（アップグレード）画面へ誘導

- event_save.php: 同じ開催月の上限に達したら upgrade.php?reason=month_limit&month=... へリダイレクト
- upgrade.php: 上限到達の案内バナーを表示

(commit 61e0fb7)

## 2026-06-05 03:18 — サインアップを招待制からオープン登録（メール＋パスワード）に変更

- signup.php: 招待コード不要でアカウント作成（重複メールは拒否）
- ログイン/トップ/利用規約の文言を更新、トップに「無料で新規登録」導線
- 招待機能(invites)は管理者向けに残置（任意利用）

(commit 705857d)

## 2026-06-05 02:56 — アカウント/パスワード再設定・セキュリティ強化・法務ページ・確認メールを追加

- パスワード再設定（メールでリンク）forgot.php/reset.php、password_resets テーブル
- アカウント設定 account.php（表示名・パスワード変更）
- セッションCookie堅牢化（HTTPS時Secure・HttpOnly・SameSite=Lax）、CSRFもsession_boot経由
- ログイン試行制限（15分で5回失敗→一時ブロック）login_attempts テーブル
- メール送信 src/mail.php（mail()＋logs/mail.logへ記録）、当日申込の確認メール送信
- 法務ページ 特商法/利用規約/プライバシー（テンプレート）＋トップにリンク
- .env.example に MAIL_FROM 等、README 更新

(commit aee3410)

## 2026-06-05 02:46 — プラン上限を「開催月」基準に変更

- 上限は登録総数ではなく、同じ開催月(イベント日付の年月)に登録できる数で判定
- event_month()で日付から開催月を抽出、tenant_month_event_count()で同月件数を集計
- 登録・編集の両方でチェック（編集は自分自身を除外）。開催月が判定できない日付は登録不可
- ダッシュボード/アップグレード/プラン定義の文言を「開催月ごと」に更新

(commit 3286f05)

## 2026-06-05 02:39 — プラン料金を確定: 無料1 / 月5=¥500 / 月10=¥1000 / 無制限=¥1500

- plan_catalog を free・p5・p10・unlimited に再定義
- Price IDマップと .env.example を STRIPE_PRICE_P5/P10/UNLIMITED に更新

(commit 8d5065f)

## 2026-06-05 02:35 — プラン課金（主催者→運営者）をStripeサブスクで実装

- プラン利用料はプラットフォーム本体のStripeに入金（接続アカウント指定なし）
- upgrade.php: サブスクのCheckout作成（client_reference_id/metadataにtenant・plan）
- portal.php: Stripeカスタマーポータル（変更・解約）、billing_return.php: 完了案内
- webhook.php: subscription系イベントでtenant.planを自動同期（解約で無料へ）
- tenants.stripe_customer_id 追加、plan↔Price IDマップ(.env)、ダッシュボードに導線
- .env.example に STRIPE_PRICE_* を追加

(commit 3efdd79)

## 2026-06-05 02:29 — プラン制・公開一覧・定員締切・出席/受領ボタンを追加

- 料金プラン: 無料=1イベント、ライト/スタンダード/無制限で上限増。新規登録時に上限チェック
  （tenants.plan 列を追加・後方互換マイグレーション、CLI set-plan、ダッシュボードに表示）
- 公開イベント一覧 o.php?t=テナントID（主催者が1リンク共有、満員表示）
- 定員到達で締切: apply で残席/満員表示、checkout で確定チェック
- 名簿に出席確認ボタン（全員・顧客metadata.attended）と当日支払いの受領ボタン、集計に反映、CSVに出席列

(commit 57a1f13)

## 2026-06-05 02:12 — システム概要のドキュメントを追加（docs/システム概要.md）


(commit e2fe77d)

## 2026-06-05 01:44 — Stage 3: マルチテナント本格切替（DBイベント・テナントスコープ・Connect決済）

- イベントを events.json から DB(events) へ移行（テナント別CRUD: create/update/delete_event）
- 管理画面をBasic認証→テナントのセッション認証に置換し、全操作を所有テナントに限定
- 公開申込/名簿/返金/集金/取消を「イベント所有テナントの接続アカウント(stripe_account)」でスコープ
- 申込・決済はテナントが Connect 連携済みのときのみ受付（未連携は受付不可表示）
- イベント管理に申込リンク発行、ルートはランディング化、旧 require_admin_auth/events.json を撤去
- README をマルチテナント構成に更新

(commit b1986f1)

## 2026-06-05 01:15 — 当日支払いに「集金確認済み」チェックを追加、事前決済者は「事前決済済み」表記に

- 当日申込(Stripe顧客)の metadata.collected を切替する onsite_collect.php を追加
- 名簿に「集金確認済みにする／未収に戻す」ボタンと状態表示
- 集計に集金済み件数(集金済 x/y)を表示、CSVの状態にも反映

(commit b266ecc)

## 2026-06-05 01:13 — マルチテナント基盤を追加（Stage 1+2: SQLite・招待制アカウント・Stripe Connect連携）

- src/db.php: SQLite データ層（tenants/invites/events スキーマ）
- src/tenant.php: アカウント作成・セッションログイン・招待発行/消費
- 招待制サインアップ/ログイン/ログアウト/ダッシュボード(/admin/)
- Stripe Connect の OAuth 連携（connect.php/connect_callback.php、秘密鍵は保存せずacct_idのみ保持）
- 管理者向け招待発行画面(invites.php)とCLI(bin/console.php: init/create-admin/make-invite)
- .env.example に STRIPE_CONNECT_CLIENT_ID 等を追加、data/ をgitignore

※ 既存の単一テナント(Basic認証/events.json)は Stage 3 でこのテナント単位へ移行予定

(commit 6e9d2b9)

## 2026-06-05 01:05 — 事前決済／当日支払いの選択と方式別の料金設定を追加

- イベントに当日料金(amount_onsite)と受付方式(allow_prepay/allow_onsite)を追加
- 申込フォームで支払い方法を選択、人数×単価で合計を自動計算
- 当日支払いは課金なしのStripe顧客として記録し、名簿に未収として合流
- 管理画面に支払方法列・当日予定の集計・当日申込の取消を追加、CSVにも反映

(commit 2151a17)

## 2026-06-05 00:44 — 自前の参加申込フォーム（参加人数選択）と管理画面からのイベント登録を追加

- apply.php: 氏名/メール/電話/参加人数/備考を入力→合計を自動計算
- checkout.php: フォーム入力を検証しquantity・metadataとしてStripeへ受け渡し
- 管理画面にイベントのCRUD（events.php / event_save.php / event_delete.php）を追加し config/events.json に保存
- 参加者名簿に人数・備考を表示、CSVにも追加

(commit 62188c5)

## 2026-06-05 00:35 — 参加者管理画面を追加（名簿閲覧・返金/キャンセル・CSV、ID+PW保護）

- Stripeの決済データから名簿を取得（自前DBは持たない方針を維持）
- /admin/ をBasic認証(ADMIN_USER/ADMIN_PASS)で保護
- 全額返金=キャンセル、一部返金に対応（CSRF対策つき）
- UTF-8 BOM付きCSV出力

(commit 1ba94c6)

## 2026-06-05 00:19 — 決済情報を主催者が保持しない旨を各ページに明示


(commit 77c9722)

## 2026-06-05 00:08 — 事前決済（前払い）イベント申込アプリを追加

小規模イベント向けに、参加費を前払いで集めるPHP最小構成アプリを実装。
当日欠席・ドタキャンのキャンセル料を後から取り立てられない課題を、
先に決済してもらうことで構造的に解消する。

- 決済はStripe Checkout（ホスト画面）に代行。カード情報はサーバーで一切扱わない
- DBを持たず、参加者情報・名簿はStripeダッシュボードに集約
- イベント定義はconfig/events.jsonで管理
- 申込→決済→成功/中断/ポリシー表示の各画面と任意のWebhook記録を実装
- README・運用手順ドキュメントを整備

(commit b861621)

## 2026-06-04 17:52 — Initial commit


(commit 5c75153)
