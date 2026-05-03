"""
send_messages.py — Envoi automatisé de messages LinkedIn
Alliance Française de Kunming

Usage:
  python3 send_messages.py [--csv contacts.csv] [--dry-run]

Prérequis:
  pip install playwright
  playwright install chromium

Le script utilise ton profil Chrome existant pour éviter
de te reconnecter à LinkedIn.
"""

import csv, sys, time, random, argparse
from pathlib import Path

try:
    from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout
except ImportError:
    print("❌ Playwright non installé. Lance : pip install playwright && playwright install chromium")
    sys.exit(1)

# ── Config ────────────────────────────────────────────────────────────────────
LINKEDIN_BASE   = "https://www.linkedin.com"
AFK_PAGE_URL    = "https://www.linkedin.com/company/alliance-fran%C3%A7aise-kunming"
SIGNATURE       = "Bien cordialement,\nAntoine Lopez"
PAUSE_MIN       = 8    # secondes entre chaque message (min)
PAUSE_MAX       = 20   # secondes entre chaque message (max)

# Chemin vers ton profil Chrome (macOS)
import os
CHROME_PROFILE = os.path.expanduser(
    "~/Library/Application Support/Google/Chrome"
)

# ── Message templates ─────────────────────────────────────────────────────────
def build_message(row: dict) -> str:
    prenom    = row.get("prenom", "").strip()
    nom       = row.get("nom", "").strip()
    poste     = row.get("poste", "").strip()
    orga      = row.get("organisation", "").strip()
    categorie = row.get("categorie", "standard").strip().lower()

    salutation = f"Bonjour {prenom},"

    if categorie == "academique":
        corps = (
            f"Merci pour l'ajout à votre réseau. "
            f"En tant que {poste}{(' (' + orga + ')') if orga else ''}, "
            f"votre regard sur les questions de langue, de culture et d'éducation me paraît précieux. "
            f"Je vous invite à suivre la page de l'Alliance Française de Kunming, "
            f"qui porte ces enjeux au cœur de la Chine :\n{AFK_PAGE_URL}"
        )
    elif categorie == "fle":
        corps = (
            f"Merci pour l'ajout. Vous travaillez dans l'enseignement — "
            f"notre page présente le quotidien d'un centre de langue implanté à Kunming, "
            f"avec ses spécificités pédagogiques et culturelles. "
            f"Je pense qu'elle pourrait vous intéresser :\n{AFK_PAGE_URL}"
        )
    elif categorie == "cooperation":
        corps = (
            f"Merci pour l'ajout. Votre rôle{(' en tant que ' + poste) if poste else ''}"
            f"{(' chez ' + orga) if orga else ''} et nos missions en Chine "
            f"s'inscrivent dans un même espace francophone. "
            f"Je vous invite à suivre notre page :\n{AFK_PAGE_URL}"
        )
    else:
        corps = (
            f"Merci pour l'ajout à votre réseau. "
            f"Je vous invite à suivre la page de l'Alliance Française de Kunming, "
            f"vitrine de nos actions éducatives, culturelles et francophones en Chine :\n{AFK_PAGE_URL}"
        )

    return f"{salutation}\n\n{corps}\n\n{SIGNATURE}"


# ── Playwright ────────────────────────────────────────────────────────────────
def send_message(page, profile_url: str, message: str, dry_run: bool) -> bool:
    try:
        page.goto(profile_url, wait_until="domcontentloaded", timeout=15000)
        time.sleep(random.uniform(2, 4))

        # Chercher le bouton "Message"
        msg_btn = page.locator('a[href*="messaging"], button:has-text("Message")')
        msg_btn.first.click(timeout=8000)
        time.sleep(random.uniform(1.5, 3))

        # Zone de saisie
        box = page.locator('div[role="textbox"][aria-label*="essage"], div.msg-form__contenteditable')
        box.first.click()
        time.sleep(0.5)

        if dry_run:
            print(f"  [DRY-RUN] Message à envoyer :\n{message}\n")
            return True

        # Taper le message caractère par caractère (plus naturel)
        for line in message.split("\n"):
            box.first.type(line, delay=random.randint(30, 80))
            box.first.press("Shift+Enter")
        time.sleep(0.5)

        # Envoyer
        send_btn = page.locator('button[type="submit"]:has-text("Envoyer"), button.msg-form__send-button')
        send_btn.first.click(timeout=5000)
        time.sleep(random.uniform(1, 2))
        return True

    except PWTimeout:
        print(f"  ⚠️  Timeout sur {profile_url}")
        return False
    except Exception as e:
        print(f"  ❌ Erreur : {e}")
        return False


# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser(description="Envoi automatisé de messages LinkedIn AFK")
    parser.add_argument("--csv",     default="contacts.csv", help="Fichier CSV des contacts")
    parser.add_argument("--dry-run", action="store_true",    help="Simuler sans envoyer")
    args = parser.parse_args()

    csv_path = Path(args.csv)
    if not csv_path.exists():
        print(f"❌ Fichier introuvable : {csv_path}")
        sys.exit(1)

    contacts = []
    with open(csv_path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            contacts.append(row)

    print(f"📋 {len(contacts)} contacts chargés depuis {csv_path}")
    if args.dry_run:
        print("🔍 Mode DRY-RUN — aucun message ne sera envoyé\n")

    with sync_playwright() as p:
        # Utiliser le profil Chrome existant (session LinkedIn active)
        browser = p.chromium.launch_persistent_context(
            user_data_dir=CHROME_PROFILE,
            channel="chrome",
            headless=False,   # Visible pour surveiller
            slow_mo=200,
        )
        page = browser.new_page()

        # Vérifier la connexion LinkedIn
        page.goto("https://www.linkedin.com/feed/", wait_until="domcontentloaded")
        time.sleep(2)
        if "login" in page.url:
            print("❌ Non connecté à LinkedIn. Connecte-toi dans Chrome et relance le script.")
            browser.close()
            sys.exit(1)
        print("✅ Connecté à LinkedIn\n")

        sent = 0
        failed = 0

        for i, row in enumerate(contacts, 1):
            prenom = row.get("prenom", "?")
            nom    = row.get("nom", "?")
            url    = row.get("linkedin_url", "").strip()

            if not url:
                print(f"[{i}/{len(contacts)}] ⚠️  {prenom} {nom} — URL manquante, ignoré")
                continue

            message = build_message(row)
            print(f"[{i}/{len(contacts)}] ✉️  {prenom} {nom} ({row.get('poste','')[:40)})")

            ok = send_message(page, url, message, args.dry_run)
            if ok:
                sent += 1
                print(f"  ✅ Envoyé")
            else:
                failed += 1

            if i < len(contacts):
                pause = random.uniform(PAUSE_MIN, PAUSE_MAX)
                print(f"  ⏱  Pause {pause:.0f}s...")
                time.sleep(pause)

        browser.close()

    print(f"\n{'='*40}")
    print(f"✅ Envoyés : {sent}  |  ❌ Échecs : {failed}")


if __name__ == "__main__":
    main()
