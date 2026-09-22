#!/usr/bin/env bash
# Copilot CLI PreToolUse guard for direct reads of secret-bearing files.

set -euo pipefail

input=$(cat)

python3 - "$input" <<'PY'
import json
import os
import sys

SENSITIVE_FILENAMES = {
    ".env",
    ".env.local",
    ".env.production",
    ".env.staging",
    ".credentials.local",
}

try:
    payload = json.loads(sys.argv[1])
except (IndexError, json.JSONDecodeError):
    print(json.dumps({
        "permissionDecision": "deny",
        "permissionDecisionReason": "PreToolUse入力を解析できないため、機密ファイル読み取りを拒否しました",
    }))
    sys.exit(0)


def strings(value):
    if isinstance(value, str):
        yield value
    elif isinstance(value, dict):
        for nested_value in value.values():
            yield from strings(nested_value)
    elif isinstance(value, list):
        for nested_value in value:
            yield from strings(nested_value)


for value in strings(payload.get("tool_input", {})):
    if os.path.basename(value) in SENSITIVE_FILENAMES:
        print(json.dumps({
            "permissionDecision": "deny",
            "permissionDecisionReason": ".env等の機密ファイルはAIセッション中に読み取れません",
        }))
        sys.exit(0)
PY
