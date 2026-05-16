---
summary: "Workspace template for TOOLS.md"
read_when:
  - Bootstrapping a workspace manually
---

# TOOLS.md - Local Notes

Skills define _how_ tools work. This file is for _your_ specifics — the stuff that's unique to your setup.

## Gandi sFTP
- Host : `sftp.sd6.gpaas.net`
- User : `436b11ba-aeb3-11ef-9c89-00163e816020`
- Clé SSH : `/data/.ssh/id_ed25519_gandi` (variable `GANDI_SSH_KEY`)
- Mot de passe : variable `GANDI_SFTP_PASSWORD`
- Racine site : `vhosts/kunming-afchine.org/htdocs/`

## What Goes Here

Things like:

- Camera names and locations
- SSH hosts and aliases
- Preferred voices for TTS
- Speaker/room names
- Device nicknames
- Anything environment-specific

## Examples

```markdown
### Cameras

- living-room → Main area, 180° wide angle
- front-door → Entrance, motion-triggered

### SSH

- home-server → 192.168.1.100, user: admin

### TTS

- Preferred voice: "Nova" (warm, slightly British)
- Default speaker: Kitchen HomePod
```

## Why Separate?

Skills are shared. Your setup is yours. Keeping them apart means you can update skills without losing your notes, and share skills without leaking your infrastructure.

---

Add whatever helps you do your job. This is your cheat sheet.
## Browser (OpenClaw tool)

Chromium fonctionnel via le browser tool OpenClaw.

**Config** (`~/.openclaw/openclaw.json`) :
```json
"browser": {
  "executablePath": "/usr/bin/chromium",
  "headless": true,
  "noSandbox": true,
  "defaultProfile": "openclaw",
  "extraArgs": [
    "--disable-gpu",
    "--disable-dev-shm-usage",
    "--disable-extensions",
    "--disable-background-networking",
    "--no-first-run",
    "--no-default-browser-check"
  ]
}
```

**Si le browser tool timeout ou dit "profile locked"** :
```bash
rm -rf /data/.openclaw/browser/openclaw/user-data
pkill -9 -f chromium || true
```
Puis relancer la requête (pas besoin de redémarrer le gateway).

**Dépendance installée** : `playwright-core@1.59.1`
```bash
sudo npm install --prefix /usr/local/lib/node_modules/openclaw/dist/extensions/browser \
  --production --no-save playwright-core@1.59.1
```
