# Stack Technique — Projet SaaS AF
*Version 0.1 — Mai 2026*

---

## Contexte de décision

Le produit doit être :
- **Déployable en Chine continentale** (hébergement local, pas de dépendance à Google/AWS US)
- **Maintenable solo** au moins jusqu'à la première traction commerciale
- **Adaptable à chaque client** (langue locale, identité visuelle, domaine propre)
- **Évolutif** sans réécriture complète dans 3 ans

---

## Option 1 — Stack Joomla (continuité Kunming) ✅ Recommandée pour MVP

### Principe
Packager et productiser ce qui existe déjà à Kunming : Joomla 4/5 + extensions éprouvées, déployé en instance dédiée par client (modèle "single-tenant").

### Composants

| Rôle | Composant | Licence | Notes |
|---|---|---|---|
| CMS | Joomla 4/5 | Open source | Base solide, LTS jusqu'à ~2027 |
| Template | Helix Ultimate + SP Builder | Freemium | Flexible, pas de code pour le client |
| Multilingue | FaLang Pro | Commercial | Traductions FR/EN/ZH robustes |
| Calendrier & événements | RSEvents Pro | Commercial | Éprouvé, catégories, filtres |
| Formulaires | RSForm Pro | Commercial | Inscriptions, validations, emails auto |
| SEO local | Baidu push (script Python maison) | Maison | Déjà opérationnel |
| Cache & perfo | Joomla native + Varnish hébergeur | — | Suffisant |
| Sécurité | Honeypot + validation serveur (maison) | Maison | Déjà opérationnel |

### Modèle de déploiement
```
Client AF X
└── VPS dédié ou mutualisé renforcé
    ├── Joomla instance (domaine client)
    ├── Base MySQL dédiée
    └── Stack standard AF (template + extensions)
```

Chaque client = instance isolée. Pas de multi-tenant à ce stade.

### Avantages
- **Time-to-market très court** : base Kunming = ~70% du produit fini
- **Aucune dépendance cloud US** : hébergeable sur VPS en Chine, HK, EU
- **Coût licences maîtrisé** : ~300–500 €/an par instance (RSEvents + RSForm + FaLang)
- **Maintenance solo réaliste** : stack connue, documentation existante

### Limites
- Pas natif SaaS (pas de dashboard centralisé multi-clients)
- Onboarding manuel (pas de provisioning automatique)
- Dette technique à terme si scaling > 20 clients
- Joomla = image "vieillissante" auprès des développeurs (mais invisible pour le client final)

---

## Option 2 — Stack moderne (Laravel + Vue 3)

### Principe
Réécrire le produit sur une stack full-stack moderne, avec architecture multi-tenant native et interface d'administration centralisée.

### Composants envisagés

| Rôle | Composant |
|---|---|
| Backend | Laravel 11 (PHP 8.3) |
| Frontend | Vue 3 + Inertia.js ou Nuxt |
| Base de données | MySQL 8 / PostgreSQL |
| Multi-tenant | Stancl/tenancy (Laravel package) |
| Auth | Laravel Breeze / Jetstream |
| Emails | Laravel Mail + SMTP / Resend |
| File storage | S3-compatible (MinIO en self-hosted) |
| Déploiement | Docker + Coolify ou Forge |

### Avantages
- Architecture SaaS native (multi-tenant, dashboard central)
- API-first → intégrations futures (WeChat, GAEL, Apolearn)
- Recrutement développeurs plus facile
- Scalabilité réelle

### Limites
- **6–12 mois de développement minimum** avant MVP livrable
- Complexité solo très élevée (backend + frontend + infra + métier)
- Risque de sur-engineering avant validation marché

---

## Option 3 — Hybride (recommandée phase 2)

### Principe
Démarrer avec Option 1 (Joomla, déploiements manuels) pour valider le marché et générer les premiers revenus. Développer en parallèle une couche d'administration centrale en Laravel qui prend le relais à partir de ~10 clients.

```
Phase 1 (0–10 clients)     →  Joomla packagé, déploiement manuel
Phase 2 (10–30 clients)    →  Dashboard central + provisioning semi-auto
Phase 3 (30+ clients)      →  Migration vers architecture multi-tenant native
```

---

## Infrastructure cible

### Hébergement par client

| Contexte | Solution recommandée | Coût estimé |
|---|---|---|
| Chine continentale | Alibaba Cloud / Tencent Cloud (ICP requis) | ~50–100 €/mois |
| HK / Taïwan / Singapour | Hetzner Cloud (Singapore) ou Vultr | ~15–30 €/mois |
| Europe / Afrique / Amériques | Hetzner (EU) ou Gandi SD6 | ~10–25 €/mois |

### Tooling de déploiement (à construire)
- Script d'installation automatisé (Bash ou Ansible)
- Template Joomla pré-configuré exportable (`.jpa` via Akeeba Backup)
- Checklist d'onboarding client (DNS, SMTP, contenus de base)

---

## Intégrations prioritaires

| Priorité | Intégration | Complexité | Valeur |
|---|---|---|---|
| P1 | **GAEL** (export candidats examens) | Moyenne | Très haute — différenciateur clé |
| P1 | **Alipay / WeChat Pay** | Haute (licence marchande CN) | Indispensable pour clients Chine |
| P2 | **Apolearn** (LMS) | Moyenne | Utile pour cours en ligne |
| P2 | **Stripe / PayPal** | Faible | Clients hors Chine |
| P3 | **WeChat Official Account** (notifications) | Haute | Valeur ajoutée Chine |
| P3 | **API IFALAC** (si existante) | Inconnue | Réseau institutionnel |

---

## Sécurité & conformité

| Point | Action |
|---|---|
| Données personnels apprenants | RGPD (UE) + PIPL (Chine) — politique de confidentialité dédiée |
| Paiements | Jamais stocker CB → gateway tiers uniquement |
| Accès admin | 2FA obligatoire, audit log |
| Sauvegardes | Akeeba Backup quotidien + snapshot VPS hebdo |
| Certificats SSL | Let's Encrypt auto-renouvellement |

---

## Estimation de l'effort de productisation (MVP)

À partir de la base Kunming, pour livrer un premier client opérationnel :

| Tâche | Effort estimé |
|---|---|
| Nettoyer et généraliser le template (suppression contenu Kunming) | 2–3 jours |
| Créer un script d'installation / configuration de base | 3–5 jours |
| Documenter le processus d'onboarding | 2 jours |
| Adapter FaLang pour une 2e langue locale (ex : vietnamien, coréen) | 3–5 jours |
| Tester sur un VPS propre (hors Gandi) | 1–2 jours |
| **Total MVP livrable** | **~3 semaines** |

---

## Décision recommandée

> **Partir sur Option 1 (Joomla packagé) pour le MVP 2026.**
> Objectif : 2–3 premiers clients AF Asie avant fin 2026, revenus récurrents établis.
> Initier Option 3 (couche centrale) dès que le modèle est validé commercialement.

La stack Joomla n'est pas un compromis : c'est une force pour ce marché.
Les directeurs d'AF ne voient pas le CMS — ils voient un site qui fonctionne, des inscriptions qui arrivent, et des examens bien gérés.

---

*Prochaine étape suggérée : définir le périmètre exact du package MVP et la checklist d'onboarding.*
