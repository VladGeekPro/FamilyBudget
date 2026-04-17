#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

if [ ! -d ".venv" ]; then
  python3 -m venv .venv
fi

source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements-fastapi.txt
pip install -r requirements-transcribe.txt

uvicorn expense_voice.fastapi_app:app --host 127.0.0.1 --port 8000 --workers 1
