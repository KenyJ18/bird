#!/usr/bin/env bash
# Copilot CLI PreToolUse adapter for the shared Claude Code secret guard.
#
# Copilot expects permissionDecision while Claude Code expects decision. Keep
# detection rules in secret-guard.sh and translate only the response contract.

set -euo pipefail

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
input=$(cat)
result=$(printf '%s' "$input" | "$script_dir/secret-guard.sh")

[[ -z "$result" ]] && exit 0

python3 - "$result" <<'PY'
import json
import sys

try:
    result = json.loads(sys.argv[1])
except (IndexError, json.JSONDecodeError) as error:
    print(f"secret-guard returned invalid JSON: {error}", file=sys.stderr)
    sys.exit(1)

if result.get("decision") == "block":
    reason = result.get("reason")
    if not isinstance(reason, str) or not reason:
        print("secret-guard returned a block decision without a reason", file=sys.stderr)
        sys.exit(1)
    print(json.dumps({
        "permissionDecision": "deny",
        "permissionDecisionReason": reason,
    }))
    sys.exit(0)

print("secret-guard returned an unsupported decision", file=sys.stderr)
sys.exit(1)
PY
