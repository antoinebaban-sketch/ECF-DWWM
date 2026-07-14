# Vite & Gourmand — Site Traiteur Bordeaux

Projet ECF — TP Développeur Web et Web Mobile (Studi)
**Antoine Baban** · 2026

---

## Présentation

Site vitrine et de commande pour **Vite & Gourmand**, traiteur à Bordeaux depuis 1999.
Permet aux visiteurs de consulter les menus, aux clients de passer commande et de déposer un avis.
Inclut un espace employé (gestion des menus/plats/horaires/commandes/avis) et un espace administration
(gestion des comptes employés, statistiques).

---

## Stack technique

| Couche      | Technologie |
|-------------|-------------|
| Frontend    | HTML5 · CSS3 (custom properties) · JavaScript vanilla |
| Backend     | PHP 8.1 · PDO · API REST JSON (sans framework) |
| Base SQL    | MySQL 8 · InnoDB · utf8mb4 |
| Base NoSQL  | MongoDB (statistiques "commandes par menu" de l'espace admin uniquement) |
| Auth        | Sessions PHP natives (`session_start()`, cookie de session) · bcrypt cost 12 |
| Emails      | PHP `mail()` |

---

## Structure du projet

```
projet/
├── frontend/          ← Pages HTML + CSS + JS
│   ├── index.html              Accueil
│   ├── menu.html                Catalogue des menus (filtres régime, prix, thème…)
│   ├── commande.html            Tunnel de commande
│   ├── contact.html             Formulaire contact / demande de devis
│   ├── MonCompte.html           Espace client (commandes, profil, préférences, avis)
│   ├── SeConnecter.html         Connexion
│   ├── SInscrire.html           Inscription
│   ├── reset-password.html      Réinitialisation du mot de passe
│   ├── employe.html             Login espace employé
│   ├── employe-dashboard.html   Dashboard employé
│   ├── admin.html               Login administration
│   ├── admin-dashboard.html     Dashboard admin (stats, CRUD, utilisateurs, employés)
│   ├── style.css                Feuille de styles principale
│   ├── navbar.js                Barre de navigation partagée (état connexion, panier, horaires)
│   ├── images/                  Assets visuels
│   └── vite_et_gourmand.sql     Schéma SQL + données de test
│
└── api/               ← Backend PHP
    ├── index.php            Routeur principal (CORS, session, dispatch vers les contrôleurs)
    ├── config.php           Config DB/mail/Mongo + PDO singleton
    ├── helpers.php          Réponses JSON, auth par session, mail, validations
    ├── mongodb.php          Connexion MongoDB (insert + agrégation)
    ├── .env.example         Variables d'environnement (template)
    ├── setup_admin.php      Script d'initialisation (⚠ à supprimer en prod)
    └── controllers/
        ├── auth.php         /api/auth/*  (login, register, logout, me, reset mdp)
        ├── menus.php        /api/menus/*
        ├── plats.php        /api/plats/*
        ├── commandes.php    /api/commandes/*
        ├── avis.php         /api/avis/*
        ├── utilisateurs.php /api/utilisateurs/*  (profil, RGPD, préférences)
        ├── admin.php        /api/admin/*  (employés, stats)
        ├── themes.php       /api/themes/*
        ├── regimes.php      /api/regimes/*
        ├── allergenes.php   /api/allergenes/*
        ├── horaires.php     /api/horaires/*
        └── contact.php      /api/contact/*, /api/devis/*

utile/
├── Diagramme ER Vite et gourmand.png
├── Diagramme de classes (dossier docs techniques)
├── Diagramme de séquence — Connexion / Passer une commande
├── charte_graphique_vite_et_gourmand.pdf
└── Wireframes vite et gourmand/
```

---

## Installation locale

### Prérequis
- PHP 8.1+
- MySQL 8+
- Serveur web (Apache/Nginx) avec mod_rewrite activé, ou XAMPP/WAMP/Laragon
- Extension `mongodb` pour PHP (optionnelle — voir section MongoDB)

### 1. Base de données

```sql
CREATE DATABASE vite_et_gourmand CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Importer le schéma et les données de test :

```bash
mysql -u root -p vite_et_gourmand < projet/frontend/vite_et_gourmand.sql
```

### 2. Configuration backend

```bash
cp projet/api/.env.example projet/api/.env
```

Éditer `projet/api/.env` :

```env
DB_HOST=localhost
DB_NAME=vite_et_gourmand
DB_USER=root
DB_PASS=votre_mot_de_passe
MAIL_FROM=noreply@viteetgourmand.fr
MAIL_NAME=Vite & Gourmand
APP_URL=http://localhost/ViteEtGourmand/projet/frontend

# MongoDB (optionnel — laisser vide pour désactiver le graphique "commandes par menu")
MONGO_URI=
MONGO_DB=vite_et_gourmand_logs
```

### 3. Initialisation des mots de passe

Accéder une seule fois à :

```
http://localhost/ViteEtGourmand/projet/api/setup_admin.php?secret=vg-setup-2026
```

Ce script génère les hash bcrypt pour tous les comptes de test et recopie les commandes
existantes dans MongoDB (si configuré) pour peupler les statistiques admin.
**Supprimer ce fichier après utilisation.**

### 4. Lancer le projet

Ouvrir `http://localhost/ViteEtGourmand/projet/frontend/index.html`

---

## Comptes de test

| Rôle          | Email                          | Mot de passe   |
|---------------|--------------------------------|----------------|
| Administrateur | admin@viteetgourmand.fr       | `Admin2026!`   |
| Employé       | employe@viteetgourmand.fr      | `Employe2026!` |
| Client 1      | jean.martin@email.fr           | `Client2026!`  |
| Client 2      | sophie.b@email.fr              | `Client2026!`  |
| Client 3      | pierre.d@email.fr              | `Client2026!`  |

---

## Endpoints API principaux

```
POST   /api/auth/login
POST   /api/auth/register
POST   /api/auth/logout
GET    /api/auth/me                    → utilisateur connecté (session)
POST   /api/auth/forgot-password
POST   /api/auth/reset-password

GET    /api/menus              → catalogue (filtres: theme_id, regime_id, prix_min, prix_max, personnes, q)
GET    /api/menus/populaires   → 3 menus les plus commandés
GET    /api/menus/{id}         → détail (plats, régimes, allergènes, images)

POST   /api/commandes                   → créer une commande (auth client)
GET    /api/commandes/mes-commandes
GET    /api/commandes                   → toutes (employé/admin)
PUT    /api/commandes/{id}              → modifier (client, tant que "en_attente")
PUT    /api/commandes/{id}/statut       → changer statut (employé/admin)
PUT    /api/commandes/{id}/annuler      → annuler (client, tant que "en_attente")
GET    /api/commandes/{id}/historique   → suivi des statuts

GET    /api/avis               → avis validés (public)
POST   /api/avis               → déposer un avis (auth, commande terminée)
PUT    /api/avis/{id}/validation → valider/refuser (employé/admin)

GET    /api/admin/stats        → CA par mois/menu (MySQL) + commandes par menu (MongoDB)
GET    /api/admin/utilisateurs → tous les comptes (admin)
POST   /api/admin/employes     → créer un employé (admin)

POST   /api/contact
POST   /api/devis
```

---

## Règles métier implémentées

- **Remise 10 %** : automatiquement appliquée si `nb_personnes − minimum >= 5`
- **Frais de livraison** : 5 € de base + 0,59 €/km pour livraison hors Bordeaux
- **Conditions de prestation** : affichées en évidence dans la fiche menu et le tunnel de commande
- **Avis** : uniquement déposable pour une commande avec statut `terminée`, un seul avis par commande
- **Statuts commande** : `en_attente → acceptée → en_preparation → en_cours_livraison → livrée → retour_materiel → terminée` (ou `annulée`)
- **Modification/annulation client** : possible librement tant que la commande est `en_attente`
- **Annulation employé** : impossible sans avoir renseigné un motif **et** un mode de contact (le client doit avoir été prévenu avant)
- **Emails automatiques** : bienvenue à l'inscription, confirmation de commande, retour matériel (J+10, 600 €), invitation avis (statut terminée), création de compte employé
- **Mot de passe** : minimum 10 caractères, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial

---

## Sécurité

- Authentification par **session PHP** côté serveur (pas de token stocké côté client)
- Mots de passe hashés bcrypt cost 12
- Validation des rôles côté serveur sur chaque endpoint protégé (`roleRequired()`)
- Pas d'injection SQL (PDO avec requêtes préparées uniquement)
- Échappement HTML systématique à l'affichage (`escapeHtml()` côté client, `sanitize()` côté serveur)
- CORS restreint à l'origine de la requête (nécessaire pour partager le cookie de session)
- `setup_admin.php` protégé par clé secrète — **à supprimer en production**

---

## MongoDB — usage volontairement limité

Le sujet impose l'usage d'une base non relationnelle pour le graphique "nombre de commandes
par menu" de l'espace admin. C'est le seul usage de MongoDB dans ce projet : à chaque commande
créée, un document minimal (`menu_id`, `menu_titre`, `montant`, `date`) est inséré dans la
collection `commandes`, puis agrégé (`$group`/`$sum`) pour le graphique.

Si `MONGO_URI` est vide dans `.env`, l'application fonctionne normalement — le graphique
affiche simplement "Aucune donnée".

---

## Déploiement en production (hébergement mutualisé)

### Structure cible sur le serveur

```
public_html/          ← ou www/
├── index.html
├── menu.html
├── ... (tous les .html)
├── style.css
├── navbar.js
├── images/
└── api/
    ├── index.php
    ├── config.php
    ├── helpers.php
    ├── mongodb.php
    ├── .htaccess
    ├── .env          ← créer manuellement (ne jamais versionner)
    └── controllers/
```

### Étapes

**1. Transférer les fichiers via FTP (FileZilla)**
- `projet/frontend/*` → `public_html/`
- `projet/api/*` → `public_html/api/`
- Ne pas transférer `.env` (le créer directement sur le serveur)

**2. Importer la base de données (phpMyAdmin)**
```sql
CREATE DATABASE nom_bdd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Puis importer `public_html/api/vite_et_gourmand.sql` (ou via l'onglet Import de phpMyAdmin).

**3. Créer `public_html/api/.env` sur le serveur**
```env
DB_HOST=localhost
DB_NAME=nom_bdd
DB_USER=nom_user_mysql
DB_PASS=mot_de_passe_mysql
MAIL_FROM=contact@votredomaine.fr
MAIL_NAME=Vite & Gourmand
APP_URL=https://votredomaine.fr
MONGO_URI=
MONGO_DB=vite_et_gourmand_logs
```

**4. Initialiser les comptes (une seule fois)**
```
https://votredomaine.fr/api/setup_admin.php?secret=vg-setup-2026
```
⚠ Supprimer `setup_admin.php` immédiatement après.

**5. Activer HTTPS**
Décommenter les 3 lignes HTTPS dans `public_html/.htaccess`.

**6. Vérifier**
- `https://votredomaine.fr` → page d'accueil
- `https://votredomaine.fr/api/menus` → JSON avec les menus
- Connexion admin, création commande test, changement de statut

### MongoDB Atlas (optionnel)
1. Créer un compte gratuit sur [cloud.mongodb.com](https://cloud.mongodb.com)
2. Créer un cluster M0 (gratuit)
3. Ajouter l'IP du serveur dans "Network Access"
4. Copier l'URI de connexion dans `.env` → `MONGO_URI`
5. Activer `extension=mongodb` dans le `php.ini` de l'hébergeur (support à contacter si nécessaire)

---

## Branches Git

| Branche     | Usage |
|-------------|-------|
| `main`      | Code stable / production |
| `develop`   | Intégration des fonctionnalités |
| `feature/*` | Une branche par fonctionnalité, fusionnée dans `develop` après test |

---

*Projet réalisé dans le cadre du TP Développeur Web et Web Mobile — Studi 2026*
