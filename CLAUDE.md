# BP — Blueprint WordPress (Webrocket)

Ce fichier est lu automatiquement par Claude Code au démarrage de chaque session.
Il garantit que tous les membres de l'équipe travaillent avec le même contexte Claude.

---

## Présentation du projet

**BP** (Blue Print) est le projet WordPress de référence de Webrocket.
Il sert de base pour tous les nouveaux sites créés par l'agence.

Quand on démarre un nouveau projet client :
1. On clone ce dépôt
2. On renomme le thème et adapte la config
3. On pousse vers un nouveau dépôt GitHub dédié au client

---

## Stack technique

- **CMS** : WordPress
- **Thème custom** : `jimee-theme` (à renommer selon le projet client)
- **WooCommerce** : installé et configuré comme base e-commerce
- **Plugins inclus** :
  - `woocommerce` — boutique
  - `wordpress-seo` (Yoast) — SEO
  - `sg-cachepress` — cache SiteGround
  - `sg-security` — sécurité SiteGround
  - `woo-bordereau-generator` — plugin custom Webrocket

---

## Structure importante

```
wp-content/
├── themes/
│   └── jimee-theme/         ← thème principal à modifier
│       ├── functions.php
│       ├── header.php / footer.php
│       ├── assets/
│       ├── inc/
│       ├── template-parts/
│       └── woocommerce/     ← overrides WooCommerce
├── plugins/                 ← plugins tracés dans Git
└── mu-plugins/              ← must-use plugins
```

---

## Conventions de travail

### Nommage
- Classes PHP : `PascalCase`
- Fonctions PHP : `snake_case` avec préfixe du projet (ex: `bp_get_header()`)
- Fichiers de template : `kebab-case.php`
- Variables JS : `camelCase`

### Thème
- Toutes les personnalisations vont dans `functions.php` ou dans `inc/`
- Les overrides WooCommerce se placent dans `themes/jimee-theme/woocommerce/`
- Ne pas modifier directement le core WordPress ni les plugins tiers

### Git
- Branche `main` → état stable, prêt à être cloné
- Branche `dev` → travail en cours
- Branches `feature/nom-de-la-feature` pour les nouvelles fonctionnalités
- Chaque commit fait avec Claude Code porte le tag `Co-Authored-By: Claude`

---

## Ce que Claude doit savoir

- Ce projet est un **blueprint** : les modifications doivent rester génériques et réutilisables
- Éviter le code spécifique à un client dans ce dépôt
- Le thème s'appelle `jimee-theme` dans ce blueprint mais sera renommé sur chaque projet client
- WooCommerce est la base e-commerce, ne pas le retirer
- L'hébergement cible est **SiteGround** (d'où les plugins sg-*)

---

## Reprendre le contexte après un clone ou une longue pause

Claude lit ce fichier automatiquement, mais **ne lit pas l'historique Git tout seul**.
Il connaît le contexte du projet (ce fichier), pas ce qui a changé récemment.

Pour que Claude se remette dans le contexte des modifications passées, commencer la session par :

```
Lis tous les commits git et résume ce qui a été fait et pourquoi.
```

Claude fera un `git log` complet et lira tous les messages de commit pour reconstituer l'historique de travail.

---

## Démarrer un nouveau projet depuis BP

```bash
git clone https://github.com/hichemhamdani/BP-Webrocket.git nom-du-projet
cd nom-du-projet
git remote set-url origin https://github.com/hichemhamdani/NOM-NOUVEAU-REPO.git
git push -u origin main
```

Puis renommer le thème et adapter `wp-config.php` pour le nouvel environnement.
