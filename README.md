# kunming-afchine.org — fichiers custom

Dépôt des fichiers personnalisés du site de l'Alliance Française de Kunming.

## Structure

```
templates/shaper_languageschool/
  index.php          — Template principal (SEO, hreflang, og:, Schema.org)

falang-inject/
  sppb5.php          — Endpoint d'administration distante (requêtes DB, cache)

robots.txt           — Directives crawl + référence sitemap
```

## Déploiement

Push sur `main` → GitHub Actions → SFTP vers Gandi Simple Hosting.

## Fichiers exclus

- Core Joomla (non modifiés)
- `configuration.php` (credentials DB — jamais dans le repo)
- `media/`, `cache/`, `tmp/`
