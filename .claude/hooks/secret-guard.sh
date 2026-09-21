#!/usr/bin/env bash
# Claude Code PreToolUse hook
# Bash ツール経由で .env 等の機密情報がプロンプトに乗るのを防ぐ
#
# 入力: JSON (stdin)  { "tool_name": "Bash", "tool_input": { "command": "..." } }
# 出力: JSON (stdout) { "decision": "block", "reason": "..." }  でブロック
#       何も出力しない / 終了コード 0                            で通過
#
# フェイルクローズ方針（2026-09-21改訂）:
# 「bash .claude/hooks/secret-guard.sh」という相対パス指定で登録していたため、
# Bashツールのcwdが apps/api/ にドリフトした際にスクリプト自体が見つからず
# 起動失敗 → Claude Codeの仕様上フェイルオープン（素通し）し、
# tinker 経由で実APIキーが会話に漏洩する事故が発生した。
# 再発防止のため、①settings.json側は ${CLAUDE_PROJECT_DIR} 絶対パスに変更、
# ②本スクリプト内でも「判定できない場合はブロックする」方針を徹底する。
#
# 注意: これは正規表現によるブロックリストであり、原理的に完全ではない
# （例: 未知のコマンド・言語での読み出しは検知できない）。恒久対策としては
# Claude Code のサンドボックス機能（sandbox.credentials）による OS レベルの
# 保護を別途検討すること（bird_design.md §9.4 参照）。

set -uo pipefail

block() {
  python3 -c "import json; print(json.dumps({'decision':'block','reason':'''$1'''}))" 2>/dev/null \
    || printf '{"decision":"block","reason":"secret-guard: %s"}\n' "$1"
  exit 0
}

# python3 自体が使えない場合、コマンド内容を検査できないためブロック
command -v python3 >/dev/null 2>&1 \
  || block "python3 が利用できずコマンド内容を検査できないためブロックしました"

INPUT=$(cat)

# tool_name と command を1回のpython呼び出しで取得（改行はSOH(\x01)に退避）
PARSE_OUT=$(python3 -c "
import json, sys
d = json.load(sys.stdin)
print(d.get('tool_name',''))
print(d.get('tool_input',{}).get('command','').replace(chr(10), chr(1)))
" <<< "$INPUT" 2>/dev/null)
PARSE_RC=$?

[[ $PARSE_RC -eq 0 ]] || block "PreToolUse 入力の解析に失敗したためブロックしました"

TOOL_NAME=$(sed -n '1p' <<< "$PARSE_OUT")
COMMAND=$(sed -n '2p' <<< "$PARSE_OUT" | tr '\1' '\n')

# Bash ツール以外は何もしない
[[ "$TOOL_NAME" == "Bash" ]] || exit 0

# ---- ルール 1: .env ファイルを直接表示・ダンプするコマンドをブロック ----
# cat / head / tail / bat / less / more / nl / od / xxd / hexdump / strings /
# base64 / sed / awk / dd / tac + .env 系パス
# .env.example は許可（値が入っていないテンプレート）
if echo "$COMMAND" | grep -qE \
     '(^|[|&;[:space:]])(cat|head|tail|bat|less|more|nl|od|xxd|hexdump|strings|base64|sed|awk|dd|tac|rev)[[:space:]]+[^|&;]*\.env\b' \
   && ! echo "$COMMAND" | grep -qE '\.example'; then
  block ".env ファイルの内容表示は AIセッション中にブロックされています"
fi

# ---- ルール 2: grep で .env から値を抽出するコマンドをブロック ----
if echo "$COMMAND" | grep -qE 'grep[^|]*\.env\b' \
   && ! echo "$COMMAND" | grep -qE '\.example|\.gitignore'; then
  block ".env への grep は AIセッション中にブロックされています"
fi

# ---- ルール 3: 全環境変数ダンプをブロック ----
# env / printenv / set / export -p / declare -x を単独実行すると
# 全変数（REINFOLIB_API_KEY 等）が出る
if echo "$COMMAND" | grep -qE '(^|[|&;[:space:]])(env|printenv|set|export[[:space:]]+-p|declare[[:space:]]+-x)[[:space:]]*($|[|&;])'; then
  block "全環境変数の表示は AIセッション中にブロックされています（機密情報が含まれる可能性があります）"
fi

# ---- ルール 4: php artisan tinker 経由での機密情報アクセスをブロック ----
# env() だけでなく config() 経由の間接アクセスや、.env / APIキー・パスワード・
# トークン等を示唆するキーワードを含む tinker 実行も広めにブロックする
if echo "$COMMAND" | grep -qiE 'tinker' \
   && echo "$COMMAND" | grep -qiE '\benv\(|\bconfig\(|\.env\b|api[_-]?key|password|secret|token|credential'; then
  block "tinker 経由での機密情報アクセスの可能性があるためブロックされています"
fi

# ---- ルール 5: インタプリタのワンライナーで .env 等を読むコマンドをブロック ----
# php -r / python3 -c / node -e / ruby -e で .env や機密情報キーワードを扱う場合
if echo "$COMMAND" | grep -qE '(^|[|&;[:space:]])(php[[:space:]]+-r|python3?[[:space:]]+-c|node[[:space:]]+-e|ruby[[:space:]]+-e)\b' \
   && echo "$COMMAND" | grep -qiE '\.env\b|api[_-]?key|password|secret|token|credential'; then
  block "インタプリタのワンライナー経由での機密情報アクセスの可能性があるためブロックされています"
fi

exit 0
