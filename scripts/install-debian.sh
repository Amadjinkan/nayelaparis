#!/usr/bin/env bash
# ==============================================================
# NayeLa Paris - Installation automatique sur Debian 12 Bookworm
# Installe : Apache 2.4 + PHP 8.3 + MariaDB + Composer + Git
# Usage : sudo ./install-debian.sh
# ==============================================================

set -e  # Arrêter au premier échec

# Couleurs pour l'affichage
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

log()   { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# Vérifier qu'on est root
if [ "$EUID" -ne 0 ]; then
  error "Ce script doit être lancé en tant que root (sudo ./install-debian.sh)"
fi

# Vérifier qu'on est sur Debian
if [ ! -f /etc/debian_version ]; then
  error "Ce script est conçu pour Debian. Détection : $(uname -a)"
fi

echo ""
echo "=========================================="
echo "  NayeLa Paris — Installation Debian 12"
echo "=========================================="
echo ""

# ====== 1. MISE À JOUR SYSTÈME ======
log "Mise à jour du système..."
apt update -y
apt upgrade -y

# ====== 2. OUTILS DE BASE ======
log "Installation des outils de base..."
apt install -y \
    curl \
    wget \
    git \
    unzip \
    ca-certificates \
    apt-transport-https \
    lsb-release \
    gnupg2 \
    software-properties-common

# ====== 3. AJOUT DU DÉPÔT SURY POUR PHP 8.3 ======
# Debian 12 inclut PHP 8.2 par défaut, on utilise le dépôt sury.org pour PHP 8.3
log "Ajout du dépôt PHP de Sury (pour PHP 8.3)..."
curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
dpkg -i /tmp/debsuryorg-archive-keyring.deb
echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
    > /etc/apt/sources.list.d/php.list
apt update -y

# ====== 4. APACHE 2.4 ======
log "Installation d'Apache 2.4..."
apt install -y apache2
systemctl enable apache2
systemctl start apache2

# ====== 5. PHP 8.3 + EXTENSIONS ======
log "Installation de PHP 8.3 et ses extensions..."
apt install -y \
    php8.3 \
    php8.3-cli \
    php8.3-common \
    php8.3-curl \
    php8.3-mbstring \
    php8.3-mysql \
    php8.3-xml \
    php8.3-zip \
    php8.3-gd \
    php8.3-bcmath \
    php8.3-intl \
    php8.3-tokenizer \
    php8.3-fileinfo \
    php8.3-opcache \
    libapache2-mod-php8.3

# Activer PHP 8.3 dans Apache
a2dismod php8.2 2>/dev/null || true
a2enmod php8.3

# ====== 6. MARIADB ======
log "Installation de MariaDB..."
apt install -y mariadb-server mariadb-client
systemctl enable mariadb
systemctl start mariadb

# Sécuriser MariaDB (configuration recommandée non-interactive)
warn "Configuration recommandée : exécutez 'sudo mysql_secure_installation' manuellement après ce script."

# ====== 7. COMPOSER ======
log "Installation de Composer..."
if ! command -v composer &> /dev/null; then
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        error "Échec de la vérification de l'installateur Composer"
    fi

    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

# ====== 8. MODULES APACHE ======
log "Activation des modules Apache nécessaires..."
a2enmod rewrite headers ssl

# ====== 9. PERMISSIONS ======
log "Configuration des permissions /var/www..."
mkdir -p /var/www
chown -R www-data:www-data /var/www

# ====== 10. PARE-FEU (optionnel mais recommandé) ======
log "Configuration du pare-feu UFW..."
if ! command -v ufw &> /dev/null; then
    apt install -y ufw
fi
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
# Décommentez la ligne suivante pour activer le pare-feu (attention en SSH !)
# ufw --force enable

# ====== 11. REDÉMARRAGE APACHE ======
systemctl restart apache2

# ====== RÉSUMÉ ======
echo ""
echo "=========================================="
echo "  ✅  Installation terminée avec succès !"
echo "=========================================="
echo ""
log "Versions installées :"
echo "    Apache : $(apache2 -v | head -1)"
echo "    PHP    : $(php -v | head -1)"
echo "    MariaDB: $(mariadb --version)"
echo "    Composer: $(composer --version)"
echo ""
log "Prochaines étapes :"
echo "  1. Sécurisez MariaDB : sudo mysql_secure_installation"
echo "  2. Créez la base de données :"
echo "       sudo mysql -u root"
echo "       > CREATE DATABASE nayela_paris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "       > CREATE USER 'nayela'@'localhost' IDENTIFIED BY 'votre_mdp';"
echo "       > GRANT ALL ON nayela_paris.* TO 'nayela'@'localhost';"
echo "       > FLUSH PRIVILEGES;"
echo "  3. Déposez le projet dans /var/www/nayela-paris"
echo "  4. cd /var/www/nayela-paris && sudo -u www-data composer install"
echo "  5. Configurez .env et lancez : sudo -u www-data php artisan migrate --seed"
echo "  6. Activez le VirtualHost Apache (voir README.md, étape 5)"
echo ""
echo "📖 Documentation complète : voir README.md"
echo ""
