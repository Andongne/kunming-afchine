#!/usr/bin/env python3
"""Baidu daily push — soumet jusqu'au quota quotidien, reprend le lendemain.

Usage:
    BAIDU_TOKEN=xxx python3 scripts/baidu_daily_push.py

Variables d'environnement:
    BAIDU_TOKEN   Token API Baidu (obligatoire)
    BAIDU_SITE    Site (défaut: https://kunming-afchine.org)
    URLS_FILE     Fichier de toutes les URLs (défaut: scripts/baidu_urls_all_zh.txt)
    STATE_FILE    Fichier de suivi (défaut: scripts/.baidu_push_state.json)
"""

import json, os, sys, urllib.request, urllib.parse
from pathlib import Path
from datetime import date

BASE_DIR   = Path(__file__).parent
BAIDU_SITE = os.environ.get("BAIDU_SITE", "https://kunming-afchine.org").rstrip("/")
BAIDU_TOKEN = os.environ.get("BAIDU_TOKEN", "a8j5iMkCdWOwb2vs")
URLS_FILE  = Path(os.environ.get("URLS_FILE",  BASE_DIR / "baidu_urls_all_zh.txt"))
STATE_FILE = Path(os.environ.get("STATE_FILE", BASE_DIR / ".baidu_push_state.json"))
API_URL    = f"http://data.zz.baidu.com/urls?site={urllib.parse.quote(BAIDU_SITE, safe=':/')}&token={BAIDU_TOKEN}"
BATCH      = 10  # max par appel API

def load_state():
    if STATE_FILE.exists():
        return json.loads(STATE_FILE.read_text())
    return {"submitted": [], "last_run": None}

def save_state(state):
    STATE_FILE.write_text(json.dumps(state, ensure_ascii=False, indent=2))

def push(urls):
    body = "\n".join(urls).encode("utf-8")
    req  = urllib.request.Request(API_URL, data=body,
                                  headers={"Content-Type": "text/plain"}, method="POST")
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(r.read().decode())

def main():
    if not BAIDU_TOKEN:
        sys.exit("BAIDU_TOKEN manquant")
    if not URLS_FILE.exists():
        sys.exit(f"Fichier URLs introuvable: {URLS_FILE}")

    all_urls  = [l.strip() for l in URLS_FILE.read_text().splitlines() if l.strip()]
    state     = load_state()
    submitted = set(state["submitted"])
    pending   = [u for u in all_urls if u not in submitted]

    if not pending:
        print("Toutes les URLs ont déjà été soumises.")
        return

    batch = pending[:BATCH]
    print(f"Soumission de {len(batch)} URL(s) sur {len(pending)} restantes...")

    result = push(batch)
    print(json.dumps(result, ensure_ascii=False, indent=2))

    success_count = result.get("success", 0)
    if success_count > 0:
        state["submitted"].extend(batch[:success_count])
        state["last_run"] = str(date.today())
        save_state(state)
        print(f"✓ {success_count} URL(s) soumises. Quota restant: {result.get('remain', '?')}")
        remaining = len(all_urls) - len(state["submitted"])
        print(f"→ {remaining} URL(s) restantes pour les prochains jours.")
    else:
        print("Aucune URL soumise — vérifier le quota ou le token.")

if __name__ == "__main__":
    main()
