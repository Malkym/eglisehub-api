#!/bin/bash

echo "=============================="
echo " Déploiement EgliseHub API"
echo "=============================="

# 1. Récupérer le code
echo "→ Mise à jour du code..."
git pull origin main

# 2. Installer les dépendances
echo "→ Installation des dépendances..."
composer install --no-dev --optimize-autoloader

# 3. Copier le .env de production
echo "→ Configuration..."
cp .env.production .env
php artisan key:generate --force

# 4. Migrations
echo "→ Migrations..."
php artisan migrate --force

# 5. Optimisations
echo "→ Optimisation du cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Générer la doc Swagger
echo "→ Génération Swagger..."
php artisan l5-swagger:generate

# 7. Permissions
echo "→ Permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 8. Lien symbolique storage
php artisan storage:link

echo "=============================="
echo " Déploiement terminé !"
echo "=============================="