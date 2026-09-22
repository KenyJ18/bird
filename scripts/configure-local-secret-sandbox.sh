#!/usr/bin/env bash
# Configure the local macOS shell and Claude Code sandbox without handling the secret value.

set -euo pipefail

if [[ "$(uname)" != "Darwin" ]]; then
  echo "This setup script supports macOS Keychain only." >&2
  exit 1
fi

keychain_service="bird.reinfolib-api-key"
claude_dir="${CLAUDE_CONFIG_DIR:-$HOME/.claude}"
claude_settings="$claude_dir/settings.json"
zshrc="${ZDOTDIR:-$HOME}/.zshrc"
begin_marker="# >>> bird Reinfolib Keychain environment >>>"
end_marker="# <<< bird Reinfolib Keychain environment <<<"

if ! security find-generic-password -a "$USER" -s "$keychain_service" -w >/dev/null 2>&1; then
  cat >&2 <<EOF
No Keychain item exists for '$keychain_service'.
Create it first with Keychain Access, then run this script again. Do not paste the API key into an AI session.
EOF
  exit 1
fi

mkdir -p "$claude_dir"
if [[ -f "$claude_settings" ]]; then
  backup_path="$claude_settings.backup.$(date +%Y%m%d%H%M%S)"
  cp "$claude_settings" "$backup_path"
  chmod 600 "$backup_path"
fi

python3 - "$claude_settings" <<'PY'
import json
import os
import sys

settings_path = sys.argv[1]
if os.path.exists(settings_path):
    with open(settings_path, encoding="utf-8") as file:
        settings = json.load(file)
else:
    settings = {}

sandbox = settings.setdefault("sandbox", {})
sandbox.update({
    "enabled": True,
    "allowUnsandboxedCommands": False,
    "failIfUnavailable": True,
})

network = sandbox.setdefault("network", {})
network.setdefault("tlsTerminate", {})
allowed_domains = network.setdefault("allowedDomains", [])
if "www.reinfolib.mlit.go.jp" not in allowed_domains:
    allowed_domains.append("www.reinfolib.mlit.go.jp")

credentials = sandbox.setdefault("credentials", {})
env_vars = [
    entry for entry in credentials.get("envVars", [])
    if entry.get("name") != "REINFOLIB_API_KEY"
]
env_vars.append({
    "name": "REINFOLIB_API_KEY",
    "mode": "mask",
    "injectHosts": ["www.reinfolib.mlit.go.jp"],
})
credentials["envVars"] = env_vars

with open(settings_path, "w", encoding="utf-8") as file:
    json.dump(settings, file, ensure_ascii=False, indent=2)
    file.write("\n")
PY
chmod 600 "$claude_settings"

python3 - "$zshrc" "$begin_marker" "$end_marker" "$keychain_service" <<'PY'
import pathlib
import sys

path = pathlib.Path(sys.argv[1])
begin, end, service = sys.argv[2:]
existing = path.read_text(encoding="utf-8") if path.exists() else ""

if begin in existing:
    start = existing.index(begin)
    finish = existing.index(end, start) + len(end)
    existing = existing[:start] + existing[finish:].lstrip("\n")

block = f"""{begin}
if [[ -z "${{REINFOLIB_API_KEY:-}}" ]] && command -v security >/dev/null 2>&1; then
  REINFOLIB_API_KEY="$(security find-generic-password -a "$USER" -s "{service}" -w 2>/dev/null || true)"
  export REINFOLIB_API_KEY
fi
{end}
"""
path.write_text(existing.rstrip() + "\n\n" + block, encoding="utf-8")
PY

cat <<EOF
Configured the Claude Code sandbox and zsh Keychain loader.

Next steps:
1. Remove REINFOLIB_API_KEY from local .env files without displaying its value.
2. Restart the terminal and Claude Code.
3. Run /sandbox and confirm the effective policy before using the API.
EOF
