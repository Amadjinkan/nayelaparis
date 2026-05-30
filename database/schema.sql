-- ==============================================================
-- NayeLa Paris — Schéma de base de données (MariaDB / MySQL)
-- Ce fichier est fourni à titre de référence.
-- Pour utiliser : préférez « php artisan migrate --seed »
-- ==============================================================

CREATE DATABASE IF NOT EXISTS nayela_paris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nayela_paris;

-- ===== UTILISATEURS =====
CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prenom          VARCHAR(100) NOT NULL,
    nom             VARCHAR(100) NOT NULL,
    email           VARCHAR(191) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    mot_de_passe    VARCHAR(255) NOT NULL,
    telephone       VARCHAR(30) NULL,
    role            ENUM('client', 'admin') DEFAULT 'client',
    newsletter      BOOLEAN DEFAULT FALSE,
    remember_token  VARCHAR(100) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    INDEX (email), INDEX (role)
) ENGINE=InnoDB;

-- ===== ADRESSES =====
CREATE TABLE IF NOT EXISTS adresses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    label           VARCHAR(50) DEFAULT 'Domicile',
    ligne1          VARCHAR(255) NOT NULL,
    ligne2          VARCHAR(255) NULL,
    ville           VARCHAR(100) NOT NULL,
    province        VARCHAR(50) NOT NULL,
    code_postal     VARCHAR(20) NOT NULL,
    pays            VARCHAR(50) DEFAULT 'Canada',
    par_defaut      BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (user_id, par_defaut)
) ENGINE=InnoDB;

-- ===== PRODUITS =====
CREATE TABLE IF NOT EXISTS produits (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(200) NOT NULL,
    categorie       VARCHAR(100) NOT NULL,
    prix            DECIMAL(10,2) NOT NULL,
    stock           INT DEFAULT 0,
    tailles         VARCHAR(200) DEFAULT 'Unique',
    description     TEXT NULL,
    emoji           VARCHAR(10) DEFAULT '👗',
    image           VARCHAR(255) NULL,
    featured        BOOLEAN DEFAULT FALSE,
    actif           BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    INDEX (categorie), INDEX (featured), INDEX (actif)
) ENGINE=InnoDB;

-- ===== COMMANDES =====
CREATE TABLE IF NOT EXISTS commandes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    numero          VARCHAR(30) UNIQUE NOT NULL,
    sous_total      DECIMAL(10,2) NOT NULL,
    frais_livraison DECIMAL(10,2) DEFAULT 0,
    taxes           DECIMAL(10,2) DEFAULT 0,
    total           DECIMAL(10,2) NOT NULL,
    devise          VARCHAR(3) DEFAULT 'CAD',
    statut          ENUM('pending','paid','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    livr_destinataire VARCHAR(255) NULL,
    livr_ligne1     VARCHAR(255) NULL,
    livr_ligne2     VARCHAR(255) NULL,
    livr_ville      VARCHAR(100) NULL,
    livr_province   VARCHAR(50) NULL,
    livr_code_postal VARCHAR(20) NULL,
    livr_pays       VARCHAR(50) DEFAULT 'Canada',
    numero_suivi    VARCHAR(100) NULL,
    transporteur    VARCHAR(50) NULL,
    date_expedition TIMESTAMP NULL,
    date_livraison  TIMESTAMP NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX (user_id, statut), INDEX (statut), INDEX (numero)
) ENGINE=InnoDB;

-- ===== LIGNES DE COMMANDES =====
CREATE TABLE IF NOT EXISTS lignes_commandes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id     BIGINT UNSIGNED NOT NULL,
    produit_id      BIGINT UNSIGNED NOT NULL,
    nom_produit     VARCHAR(200) NOT NULL,
    prix_unitaire   DECIMAL(10,2) NOT NULL,
    emoji           VARCHAR(10) NULL,
    taille          VARCHAR(50) NULL,
    quantite        INT NOT NULL,
    sous_total      DECIMAL(10,2) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT,
    INDEX (commande_id), INDEX (produit_id)
) ENGINE=InnoDB;

-- ===== PAIEMENTS STRIPE =====
CREATE TABLE IF NOT EXISTS paiements (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id     BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    stripe_payment_intent_id VARCHAR(255) UNIQUE NULL,
    stripe_charge_id VARCHAR(255) NULL,
    stripe_customer_id VARCHAR(255) NULL,
    montant         DECIMAL(10,2) NOT NULL,
    devise          VARCHAR(3) DEFAULT 'CAD',
    marque_carte    VARCHAR(20) NULL,
    quatre_derniers VARCHAR(4) NULL,
    statut          ENUM('pending','processing','succeeded','failed','refunded','partial_refund') DEFAULT 'pending',
    message_erreur  TEXT NULL,
    metadata        JSON NULL,
    paye_le         TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX (stripe_payment_intent_id), INDEX (commande_id, statut)
) ENGINE=InnoDB;

-- ===== DEMANDES DE RETOUR (RMA) =====
CREATE TABLE IF NOT EXISTS retours (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_rma      VARCHAR(30) UNIQUE NOT NULL,
    commande_id     BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    motif           ENUM('taille_incorrecte','defaut_qualite','non_conforme','recu_endommage','autre') NOT NULL,
    description     TEXT NOT NULL,
    statut          ENUM('demande','approuve','refuse','attendu','recu','rembourse','clos') DEFAULT 'demande',
    montant_rembourse DECIMAL(10,2) DEFAULT 0,
    stripe_refund_id VARCHAR(255) NULL,
    note_client     TEXT NULL,
    note_admin      TEXT NULL,
    motif_refus     TEXT NULL,
    etiquette_retour VARCHAR(255) NULL,
    approuve_le     TIMESTAMP NULL,
    recu_le         TIMESTAMP NULL,
    rembourse_le    TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX (user_id, statut), INDEX (statut), INDEX (numero_rma)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lignes_retours (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    retour_id       BIGINT UNSIGNED NOT NULL,
    ligne_commande_id BIGINT UNSIGNED NOT NULL,
    quantite        INT NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (retour_id) REFERENCES retours(id) ON DELETE CASCADE,
    FOREIGN KEY (ligne_commande_id) REFERENCES lignes_commandes(id) ON DELETE RESTRICT,
    INDEX (retour_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS menu_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(80) NOT NULL UNIQUE,
    label_fr        VARCHAR(120) NOT NULL,
    label_en        VARCHAR(120) NULL,
    type            ENUM('page', 'url') DEFAULT 'page',
    page_key        VARCHAR(80) NULL,
    url             VARCHAR(500) NULL,
    position        INT UNSIGNED DEFAULT 1,
    is_active       TINYINT(1) DEFAULT 1,
    is_locked       TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    INDEX (is_active, position),
    INDEX (type)
) ENGINE=InnoDB;

-- ===== TABLES TECHNIQUES LARAVEL =====

CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type  VARCHAR(255) NOT NULL,
    tokenable_id    BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    token           VARCHAR(64) UNIQUE NOT NULL,
    abilities       TEXT NULL,
    last_used_at    TIMESTAMP NULL,
    expires_at      TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    INDEX (tokenable_type, tokenable_id), INDEX (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email           VARCHAR(255) PRIMARY KEY,
    token           VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sessions (
    id              VARCHAR(255) PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT NULL,
    payload         LONGTEXT NOT NULL,
    last_activity   INT NOT NULL,
    INDEX (user_id), INDEX (last_activity)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cache (
    `key`           VARCHAR(255) PRIMARY KEY,
    value           MEDIUMTEXT NOT NULL,
    expiration      INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cache_locks (
    `key`           VARCHAR(255) PRIMARY KEY,
    owner           VARCHAR(255) NOT NULL,
    expiration      INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jobs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue           VARCHAR(255) NOT NULL,
    payload         LONGTEXT NOT NULL,
    attempts        TINYINT UNSIGNED NOT NULL,
    reserved_at     INT UNSIGNED NULL,
    available_at    INT UNSIGNED NOT NULL,
    created_at      INT UNSIGNED NOT NULL,
    INDEX (queue)
) ENGINE=InnoDB;

-- ===== DONNÉES INITIALES =====
-- Compte administrateur (mot de passe : admin1234 — bcrypt)
INSERT INTO users (prenom, nom, email, mot_de_passe, role, created_at, updated_at) VALUES
('Admin', 'NayeLa', 'admin@nayelaparis.com', '$2y$12$JK0pBp9Lz3PYqGGgYqQqXOMQQ/aQ6PHQO9Wn0LjkOzZ8C2VGdRMzG', 'admin', NOW(), NOW());

-- Catalogue de démarrage
INSERT INTO produits (nom, categorie, prix, stock, tailles, description, emoji, featured, actif, created_at, updated_at) VALUES
('Robe Liberty Fleurie', 'Fille', 58.00, 12, '3A,4A,5A,6A,8A,10A', 'Robe en coton Liberty aux imprimés floraux délicats.', '👗', 1, 1, NOW(), NOW()),
('Robe Broderie Anglaise', 'Fille', 65.00, 8, '2A,3A,4A,5A,6A', 'Robe en broderie anglaise blanche, légère et romantique.', '🌸', 1, 1, NOW(), NOW()),
('Chemise Oxford Garçon', 'Garçon', 42.00, 15, '2A,3A,4A,5A,6A,8A,10A', 'Chemise Oxford classique en coton peigné.', '👔', 1, 1, NOW(), NOW()),
('Barboteuse Bébé Lin', 'Bébé', 35.00, 18, '1M,3M,6M,9M,12M', 'Barboteuse en lin doux pour les tout-petits.', '🐣', 1, 1, NOW(), NOW());

-- Menu de demarrage
INSERT INTO menu_items (slug, label_fr, label_en, type, page_key, url, position, is_active, is_locked, created_at, updated_at) VALUES
('accueil', 'Accueil', 'Home', 'page', 'accueil', NULL, 1, 1, 1, NOW(), NOW()),
('boutique', 'Boutique', 'Shop', 'page', 'boutique', NULL, 2, 1, 1, NOW(), NOW()),
('collections', 'Collections', 'Collections', 'page', 'collections', NULL, 3, 1, 0, NOW(), NOW()),
('contact', 'Contact', 'Contact', 'page', 'contact', NULL, 4, 1, 0, NOW(), NOW()),
('a-propos', 'A propos', 'About', 'page', 'accueil', NULL, 5, 1, 0, NOW(), NOW());
