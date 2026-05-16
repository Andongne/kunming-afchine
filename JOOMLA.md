---
summary: "Contexte projet Joomla — kunming-afchine.org"
read_when:
  - Toute tâche Joomla (CSS, modules, menus, SP Builder, RSEvents, RSForm)
---

# JOOMLA.md — Contexte projet kunming-afchine.org

## Stack
- **Joomla** : 5.4 (production) / PHP 8.1
- **Template** : Helix Ultimate — `shaper_languageschool` (style_id=10)
- **Page builder** : SP Page Builder (table `bwhwo_sppagebuilder`)
- **DB prefix** : `bwhwo_`
- **Extensions clés** : RSEvents Pro, RSForm Pro, FaLang, Community Builder

## Accès
- **API endpoint** : `https://kunming-afchine.org/falang-inject/sppb5.php`
- **Token** : `$FALANG_SECRET_TOKEN` (env)
- **SFTP** : `436b11ba-aeb3-11ef-9c89-00163e816020@sftp.sd6.gpaas.net` / clé `$GANDI_SSH_KEY`
- **Joomla API** : `$JOOMLA_API_TOKEN` + `$JOOMLA_BASE_URL`

## Fichiers template (workspace + serveur)
```
templates/shaper_languageschool/
  css/afk-styles.css        ← CSS custom (version courante : v=20260513c)
  css/custom.css            ← snapshot custom_css Helix DB
  css/template_custom_css.css ← snapshot template_custom_css DB
  js/mobile-menu.js         ← menu hamburger mobile
  js/main.js                ← JS principal (jQuery, offcanvas, menus)
  index.php                 ← template entry point
```

## IDs importants
| Élément | ID |
|---|---|
| Template style Helix | 10 |
| Menu calendrier cours | 1015 |
| SP Builder FIDELIA | 327 |
| Article Joomla FIDELIA | 143 |
| Module corps FIDELIA | 282 |
| Catégories cours (RSEvents) | 50-53 |
| Catégories exams (RSEvents) | 45-49 |
| Form inscription | 6 |
| Module contact mobile | 258 |

## Conventions de déploiement
1. Modifier localement dans `workspace/kunming-afchine/`
2. Déployer via SCP : `scp -i $GANDI_SSH_KEY fichier user@sftp.sd6.gpaas.net:vhosts/kunming-afchine.org/htdocs/...`
3. Bumper la version CSS dans `index.php` si `afk-styles.css` modifié
4. `purge_cache` via sppb5.php après toute modification DB ou CSS
5. `git commit + push origin main` en fin de session

## Règles CSS importantes
- Header : `height: 140px !important` (custom_css Helix)
- Hamburger : `position: absolute; top:0; right:15px` (afk-styles.css, <992px)
- Breakpoints mobiles : 768px (top-bar hidden), 991px (d-lg-none)
- Font Awesome 6 chargé globalement (index.php)

## À ne pas faire
- Ne pas toucher à `bwhwo_sppagebuilder_pages` via select_query (timeout) → utiliser `bwhwo_sppagebuilder`
- Ne pas upgrader PHP en 8.4 avant RSEvents Pro 1.15+
- Ne pas modifier `custom_css` Helix via set_template_style_params si >32KB (500 error)
