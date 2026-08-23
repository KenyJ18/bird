#!/usr/bin/env bash
# Claude Code PreToolUse hook
# Bash ツール経由で .env の内容がプロンプトに乗るのを防ぐ
#
# 入力: JSON (stdin)  { "tool_name": "Bash", "tool_input": { "command": "..." } }
# 出力: JSON (stdout) { "decision": "block", "reason": "..." }  でブロック
#       何も出力しない / 終了コード 0                            で通過

set -euo pipefail

INPUT=$(cat)

# Bash ツール以外は何もしない
TOOL_NAME=$(python3 -c \
  "import json,sys; d=json.load(sys.stdin); print(d.get('tool_name',''))" \
  <<< "$INPUT" 2>/dev/null || echo "")

[[ "$TOOL_NAME" == "Bash" ]] || exit 0

COMMAND=$(python3 -c \
  "import json,sys; d=json.load(sys.stdin); print(d.get('tool_input',{}).get('command',''))" \
  <<< "$INPUT" 2>/dev/null || echo "")

block() {
  # JSON で decision: block を返す → Claude Code がツール実行を中止する
  python3 -c "import json; print(json.dumps({'decision':'block','reason':'''$1'''}))"
  exit 0
}

# ---- ルール 1: .env ファイルを直接表示するコマンドをブロック ----
# cat / head / tail / bat / less / more + .env 系パス
# .env.example は許可（値が入っていないテンプレート）
if echo "$COMMAND" | grep -qE \
     '(^|[|&;[:space:]])(cat|head|tail|bat|less|more|nl)[[:space:]]+[^|&;]*\.env\b' \
   && ! echo "$COMMAND" | grep -qE '\.example'; then
  block ".env ファイルの内容表示は AIセッション中にブロックされています"
fi

# ---- ルール 2: grep で .env から値を抽出するコマンドをブロック ----
if echo "$COMMAND" | grep -qE 'grep[^|]*\.env\b' \
   && ! echo "$COMMAND" | grep -qE '\.example|\.gitignore'; then
  block ".env への grep は AIセッション中にブロックされています"
fi

# ---- ルール 3: 全環境変数ダンプをブロック ----
# env / printenv / set を単独実行すると全変数（REINFOLIB_API_KEY 等）が出る
if echo "$COMMAND" | grep -qE '(^|[|&;[:space:]])(env|printenv|set)[[:space:]]*($|[|&;])'; then
  block "全環境変数の表示は AIセッション中にブロックされています（機密情報が含まれる可能性があります）"
fi

# ---- ルール 4: php artisan tinker 経由の env() 呼び出しをブロック ----
if echo "$COMMAND" | grep -qE 'tinker.*env\(' ; then
  block "tinker 経由の env() 呼び出しは AIセッション中にブロックされています"
fi

exit 0
