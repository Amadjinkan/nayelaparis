#!/usr/bin/env bash
# ==============================================================
# NayeLa Paris - Déploiement Production
# ==============================================================
# Usage : sudo ./scripts/deploy-prod.sh
# À exécuter une fois le projet copié dans /var/www/nayela-paris

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'
log()   { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }

cd /var/www/nayela-paris

log "1. Récupération des dernières dépendances..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

log "2. Mise à jour de la base de données..."
sudo -u www-data php artisan migrate --force

log "3. Optimisation du cache Laravel..."
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

log "4. Permissions correctes..."
chown -R www-data:www-data /var/www/nayela-paris
chmod -R 755 /var/www/nayela-paris
chmod -R 775 /var/www/nayela-paris/storage
chmod -R 775 /var/www/nayela-paris/bootstrap/cache

log "5. Redémarrage Apache..."
systemctl reload apache2

echo ""
log "✅ Déploiement terminé !"
echo ""
warn "Pour vider tous les caches : sudo -u www-data php artisan optimize:clear"
