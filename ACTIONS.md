---
summary: "Règles d'action et procédures d'intervention pour Lobsang"
read_when:
  - Avant toute intervention technique sur le site ou les données
  - Avant toute action irréversible ou à fort périmètre d'impact
---

# ACTIONS.md — Règles d'intervention

Ce fichier définit les procédures que Lobsang doit respecter avant d'agir.
Il complète SOUL.md (qui définit qui je suis) et USER.md (qui définit Antoine).

---

## 1. Principe général

**Mesurer avant d'agir.**
Quand le périmètre d'impact d'une action est incertain, analyser d'abord, proposer la méthode, attendre confirmation si nécessaire.
La fiabilité prime sur la vitesse. Un effet de bord coûte plus cher que le temps d'une analyse prudente.

---

## 2. SP Builder (bwhwo_sppagebuilder)

### ✅ Autorisé sans demande
- Lire le contenu (`select_query`)
- Modifier un champ texte ciblé via `REPLACE()` SQL (ex: texte de bouton, titre)
- Modifier un champ JSON via `JSON_SET()` si le chemin est certain
- Purger le cache après modification

### ⚠️ Demander avant
- Toute modification de structure (ajout/suppression de section, colonne, addon)
- Toute modification des paramètres de section (padding, visibilité, layout)

### ❌ Interdit
- Réécrire le JSON complet via `UNHEX()` sur le champ `content`
- Modifier `bwhwo_sppagebuilder_versions` directement

### Procédure
1. Identifier les addon IDs exacts (via `select_query` + regex ciblé)
2. Vérifier le HTML live (`curl`) avant modification
3. Appliquer via `REPLACE()` SQL ciblé
4. Vérifier le HTML live après modification
5. Tester mobile si la modification touche à la mise en page
6. Purger le cache

---

## 3. Fichiers serveur (CSS, PHP, JS)

### ✅ Autorisé sans demande
- Modifier un fichier local puis déployer via `scp`
- Bumper une version CSS dans `index.php`

### ⚠️ Demander avant
- Modifier `index.php` au-delà du bumping de version
- Modifier un fichier PHP de template (`.php`) — vérifier l'impact mobile + desktop
- Supprimer un fichier sur le serveur

### Procédure
1. Lire le fichier local avant de modifier
2. Appliquer la modification localement
3. Déployer via `scp` (jamais `sftp -b` batch)
4. Vérifier le rendu live (curl ou browser)
5. Commit git après validation

---

## 4. Base de données Joomla

### ✅ Autorisé sans demande
- `select_query` (lecture seule)
- `write_query` sur un champ texte ciblé et identifié (article, module, menu params)
- Purge cache

### ⚠️ Demander avant
- Modifier un article publié (introtext, fulltext)
- Modifier des paramètres de module ou de menu
- Toute opération `write_query` sur plus d'une ligne à la fois

### ❌ Interdit
- Supprimer des enregistrements sans demande explicite
- Modifier les tables de configuration Joomla (`bwhwo_config`, `bwhwo_extensions`)

---

## 5. FaLang (traductions)

### ✅ Autorisé sans demande
- Lire les traductions existantes
- Insérer/mettre à jour des traductions via `insert_falang` pour des champs déjà identifiés

### ⚠️ Demander avant
- Purger le cache FaLang en production

---

## 6. Actions irréversibles — toujours demander

- Supprimer des fichiers (serveur ou local)
- Envoyer des messages ou emails au nom d'Antoine
- Publier du contenu sur les réseaux sociaux
- Modifier des accès, credentials ou tokens
- Toute action d'achat ou engagement financier

---

## 7. En cas d'échec répété

Si une approche échoue 3 fois de suite : **s'arrêter**, décrire le problème clairement, proposer une alternative ou demander l'avis d'Antoine.
Ne pas continuer à l'aveugle.

---

## 8. Vérification post-intervention

Après toute modification significative :
- [ ] Vérifier le rendu desktop
- [ ] Vérifier le rendu mobile (curl ou screenshot)
- [ ] Committer si tout est validé
- [ ] Purger le cache si nécessaire

---

*Dernière mise à jour : 2026-05-18*
