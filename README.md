# BP — Blueprint WordPress · Webrocket

Base de départ pour tous les projets WordPress de l'agence Webrocket.

---

## Prérequis

- PHP 8.1+
- MySQL 8.0+
- [Local by Flywheel](https://localwp.com/) (recommandé pour le dev local)
- Git
- [Claude Code](https://claude.ai/code) pour les modifications assistées par IA

---

## Démarrer en local

1. Cloner le dépôt dans Local by Flywheel ou ton environnement local
2. Copier `wp-config-sample.php` → `wp-config.php` et renseigner les credentials DB
3. Importer la base de données de référence (partagée en interne)
4. Activer le thème `jimee-theme` dans l'admin WordPress

---

## Créer un nouveau projet client depuis ce Blueprint

```bash
# 1. Cloner BP
git clone https://github.com/hichemhamdani/BP-Webrocket.git nom-du-projet
cd nom-du-projet

# 2. Pointer vers le nouveau dépôt du projet client
git remote set-url origin https://github.com/hichemhamdani/NOM-NOUVEAU-REPO.git

# 3. Mettre à jour le nom du repo dans le workflow de déploiement
# Éditer .github/workflows/deploy.yml → remplacer NOM-DU-REPO par le vrai nom

# 4. Pousser
git push -u origin main
git push -u origin dev
```

Ensuite :
- Renommer le dossier du thème (`jimee-theme` → `nom-client-theme`)
- Mettre à jour la référence dans `functions.php` et `style.css`
- Adapter `wp-config.php` pour le nouvel environnement

---

## Importer la base de données de départ

La base fait plus de 1 Go — elle n'est pas dans Git. Elle est partagée en interne (demande le lien à Hichem).

**Étapes (à faire une fois après le clone) :**

1. Créer le site dans **Local by Flywheel** → noter le port MySQL affiché dans l'onglet Database
2. Ouvrir **AdminNeo** depuis Local → sélectionner la base `local`
3. Importer le fichier `bp-starter.sql` (menu Import)
4. Créer `wp-config.php` depuis `wp-config-sample.php` avec tes credentials locaux :
   - `DB_HOST` → `127.0.0.1:TON_PORT` (le port MySQL de ton site Local)
   - `DB_NAME` → `local`
   - `DB_USER` / `DB_PASSWORD` → `root` / `root`
   - `$table_prefix` → `yqj_` (le préfixe des tables importées)
5. Mettre à jour les URLs dans AdminNeo :
   ```sql
   UPDATE yqj_options
   SET option_value = REPLACE(option_value, 'bp.local', 'monsite.local')
   WHERE option_name IN ('siteurl', 'home');
   ```

> **Pourquoi cette approche ?** Chaque dev a sa propre copie isolée de la DB.
> Si bp.local est éteint, ton site continue de fonctionner.

---

## Déploiement automatique sur SiteGround

Ce blueprint inclut un GitHub Action (`deploy.yml`) qui déploie automatiquement
sur SiteGround à chaque push sur la branche `dev`.

### Setup (à faire une fois par projet)

**1. Ajouter les secrets GitHub** — Settings → Secrets and variables → Actions :

| Secret | Valeur |
|--------|--------|
| `SSH_HOST` | Hostname SiteGround (ex: `c113951.sgvps.net`) |
| `SSH_USERNAME` | Username SSH SiteGround |
| `SSH_PORT` | Port SSH (généralement `18765`) |
| `SSH_PATH` | Chemin WordPress sur le serveur |
| `SSH_PRIVATE_KEY` | Clé privée SSH (générée sur ta machine) |
| `GH_TOKEN` | GitHub Personal Access Token (permission `repo`) |

**2. Autoriser la clé SSH sur SiteGround**

> **Important** : sur SiteGround, les clés SSH sont **par site**, pas par compte.
> Chaque site a son propre SSH Keys Manager dans son Site Tools.
> Une clé autorisée sur `site-a.roxy.cloud` ne donne pas accès à `site-b.roxy.cloud`.

Chez Webrocket on utilise une seule clé privée (`webrocket_sg`) pour tous les projets.
Il suffit de l'autoriser dans chaque nouveau site :

1. Aller dans **Site Tools du nouveau site** → Devs → SSH Keys Manager
2. Cliquer **Create/Import → Import**
3. Coller la clé publique `webrocket_sg`
4. Cliquer **Authorize**
5. Récupérer les credentials SSH de ce site (host, username, port) dans Site Tools → Devs → SSH

**3. Initialiser Git sur SiteGround** (une seule fois via SSH) :
```bash
ssh -p PORT -i ~/.ssh/nom-projet-sg USERNAME@HOSTNAME
cd /home/customer/www/NOM-DU-SITE.roxy.cloud/public_html
git init
git remote add origin https://hichemhamdani:GH_TOKEN@github.com/hichemhamdani/NOM-REPO.git
git fetch origin dev
git reset --hard origin/dev
```

Après ça, chaque `git push` sur `dev` met le site à jour automatiquement.

> **Note SiteGround** : le chemin `public_html` suit toujours ce format :
> `/home/customer/www/NOM-DU-SITE.roxy.cloud/public_html`
> Remplace juste le nom de domaine — `customer` reste identique pour tous les sites.

---

## Structure du projet

```
wp-content/
├── themes/
│   └── jimee-theme/         ← thème principal (à renommer par projet)
├── plugins/                 ← plugins inclus dans le blueprint
└── mu-plugins/              ← plugins chargés automatiquement
```

---

## Stack

| Composant | Technologie |
|-----------|------------|
| CMS | WordPress |
| E-commerce | WooCommerce |
| SEO | Yoast SEO |
| Cache | SG CachePress |
| Sécurité | SG Security |
| Hébergement cible | SiteGround |

---

## Workflow Git

| Branche | Usage |
|---------|-------|
| `main` | État stable, prêt à être cloné |
| `dev` | Développement en cours |
| `feature/xxx` | Nouvelle fonctionnalité |

### Règle : une feature = une branche = une PR vers `dev`

---

## Travailler avec Claude Code

Claude Code lit automatiquement `CLAUDE.md` au démarrage de chaque session.
Tous les commits effectués avec l'aide de Claude portent le tag `Co-Authored-By: Claude`
dans l'historique Git — c'est la traçabilité principale.

Pour démarrer une session :
```bash
claude
```

### Important : Claude ne lit pas l'historique Git automatiquement

Claude connaît le contexte du projet via `CLAUDE.md`, mais il ne sait pas ce qui a été
modifié dans les sessions précédentes. Si tu viens de cloner le projet ou de reprendre
après une pause, commence ta session par cette commande :

```
Lis tous les commits git et résume ce qui a été fait et pourquoi.
```

Cela permet à Claude de se remettre dans le contexte du travail récent avant de continuer.

---

## Équipe

Projet maintenu par **Webrocket** · [hichemhamdani](https://github.com/hichemhamdani)
