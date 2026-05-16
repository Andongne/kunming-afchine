# MEMORY.md — Contexte durable
*Dernière mise à jour : 2026-05-16*

---

## Antoine Lopez

- Directeur de l'Alliance Française de Kunming (Chine)
- Projet personnel en cours : SaaS pour le réseau AF/IF (société HK, création 2026, solo)
- Timezone de travail : Europe/Paris (en déplacement) / Asia/Shanghai (Kunming)
- Langue par défaut : français
- Préférences : sobre, précis, direct. Pas de formules convenues. Pas de "résilience", "audacieux", "émotion palpable"
- Demande approbation avant : envoi de messages en son nom, suppression de fichiers, requêtes réseau, actions irréversibles
- Arrêter après 3 échecs consécutifs. Limiter les tâches à 10 minutes sauf indication contraire

---

## Projet principal : kunming-afchine.org

### Stack
- Joomla 4.x, PHP 8.1
- Template : Helix Ultimate `shaper_languageschool` (style_id=10)
- Page builder : SP Page Builder (table `bwhwo_sppagebuilder`, homepage id=173)
- DB prefix : `bwhwo_`
- Extensions : RSEvents Pro, RSForm Pro, FaLang Pro, Community Builder
- Hébergement : Gandi SD6 (SFTP `436b11ba-aeb3-11ef-9c89-00163e816020@sftp.sd6.gpaas.net`)
- GitHub : https://github.com/Andongne/kunming-afchine
- Dev : https://dev.kunming-afchine.org

### Accès techniques
- API endpoint : `https://kunming-afchine.org/falang-inject/sppb5.php`
- Token : `$FALANG_SECRET_TOKEN`
- Clé SSH : `/data/.ssh/id_ed25519_gandi` (`$GANDI_SSH_KEY`)
- Joomla API : `$JOOMLA_API_TOKEN` + `$JOOMLA_BASE_URL`

### IDs modules importants
| Module | ID | Rôle |
|---|---|---|
| Contact mobile | 258 | position bottom3, `d-lg-none` (mobile only) |
| Contact desktop | 214 | position bottom3, `d-none d-lg-block` |
| CTA Contact | 275 | position rse-calendar-cta |
| CTA S'inscrire | 276 | position rse-calendar-cta |
| CTA Tarifs | 277 | position rse-calendar-cta |
| Corps article FIDELIA | 282 | mod_custom |
| Prochains événements (homepage) | 283 | mod_rseventspro_upcoming, layout `_:cards` → `cards.php` |
| Upcoming events (examens) | 246 | mod_rseventspro_upcoming, layout `_:default` |

### IDs menus importants
| Menu | ID | Notes |
|---|---|---|
| Accueil | 101 | client_id=0 |
| Calendrier des cours | 1015 | filtre catégories [50,51,52,53] — PAS de CTA ici |
| Événements (parent) | 163 | level=1 |
| Actualités | 1036 | level=2, sous 163, trilingue FR/EN/ZH |
| Réservation cours | 1016 | Form 6 |

### IDs RSEvents Pro
| Élément | ID | Notes |
|---|---|---|
| Cours groupes | cat 50 | bleu #1a6bbf |
| Cours d'essai | cat 51 | vert #2a7a2a |
| Cours VIP | cat 52 | or #b8860b |
| Petits groupes | cat 53 | orange #e07b00 |
| Événements culturels | cat 54 | violet #5c4b8a |
| TCF Canada | cat 45 | rouge |
| TEF Canada | cat 47 | |
| TEFAQ | cat 48 | |
| TCF Québec | cat 49 | |
| Fête AF (juin 2026) | event 113 | location_id=3, 14h-18h UTC |
| 14 Juillet 2026 | event 114 | location_id=6, 19h-23h UTC |
| Bernard Werber | event 115 | location_id=4, 14h-18h UTC |

### Locations RSEvents Pro
- id=3 : Alliance Française de Kunming (published)
- id=4 : Puyu Bookstore
- id=6 : O'Reilly's Irish Pub

### Formulaires RSForm Pro
- Form 6 : Inscription cours (multilangue FR/EN/ZH, honeypot, anti-spam)
- Form 4 : Inscription examens (popup thank you avec QR codes)

### Fichiers CSS/JS clés
```
templates/shaper_languageschool/
  css/afk-styles.css          ← CSS custom principal
  css/mobile_cards.css        ← Cards mobiles homepage (v=20260515b)
  js/mobile-menu.js           ← Menu hamburger (debounce anti-double-toggle)
  js/main.js                  ← JS principal
  js/calendar-i18n.js         ← i18n calendrier FR/EN/ZH
  index.php                   ← FA6 + v4-shims chargés globalement
  html/mod_rseventspro_upcoming/cards.php ← Layout module 283
  html/com_rseventspro/rseventspro/default.php ← Bifurcation cat54 / examens
  html/mod_falang/default_list.php ← Switcher langue (supprime segments RSEvents)
```

---

## Règles & leçons techniques

### Déploiement CSS
- Toujours déployer le fichier CSS **avant** de bumper la version dans `index.php`
- Varnish met en cache la nouvelle URL avec l'ancien contenu si l'ordre est inversé

### SP Builder
- Homepage = `bwhwo_sppagebuilder` id=173 (PAS `bwhwo_sppagebuilder_pages`)
- Si sauvegarde échoue silencieusement → vérifier taille dans `bwhwo_sppagebuilder_versions` (tronqué = <200KB)
- Sauvegardes valides = ~900KB pour la homepage
- Ne pas utiliser `bwhwo_sppagebuilder_pages` via select_query (timeout)

### Joomla modules
- Layout `_:cards` → charge `cards.php` (PAS `default_cards.php`)
- `default_cards.php` = convention sous-template (appelé par `$this->loadTemplate('cards')`)
- Dates RSEvents Pro stockées en UTC → convertir avec `DateTime::setTimezone(Asia/Shanghai)` et utiliser `$dt->format()` (pas `date($ts)` qui ignore le TZ)

### FaLang
- Après correction traduction → purger caches Joomla serveur ET Varnish
- `language_id=4` = zh-CN
- Modules : params `d-lg-none` pour mobile-only (pas `d-md-none`)

### PHP / Joomla
- Ne pas upgrader PHP vers 8.4 avant RSEvents Pro 1.15+
- Ne pas modifier `custom_css` Helix via set_template_style_params si >32KB (erreur 500)
- Ne pas toucher aux fonts Helix Ultimate (risque corruption JSON `template_styles` id=10)

### Sécurité
- Honeypot + validation Niveau (Form 6) actifs
- Blocage .htaccess : plages Tor (192.42.116, 185.220.101, 109.70.100, 38.135.24, 45.80.158)
- CSRF activé sur RSForm Pro

### Baidu SEO
- Cron daily push : 10 URL/jour (quota compte nouveau)
- Fichier vérification Baidu permanent requis à la racine
- ~138 URLs restantes en file (au 15/05)

---

## Projet SaaS AF (long terme)

- Dossier : `/data/.openclaw/workspace/projet-saas-af/`
- Fichiers : `STRATEGIE.md`, `STACK-TECHNIQUE.md`
- Modèle de référence : https://www.afoncord.com
- Cible : AF Asie-Pacifique (différenciateur : Chine, ZH, Baidu, WeChat Pay)
- Stack retenue MVP : Joomla packagé (base Kunming), single-tenant
- Société : HK, création 2026, solo
- Effort MVP estimé : ~3 semaines à partir de la base Kunming
- Intégrations P1 : GAEL (examens), Alipay/WeChat Pay

---

## Conventions de fin de session

1. Écrire `memory/YYYY-MM-DD.md` (notes de session)
2. Git commit + push `kunming-afchine` (repo GitHub)
3. Mettre à jour `templates/shaper_languageschool/css/custom.css` + `template_custom_css.css` (snapshot Helix DB)
