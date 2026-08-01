#!/bin/sh
# 簡易バックアップスクリプト。プロジェクト直下で実行する（例: sh bin/backup.sh）。
#
# 方針:
#  - 既定は「機密を除外」してDB等だけを固める（万一バックアップが漏れても鍵は含まれない）。
#  - 鍵込みで持ち出したいときは ENCRYPT=1 でパスワード付き暗号化ZIPにする。
#
# 使い方:
#   sh bin/backup.sh              … 機密除外バックアップ backup-YYYYmmdd.zip を作成
#   ENCRYPT=1 sh bin/backup.sh    … .env/鍵/DB込みの暗号化ZIP（パスワードを対話入力）
#
# ⚠️ 重要: 暗号化キー APP_KEY は「鍵ファイル(stripe_*.key)とは別の場所」に保管すること。
#   両方が同時に漏れると復号されるため、APP_KEY はパスワードマネージャ等で分離保管する。
#   （＝バックアップに .env を含める場合は、その .env に APP_KEY を書かない運用が望ましい）

set -e
STAMP=$(date +%Y%m%d-%H%M%S)

if [ "$ENCRYPT" = "1" ]; then
  OUT="backup-secure-${STAMP}.zip"
  echo "暗号化バックアップ（.env・鍵・DBを含む）を作成します。パスワードを設定してください。"
  # -e でパスワード付き（対話）。vendor/.git は除外。
  zip -e -r "$OUT" . \
    -x ".git/*" -x "vendor/*" -x "*.DS_Store" -x "logs/*.log" >/dev/null
  echo "作成: $OUT （パスワードと APP_KEY は別々に厳重保管）"
else
  OUT="backup-${STAMP}.zip"
  # 機密（.env・鍵・ログ）と大物（vendor・.git）を除外。DBは含む。
  zip -r "$OUT" . \
    -x ".env" -x "*.key" -x "*.pem" \
    -x "logs/*.log" -x "logs/*.log.*" \
    -x "vendor/*" -x ".git/*" -x "*.DS_Store" >/dev/null
  echo "作成: $OUT （機密除外。復元時は .env と鍵を別途用意、または鍵は再登録）"
fi
