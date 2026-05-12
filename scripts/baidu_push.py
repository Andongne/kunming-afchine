#!/usr/bin/env python3
"""Push public URLs to Baidu Search Console (普通收录 API).

Minimal usage:
    export BAIDU_SITE='https://kunming-afchine.org'
    export BAIDU_TOKEN='REGENERATE_ME'
    python3 scripts/baidu_push.py urls.txt

Or via stdin:
    python3 scripts/baidu_push.py <<'EOF'
    https://kunming-afchine.org/
    https://kunming-afchine.org/zh/
    EOF
"""

from __future__ import annotations

import json
import os
import sys
import urllib.parse
import urllib.request
from pathlib import Path

API_BASE = "http://data.zz.baidu.com/urls"


def load_urls(argv: list[str]) -> list[str]:
    if len(argv) > 1:
        data = Path(argv[1]).read_text(encoding="utf-8")
    else:
        data = sys.stdin.read()

    urls = [line.strip() for line in data.splitlines() if line.strip()]
    if not urls:
        raise SystemExit("Aucune URL à envoyer.")
    return urls


def main() -> int:
    site = os.environ.get("BAIDU_SITE", "https://kunming-afchine.org").rstrip("/")
    token = os.environ.get("BAIDU_TOKEN")

    if not token:
        raise SystemExit("Variable manquante: BAIDU_TOKEN")

    urls = load_urls(sys.argv)
    invalid = [url for url in urls if not url.startswith(site)]
    if invalid:
        print("Ces URLs ne correspondent pas au site configuré:", file=sys.stderr)
        for url in invalid:
            print(f"- {url}", file=sys.stderr)
        return 2

    endpoint = f"{API_BASE}?site={urllib.parse.quote(site, safe=':/')}&token={urllib.parse.quote(token)}"
    body = ("\n".join(urls)).encode("utf-8")

    request = urllib.request.Request(
        endpoint,
        data=body,
        headers={"Content-Type": "text/plain; charset=utf-8"},
        method="POST",
    )

    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            payload = response.read().decode("utf-8", errors="replace")
            status = response.getcode()
    except Exception as exc:  # pragma: no cover
        print(f"Erreur API Baidu: {exc}", file=sys.stderr)
        return 1

    print(f"HTTP {status}")
    try:
        print(json.dumps(json.loads(payload), ensure_ascii=False, indent=2))
    except json.JSONDecodeError:
        print(payload)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
