# HOW-TO — Créer un nouveau projet client depuis BP

Guide complet pour démarrer un nouveau site WordPress depuis le blueprint Webrocket.
À suivre dans l'ordre, étape par étape.

---

## Prérequis

Avant de commencer, tu dois avoir installé sur ton PC :

- [Local by Flywheel](https://localwp.com/) — pour le développement local
- [Git](https://git-scm.com/) — pour la gestion du code
- [FileZilla](https://filezilla-project.org/) — pour transférer des fichiers sur SiteGround
- Un terminal (Git Bash, ou le terminal intégré dans VS Code)
- Accès au GitHub de Webrocket (`hichemhamdani`)
- Accès au Site Tools du site SiteGround concerné

Tu dois aussi avoir reçu de Hichem :
- Le fichier `bp-starter.sql` (base de données de départ — trop gros pour Git, partagé en interne)
- La clé privée SSH `webrocket_sg` (à placer dans `C:\Users\TON_NOM\.ssh\webrocket_sg`)

---

## Étape 1 — Créer le dépôt GitHub du projet client

1. Aller sur [github.com/hichemhamdani](https://github.com/hichemhamdani)
2. Cliquer **New repository**
3. Nommer le repo : `nom-du-client-webrocket` (ex : `dupont-webrocket`)
4. Laisser le repo **vide** (ne pas cocher README ni .gitignore)
5. Cliquer **Create repository**
6. Copier l'URL du repo (ex : `https://github.com/hichemhamdani/dupont-webrocket.git`)

---

## Étape 2 — Cloner BP et le connecter au nouveau repo

Ouvre un terminal et exécute :

```bash
# Cloner BP depuis GitHub
git clone https://github.com/hichemhamdani/BP-Webrocket.git dupont-webrocket
cd dupont-webrocket

# Pointer vers le nouveau repo GitHub du client
git remote set-url origin https://github.com/hichemhamdani/dupont-webrocket.git

# Pousser le code BP sur le nouveau repo
git push -u origin main
```

---

## Étape 3 — Créer le site dans Local by Flywheel

1. Ouvrir **Local by Flywheel**
2. Cliquer **+** en bas à gauche → **Create a new site**
3. Nom du site : `dupont` (ou le nom du client)
4. Choisir un domaine local : `dupont.local`
5. Laisser les paramètres par défaut → **Create site**
6. Une fois créé, aller dans l'onglet **Database** → noter le **port MySQL** affiché (ex : `10045`)

> **Important** : ce port est unique pour chaque site Local. Tu en auras besoin dans l'étape 5.

---

## Étape 4 — Placer les fichiers du projet dans Local

Local a créé un dossier vide pour ton site. Tu dois y mettre les fichiers du projet.

1. Dans Local, clic droit sur le site → **Go to site folder**
2. Ouvrir le dossier `app/public/`
3. **Supprimer tout son contenu** (c'est une installation WordPress vide de Local)
4. **Copier-coller** à la place le contenu du dossier `dupont-webrocket/` cloné à l'étape 2

---

## Étape 5 — Créer le wp-config.php local

Le fichier `wp-config.php` n'est pas dans Git (il contient des mots de passe). Tu dois le créer manuellement.

1. Dans le dossier `app/public/`, copier `wp-config-sample.php` et renommer la copie en `wp-config.php`
2. Ouvrir `wp-config.php` avec VS Code
3. Modifier ces lignes :

```php
define( 'DB_NAME', 'local' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_HOST', '127.0.0.1:10045' );  // ← ton port MySQL noté à l'étape 3
$table_prefix = 'yqj_';
```

> **Erreur courante** : ne pas mettre `localhost` — Local by Flywheel n'accepte pas `localhost`,
> il faut obligatoirement `127.0.0.1:PORT`.

---

## Étape 6 — Importer la base de données en local

1. Dans Local, cliquer **Open AdminNeo** (ou **Open site** puis aller sur `dupont.local/adminer`)
2. Se connecter (les credentials sont pré-remplis)
3. Sélectionner la base `local` dans le menu de gauche
4. Cliquer sur l'onglet **Import**
5. Charger le fichier `bp-starter.sql` (reçu de Hichem)
6. Cliquer **Execute**

L'import peut prendre quelques minutes.

---

## Étape 7 — Mettre à jour les URLs en local

Une fois l'import terminé, les URLs dans la base pointent encore vers `bp.local`.
Il faut les mettre à jour pour ton nouveau domaine local.

Dans AdminNeo, aller dans l'onglet **SQL** et exécuter :

```sql
UPDATE yqj_options
SET option_value = REPLACE(option_value, 'bp.local', 'dupont.local')
WHERE option_name IN ('siteurl', 'home');
```

Ça doit retourner **2 lignes modifiées**.

---

## Étape 8 — Vérifier que le site fonctionne en local

1. Dans Local, cliquer **Open site** → `dupont.local` doit s'afficher dans le navigateur
2. Aller sur `dupont.local/wp-admin`
3. Se connecter avec les credentials de la base BP (les mêmes que bp.local)

Si tu vois le site et l'admin → tout est bon, tu peux passer à la configuration SiteGround.

---

## Étape 9 — Configurer le site sur SiteGround

### 9a. Créer la base de données sur SiteGround

1. Aller dans **Site Tools** du site SiteGround → **MySQL** → **Databases**
2. Créer une nouvelle base de données → noter son nom (ex : `dbok8fy3dd7meg`)
3. Aller dans **MySQL** → **Users** → noter le nom d'utilisateur et son mot de passe

### 9b. Initialiser Git sur SiteGround (une seule fois)

Se connecter en SSH (les credentials SSH sont dans Site Tools → **Devs** → **SSH**) :

```bash
ssh -p PORT USERNAME@HOSTNAME
cd /home/customer/www/dupont.roxy.cloud/public_html
git init
git remote add origin https://hichemhamdani:GH_TOKEN@github.com/hichemhamdani/dupont-webrocket.git
git fetch origin dev
git reset --hard origin/dev
```

Remplacer `GH_TOKEN` par le token GitHub (demander à Hichem).

### 9c. Créer le wp-config.php sur SiteGround

Toujours en SSH, exécuter ces commandes une par une :

```bash
cp /home/customer/www/dupont.roxy.cloud/public_html/wp-config-sample.php \
   /home/customer/www/dupont.roxy.cloud/public_html/wp-config.php

sed -i "s/database_name_here/NOM_DE_LA_DB/" /home/customer/www/dupont.roxy.cloud/public_html/wp-config.php
sed -i "s/username_here/NOM_UTILISATEUR_DB/" /home/customer/www/dupont.roxy.cloud/public_html/wp-config.php
sed -i "s/\$table_prefix = 'wp_';/\$table_prefix = 'yqj_';/" /home/customer/www/dupont.roxy.cloud/public_html/wp-config.php
```

Pour le mot de passe (utiliser Python si le mot de passe contient des caractères spéciaux) :

```bash
python3 -c "
content = open('/home/customer/www/dupont.roxy.cloud/public_html/wp-config.php').read()
content = content.replace('password_here', 'MOT_DE_PASSE_DB')
open('/home/customer/www/dupont.roxy.cloud/public_html/wp-config.php', 'w').write(content)
print('OK')
"
```

> **Note** : sur SiteGround, `DB_HOST` reste `localhost` (contrairement à Local).

### 9d. Importer la base de données sur SiteGround

phpMyAdmin limite les imports à 256 Mo — pour un gros fichier, passer par SFTP + SSH.

**Via FileZilla (SFTP) :**
1. Ouvrir FileZilla
2. Se connecter avec les credentials SSH (Host, Username, Password, Port)
3. Uploader `bp-starter.sql.gz` dans `/home/customer/www/dupont.roxy.cloud/`

**Via SSH, importer :**
```bash
zcat /home/customer/www/dupont.roxy.cloud/bp-starter.sql.gz | mysql -u NOM_UTILISATEUR_DB -p NOM_DE_LA_DB
```

Il demandera le mot de passe DB.

### 9e. Mettre à jour les URLs sur SiteGround

Dans phpMyAdmin de SiteGround → sélectionner la base → onglet **SQL** :

```sql
UPDATE yqj_options
SET option_value = REPLACE(option_value, 'bp.local', 'dupont.roxy.cloud')
WHERE option_name IN ('siteurl', 'home');
```

---

## Étape 10 — Configurer le déploiement automatique GitHub Actions

### 10a. Autoriser la clé SSH webrocket_sg sur SiteGround

> **Important** : sur SiteGround, les clés SSH sont par site. Même si tu as déjà autorisé
> cette clé sur un autre site, tu dois la réautoriser pour chaque nouveau site.

1. Aller dans **Site Tools** du nouveau site → **Devs** → **SSH Keys Manager**
2. Cliquer **Create/Import** → **Import**
3. Coller le contenu de `webrocket_sg.pub` (la clé publique)
4. Cliquer **Authorize**

### 10b. Mettre à jour deploy.yml

Dans le dossier du projet, ouvrir `.github/workflows/deploy.yml` et remplacer `NOM-DU-REPO` par le vrai nom du repo :

```yaml
git pull https://hichemhamdani:${{ secrets.GH_TOKEN }}@github.com/hichemhamdani/dupont-webrocket.git dev
```

### 10c. Ajouter les secrets GitHub

Sur GitHub → repo du projet → **Settings** → **Secrets and variables** → **Actions** → **New repository secret** :

| Secret | Valeur |
|--------|--------|
| `SSH_HOST` | `c113951.sgvps.net` (le hostname SiteGround) |
| `SSH_USERNAME` | Username SSH du site SiteGround |
| `SSH_PORT` | Port SSH (dans Site Tools → Devs → SSH) |
| `SSH_PATH` | `/home/customer/www/dupont.roxy.cloud/public_html` |
| `SSH_PRIVATE_KEY` | Contenu de `C:\Users\TON_NOM\.ssh\webrocket_sg` |
| `GH_TOKEN` | Token GitHub (demander à Hichem) |

> **Erreur courante pour SSH_PRIVATE_KEY** : copier la clé avec PowerShell pour éviter
> les problèmes de fins de ligne Windows :
> ```powershell
> Get-Content "C:\Users\TON_NOM\.ssh\webrocket_sg" -Raw | Set-Clipboard
> ```
> Puis coller directement dans le champ GitHub.

---

## Étape 11 — Tester le déploiement automatique

1. Faire une modification dans un fichier du thème en local
2. Commiter et pusher sur `dev` :

```bash
git add .
git commit -m "Test déploiement"
git push origin dev
```

3. Aller sur GitHub → onglet **Actions** → vérifier que le workflow devient vert
4. Rafraîchir le site SiteGround pour voir la modification

---

## Résumé des erreurs courantes

| Erreur | Cause | Solution |
|--------|-------|----------|
| `Error establishing a database connection` en local | `DB_HOST = localhost` | Utiliser `127.0.0.1:PORT` (port dans Local → Database) |
| `Error establishing a database connection` sur SiteGround | `wp-config.php` absent ou mauvais credentials | Vérifier le wp-config.php via SSH |
| `0 lignes modifiées` dans la requête SQL des URLs | Les URLs pointent vers un autre domaine | Vérifier avec `SELECT option_value FROM yqj_options WHERE option_name = 'siteurl'` |
| `ssh: no key found` dans GitHub Actions | Clé copiée avec mauvaises fins de ligne | Copier avec PowerShell : `Get-Content ... -Raw \| Set-Clipboard` |
| `504 Gateway Timeout` sur import phpMyAdmin | Fichier trop gros | Passer par SFTP + import SSH |
| `Permission denied` SSH SiteGround | Clé pas autorisée sur ce site | Importer la clé publique dans Site Tools → SSH Keys Manager |

---

## Travailler avec Claude Code

Claude lit automatiquement `CLAUDE.md` au démarrage. Pour reprendre le contexte après une pause :

```
Lis tous les commits git et résume ce qui a été fait et pourquoi.
```

Tous les commits faits avec Claude portent le tag `Co-Authored-By: Claude Sonnet 4.6` dans l'historique Git.
