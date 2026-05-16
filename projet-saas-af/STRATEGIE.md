# Projet SaaS AF — Document de réflexion stratégique
*Version 0.1 — Mai 2026 — Antoine Lopez, AF Kunming*

---

## 1. Contexte & point de départ

### Ce qui existe déjà
Le site kunming-afchine.org constitue une base de travail concrète et documentée. Il intègre :

| Domaine | Ce qui est en place |
|---|---|
| **Site institutionnel** | Joomla 4, Helix Ultimate, SP Page Builder, multilingue FR/EN/ZH via FaLang |
| **Cours** | RSEvents Pro (calendrier, catégories, filtres), RSForm Pro (inscription, confirmation conditionnelle, emails auto) |
| **Examens** | Sessions TCF/TEF/DELF/DALF, tarifs, durées, intégration calendrier |
| **SEO** | Baidu Search Console, push quotidien, sitemap trilingue |
| **Sécurité** | Honeypot, validation serveur, CSRF, blocage Tor |
| **Performance** | Lazy load, preload fonts, CLS fixes, GTM optimisé |
| **Mobile** | Menu responsive, cards mobiles, modules conditionnels |

### Ce que cela démontre
La capacité à construire, opérer et maintenir un système complet de gestion d'une AF — de la vitrine au formulaire d'inscription en passant par les examens — sans dépendance à un prestataire externe.

---

## 2. Problème identifié dans le réseau AF/IF

Les Alliances Françaises sont ~800 entités dans le monde, souvent :
- sous-dotées techniquement (pas de DSI)
- dépendantes de prestataires locaux coûteux et peu spécialisés
- confrontées aux mêmes besoins partout : cours, inscriptions, examens, adhésions, multilingue
- avec des outils disparates, non intégrés, non maintenus

**Oncord AF/IF** (Australie) a identifié ce problème et propose une solution SaaS. Mais :
- Le produit est généraliste (basé sur Oncord, plateforme e-commerce/CMS)
- Peu adapté aux contextes non-anglophones, en particulier Asie
- Pas de support natif du marché chinois (Baidu, WeChat, multilingue ZH)
- GAEL integration = verrouillage fort, mais uniquement pour les examens

---

## 3. Positionnement envisagé

### Hypothèse de départ
Créer un **produit packagé** — pas nécessairement un SaaS from scratch — à partir de ce qui existe déjà, ciblant d'abord les **AF d'Asie-Pacifique**, puis le réseau mondial.

### Deux options de positionnement

**Option A — Produit complémentaire à Oncord**
- Cibler les besoins spécifiques Asie (Baidu SEO, WeChat, ZH, fuseaux horaires)
- Positionnement : module ou extension vendu aux AF utilisant déjà Oncord
- Avantage : marché captif, pas de concurrence frontale
- Limite : dépendance à Oncord, taille de marché réduite

**Option B — Alternative directe, verticale AF/IF**
- Produit autonome basé sur une stack éprouvée (Joomla ou autre)
- Proposition de valeur : moins cher, mieux adapté aux contextes non-anglophones, déployable en Chine
- Avantage : indépendance totale, marché plus large
- Limite : effort de vente plus important, nécessite un accompagnement à l'onboarding

**Hypothèse retenue pour la v0 : Option B, périmètre Asie-Pacifique**

---

## 4. Ce que le produit couvrirait (MVP)

Basé sur ce qui existe à Kunming :

### Module 1 — Site institutionnel
- Template AF/IF adapté (multilingue FR/EN + langue locale)
- Pages types : accueil, cours, examens, contact, actualités
- SEO local (Baidu pour Chine, Google pour reste AP)

### Module 2 — Gestion des cours
- Calendrier des cours avec catégories, filtres, tarifs
- Formulaire d'inscription en ligne (avec gestion des groupes, niveaux, sessions)
- Emails de confirmation automatiques (FR/EN/ZH)
- Suivi des inscrits

### Module 3 — Gestion des examens
- Sessions TCF/TEF/DELF/DALF
- Inscriptions en ligne avec paiement
- Emails automatiques candidats
- Export vers GAEL (à développer)

### Module 4 — Actualités & événements culturels
- Page événements avec images, dates, lieux
- Module homepage "prochains événements"
- Trilingue

### Hors MVP (phase 2)
- Portail apprenant
- Portail enseignant
- Adhésions / bibliothèque
- Intégration WeChat Pay / Alipay

---

## 5. Stack technique envisagée

| Choix | Justification |
|---|---|
| **Joomla 4/5** | Maîtrisé, extensible, hébergeable partout y compris en Chine |
| **RSEvents Pro** | Calendrier + inscriptions éprouvés |
| **RSForm Pro** | Formulaires complexes, validations, emails |
| **FaLang** | Multilingue robuste, y compris ZH |
| **Helix Ultimate + SP Builder** | Template flexible, no-code pour le client |
| **Gandi SD6 ou VPS dédié** | Hébergement EU ou Asie selon besoin |

**Alternative à considérer :** réécrire sur une stack plus moderne (Laravel + Vue) pour faciliter la distribution SaaS. Décision à prendre après validation du marché.

---

## 6. Modèle économique (hypothèses)

| Ligne | Détail | Ordre de grandeur |
|---|---|---|
| **Setup / migration** | Installation, configuration, migration données | 1 500–4 000 € |
| **Abonnement mensuel** | Hébergement + maintenance + mises à jour | 150–400 €/mois |
| **Formation** | Sessions de prise en main (directeur + équipe) | 500–1 500 € |
| **Développements spécifiques** | Sur demande | Régie ou forfait |

Potentiel sur 20 AF Asie-Pacifique : **60–200 k€/an récurrents**

---

## 7. Avantages concurrentiels clés

1. **Expertise opérationnelle réelle** : le produit est né d'une AF, pas d'une agence
2. **Maîtrise du contexte Chine** : Baidu, ZH, fuseaux, hébergement local
3. **Trilingue natif** : FR/EN + langue locale dès le départ
4. **Stack open source** : pas de lock-in éditeur, coût maîtrisé
5. **Réseau institutionnel** : légitimité AF, accès aux directeurs via l'IFALAC et la FIPF

---

## 8. Prochaines étapes

- [ ] Valider l'hypothèse avec 2–3 directeurs AF Asie-Pacifique (sondage informel)
- [ ] Identifier les AF en Chine continentale sans solution satisfaisante
- [ ] Définir le périmètre exact du MVP
- [ ] Estimer l'effort de "productisation" de la base Kunming
- [ ] Explorer le cadre juridique (structure en France, HK, ou autre)
- [ ] Contacter l'équipe Oncord (partenariat possible ?)

---

## 9. Questions ouvertes

- Nom du produit / de la société ?
- Associés éventuels (technique, commercial) ?
- Financement initial (bootstrapped vs. subvention vs. investisseur) ?
- Timing : projet parallèle à l'AF Kunming ou transition progressive ?

---

*Ce document est un point de départ. À enrichir au fil des réflexions.*
