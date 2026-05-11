# EgliseHub Platform

EgliseHub est une plateforme **SaaS (Software as a Service)** permettant aux ministères et églises de créer et gérer facilement leur site web.

La plateforme fonctionne avec une architecture **multi-tenant basée sur les sous-domaines**, ce qui permet à plusieurs ministères d'utiliser la même application tout en gardant leurs données séparées.

Exemples :

crc.eglisehub.com  
eglisevie.eglisehub.com  
royaumeeglise.eglisehub.com  

Chaque ministère possède :

- son site public
- son tableau de bord d'administration
- ses pages personnalisées
- ses articles et événements
- ses paramètres de personnalisation

---

# Sommaire

- Présentation
- Architecture
- Technologies utilisées
- Architecture multi-tenant
- Gestion des rôles
- Installation Backend
- Installation Frontend
- Structure du projet
- Authentification
- API Endpoints
- Déploiement
- CI/CD
- Tests
- Roadmap
- Licence

---

# Présentation

EgliseHub est une plateforme permettant de résoudre un problème fréquent :  
beaucoup d'églises et ministères ne possèdent pas de site web ou n'ont pas les compétences techniques pour en créer un.

EgliseHub permet donc de :

- créer un site pour un ministère en quelques minutes
- gérer le contenu via une interface simple
- publier des articles et enseignements
- gérer les événements
- personnaliser l'apparence du site

---

# Architecture

La plateforme est composée de deux parties principales :

Backend API  
Frontend Application

Le backend fournit les services suivants :

- authentification
- gestion des ministères
- gestion des utilisateurs
- gestion du contenu
- gestion des médias

Le frontend consomme ces API pour afficher :

- les sites publics
- les dashboards d'administration

---

# Architecture Multi-Tenant

EgliseHub utilise une architecture **multi-tenant par sous-domaine**.

Chaque ministère est identifié par son **slug**.

Exemple :

crc.eglisehub.com

Le backend lit le host de la requête HTTP :

Host: crc.eglisehub.com

Puis extrait :

slug = crc

Le système charge ensuite les données associées à ce ministère.

---

# Diagramme d'architecture

                           ┌─────────────────────────────────────────────────┐
                                          │ INTERNET │
                           └────────────────────┬────────────────────────────┘
                                                │
                                                ▼
                                        ┌─────────────────┐
                                         │ eglisehub.com │
                                        └────────┬─────────┘
                                                 │
                                 ┌───────────────┼───────────────┐
                                 │               │               │
                                 ▼               ▼               ▼
                        ┌───────────────┐ ┌───────────────┐ ┌───────────────┐
                         │ Super Admin │   │ Ministries │     │ Ministries │
                        └───────┬───────┘ └───────┬───────┘ └───────┬───────┘
                                                │ │ │
                                  │ ┌─────────────┼─────────────┐ │
                                              │ │ │ │ │
                                              │ ▼ ▼ ▼ │
                               │ ┌─────────┐ ┌─────────┐ ┌─────────┐
                               │ │crc.eglise│ │vieeglise│ │royaume │
                               │ │hub.com │ │.eglise │ │.eglise │
                                 │ │ │ │hub.com │ │hub.com │
                               │ └─────────┘ └─────────┘ └─────────┘
                                                │ │ │
                                   └──────────────┼─────────────┘
                                                  │
                                                  ▼
                                        ┌─────────────────────┐
                                        │Frontend Application │
                                        └──────────┬───────────┘
                                                   │
                                                   ▼
                                        ┌─────────────────────┐
                                        │Backend API (Node.js)│
                                        └──────────┬───────────┘
                                                   │
                                                   ▼
                                       ┌─────────────────────┐
                                         │ Base de données │
                                       └─────────────────────┘

                                                text



---

## Technologies utilisées

### Backend
- PHP 8.2
- Laravel 12
- SQLite (dev) / MySQL (production)
- Sanctum Token Authentication
- API Resources
- l5-swagger (OpenAPI 3.0)

### Frontend
- Vue.js
- Vue Router
- Pinia
- Axios
- TailwindCSS

### DevOps
- Docker
- GitHub Actions
- Nginx

---

## Gestion des rôles

Le système possède trois rôles principaux.

### Super Admin
- gère toute la plateforme
- crée les ministères
- crée les administrateurs
- accède à tous les dashboards

### Admin Ministère
- gère le site de son ministère
- modifie les pages
- publie des articles
- gère les événements

### Visitor
- consulte le site public
- lit les articles
- consulte les événements

---

## Base de données

Principales tables :
- users
- ministries
- pages
- page_sections
- page_contents
- articles
- events
- media
- settings

---

## Authentification

Le système utilise **JWT (JSON Web Token)**.

Processus :
1. utilisateur envoie email et password
2. backend vérifie les informations
3. backend génère un token JWT
4. frontend utilise ce token pour accéder aux routes protégées

---

## Installation Backend

### Requirements
- Node.js >= 18
- MySQL >= 8
- NPM >= 9

### Cloner le projet
```bash
git clone https://github.com/yourusername/eglisehub-api.git
cd eglisehub-api
```

### Installer les dépendances
```bash
npm install
```

### Configuration
Créer le fichier .env

#### Linux / Mac

```bash
cp .env.example .env
```

#### Windows

```bash
copy .env.example .env
```
### Exemple de configuration

```env
APP_NAME=EgliseHub
APP_PORT=5000
NODE_ENV=development

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=eglisehub
DB_USER=root
DB_PASSWORD=

JWT_SECRET=supersecretkey
```

### Lancer le serveur
```bash
npm run dev
```

## Installation Frontend
Requirements
- [Node.js >= 18](https://nodejs.org/en/download)

- [Yarn >= 1.22](https://classic.yarnpkg.com/lang/en/docs/install)

- [Vue CLI](https://cli.vuejs.org/guide/installation.html) ou [Vite](https://vite.dev/)

### Installer les dépendances
```bash
yarn install
```

### Lancer le serveur
```bash
yarn dev
```

#### Build production
```bash
yarn build
```

### Structure du projet
```text
eglisehub
│
├── backend
│   ├── controllers
│   ├── routes
│   ├── middlewares
│   ├── models
│   ├── services
│   ├── config
│   └── server.js
│
├── frontend
│   ├── src
│   ├── components
│   ├── layouts
│   ├── router
│   ├── store
│   └── views
│
└── docs
```

## API Endpoints
```bash
Auth
POST /api/auth/login

POST /api/auth/register
```

```bash
Ministries
GET /api/ministries

POST /api/ministries

PUT /api/ministries/:id
```

```bash
Pages
GET /api/pages

POST /api/pages

PUT /api/pages/:id
```

```bash
Articles
GET /api/articles

POST /api/articles

DELETE /api/articles/:id
```

## Swagger Documentation
Swagger permet de tester toutes les routes API.

### Local :

```text
http://localhost:5000/api/docs
```

### Production :

```text
https://api.eglisehub.com/docs
```

## Déploiement
La plateforme peut être déployée avec :

- Docker

- Nginx

- GitHub Actions

- Netlify (frontend)

### CI/CD
Le pipeline CI/CD peut :

- build le backend

- lancer les tests

- build le frontend

- déployer automatiquement


### Exemple pipeline GitHub Actions :

```yaml
on:
  push:
    branches:
      - main
```

## Tests
### Pour lancer les tests :

```bash
npm run test
```

### Pour la couverture de tests :

```bash
npm run test:coverage
```

## Roadmap
### Version MVP
- gestion ministères

- gestion pages

- gestion articles
  
- gestion des events

- dashboard admin

### Version 2
- dons en ligne

- live streaming

- gestion membres

### Version 3
- application mobile

- notifications

- analytics

## Licence
### MIT License

Voir LICENSE.md


## Structure idéale :

```text
eglisehub
│
├── backend
│   └── README.md
│
├── frontend
│   └── README.md
│
├── docs
│
└── README.md
```
