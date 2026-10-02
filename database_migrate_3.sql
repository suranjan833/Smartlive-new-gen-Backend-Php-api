-- =====================================================
-- Migration 3: Admin panel modules (api.md)
-- Safe to import multiple times (idempotent).
--   mysql -u root --default-character-set=utf8mb4 < database_migrate_3.sql
-- Run AFTER database.sql / database_migrate_2.sql on an existing DB.
-- =====================================================

USE live_streaming;

-- users: vip / level / country columns
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS country VARCHAR(60) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS vip TINYINT(1) NOT NULL DEFAULT 0 AFTER bio,
    ADD COLUMN IF NOT EXISTS vip_expires_at DATETIME NULL AFTER vip,
    ADD COLUMN IF NOT EXISTS level INT UNSIGNED NOT NULL DEFAULT 1 AFTER vip_expires_at,
    ADD COLUMN IF NOT EXISTS xp INT UNSIGNED NOT NULL DEFAULT 0 AFTER level;

-- sessions: allow sub-admin tokens
ALTER TABLE sessions
    MODIFY user_type ENUM('user','admin','sub_admin') NOT NULL;

-- wallet ledger: admin recharge type
ALTER TABLE wallet_transactions
    MODIFY type ENUM('recharge','admin_recharge','gift_sent','gift_received','admin_credit','admin_debit') NOT NULL;

-- gifts: full catalog fields
ALTER TABLE gifts
    ADD COLUMN IF NOT EXISTS category VARCHAR(40) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS emoji VARCHAR(16) NULL AFTER icon,
    ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER coins,
    ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL AFTER price,
    ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER image_url,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER active,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- -----------------------------------------------------
-- Agencies
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS agencies (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    owner_name VARCHAR(60)  NOT NULL,
    email      VARCHAR(120)     NULL,
    phone      VARCHAR(20)      NULL,
    rating     DECIMAL(3,1) NOT NULL DEFAULT 0.0,
    revenue    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status     ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agencies_status (status)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Hosts + salaries
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS hosts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED     NULL,
    name       VARCHAR(60)  NOT NULL,
    agency_id  INT UNSIGNED     NULL,
    email      VARCHAR(120)     NULL,
    phone      VARCHAR(20)      NULL,
    followers  INT UNSIGNED NOT NULL DEFAULT 0,
    level      INT UNSIGNED NOT NULL DEFAULT 1,
    earnings   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status     ENUM('online','offline','banned') NOT NULL DEFAULT 'offline',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hosts_status (status),
    INDEX idx_hosts_agency (agency_id),
    CONSTRAINT fk_hosts_agency FOREIGN KEY (agency_id) REFERENCES agencies (id) ON DELETE SET NULL,
    CONSTRAINT fk_hosts_user   FOREIGN KEY (user_id)   REFERENCES users (id)    ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS host_salaries (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id    INT UNSIGNED NOT NULL,
    amount     DECIMAL(12,2) NOT NULL,
    month      CHAR(7)      NOT NULL,
    note       VARCHAR(255) NULL,
    paid_at    TIMESTAMP    NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_salary_host (host_id, month),
    CONSTRAINT fk_salary_host FOREIGN KEY (host_id) REFERENCES hosts (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Resellers
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS resellers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    owner_name  VARCHAR(60)  NOT NULL,
    email       VARCHAR(120)     NULL,
    phone       VARCHAR(20)      NULL,
    users_served INT UNSIGNED NOT NULL DEFAULT 0,
    packages    INT UNSIGNED NOT NULL DEFAULT 0,
    revenue     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status      ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resellers_status (status)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Roles + sub admins
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL,
    description VARCHAR(255) NULL,
    permissions JSON NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS sub_admins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(60)  NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(60)      NULL,
    role_id       INT UNSIGNED     NULL,
    permissions   JSON NULL,
    status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subadmins_status (status),
    CONSTRAINT fk_subadmins_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE SET NULL
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Banners
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS banners (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    category   VARCHAR(40)  NULL,
    price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    coins      INT UNSIGNED NOT NULL DEFAULT 0,
    image_url  VARCHAR(255) NOT NULL,
    active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_banners_active (active)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Levels
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS levels (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level      INT UNSIGNED NOT NULL UNIQUE,
    title      VARCHAR(60)  NOT NULL,
    xp_required INT UNSIGNED NOT NULL DEFAULT 0,
    reward     INT UNSIGNED NOT NULL DEFAULT 0,
    color      CHAR(7)      NOT NULL DEFAULT '#3b82f6',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Notifications
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(120) NOT NULL,
    message    VARCHAR(500) NOT NULL,
    type       ENUM('info','promo','alert') NOT NULL DEFAULT 'info',
    audience   ENUM('all_users','vip_users','hosts','active_users') NOT NULL DEFAULT 'all_users',
    status     ENUM('draft','sent') NOT NULL DEFAULT 'draft',
    sent_at    DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notifications_status (status)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Settings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(60) PRIMARY KEY,
    setting_value VARCHAR(500) NOT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- VIP plans + recharges
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS vip_plans (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60)  NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    currency    CHAR(3)      NOT NULL DEFAULT 'USD',
    duration_days INT UNSIGNED NOT NULL DEFAULT 30,
    bonus_coins INT UNSIGNED NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS recharges (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    admin_id   INT UNSIGNED     NULL,
    amount     DECIMAL(10,2) NOT NULL,
    currency   CHAR(3)      NOT NULL DEFAULT 'USD',
    type       ENUM('coins','balance') NOT NULL DEFAULT 'coins',
    coins      INT UNSIGNED NOT NULL DEFAULT 0,
    note       VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recharges_user (user_id, id),
    CONSTRAINT fk_recharges_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE CASCADE,
    CONSTRAINT fk_recharges_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE = InnoDB;

-- =====================================================
-- SEEDS (idempotent)
-- =====================================================

INSERT INTO gifts (name, category, icon, emoji, coins, price, active) VALUES
    ('Rose',   'Flowers', 'rose',   '🌹', 1,    0.10, 1),
    ('Heart',  'Love',    'heart',  '❤️', 5,    0.50, 1),
    ('Teddy',  'Toys',    'teddy',  '🧸', 20,   2.00, 1),
    ('Car',    'Luxury',  'car',    '🚗', 50,   5.00, 1),
    ('Rocket', 'Special', 'rocket', '🚀', 100,  10.00, 1),
    ('Crown',  'Royalty', 'crown',  '👑', 500,  50.00, 1),
    ('Lion',   'Animals', 'lion',   '🦁', 1000, 99.00, 1)
ON DUPLICATE KEY UPDATE coins = VALUES (coins);

INSERT INTO levels (level, title, xp_required, reward, color) VALUES
    (1,  'Newbie',    0,       0,     '#94a3b8'),
    (10, 'Bronze',    5000,    100,   '#cd7f32'),
    (20, 'Silver',    20000,   500,   '#c0c0c0'),
    (30, 'Gold',      50000,   1000,  '#f59e0b'),
    (50, 'Platinum',  150000,  5000,  '#38bdf8'),
    (70, 'Diamond',   300000,  10000, '#818cf8'),
    (90, 'Supreme',   500000,  25000, '#f43f5e')
ON DUPLICATE KEY UPDATE title = VALUES (title);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('audioEnabled',        '1'),
    ('videoEnabled',        '1'),
    ('maintenanceMode',     '0'),
    ('registrationEnabled', '1'),
    ('giftsEnabled',        '1'),
    ('vipEnabled',          '1'),
    ('agencyEnabled',       '1'),
    ('resellerEnabled',     '1'),
    ('pushNotifications',   '1'),
    ('emailNotifications',  '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES (setting_value);

INSERT INTO vip_plans (name, price, currency, duration_days, bonus_coins, active) VALUES
    ('VIP Bronze',  4.99,  'USD', 30,  500,  1),
    ('VIP Silver',  9.99,  'USD', 30,  1500, 1),
    ('VIP Gold',    19.99, 'USD', 90,  6000, 1)
ON DUPLICATE KEY UPDATE price = VALUES (price);

INSERT INTO agencies (name, owner_name, email, rating, revenue)
SELECT 'Star Agency', 'Alex Kumar', 'agency@example.com', 4.8, 125000.00
WHERE NOT EXISTS (SELECT 1 FROM agencies WHERE name = 'Star Agency');

INSERT INTO hosts (name, agency_id, email, followers, level, earnings, status)
SELECT 'Elena Rodriguez', (SELECT id FROM agencies WHERE name = 'Star Agency'), 'elena@example.com', 45000, 85, 8900.00, 'offline'
WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE name = 'Elena Rodriguez');

INSERT INTO resellers (name, owner_name, email, users_served, packages, revenue)
SELECT 'Premium Reseller', 'John Smith', 'reseller@example.com', 234, 12, 45000.00
WHERE NOT EXISTS (SELECT 1 FROM resellers WHERE name = 'Premium Reseller');

INSERT INTO roles (name, description, permissions)
SELECT 'User Manager', 'Can view and manage app users',
       '{"users":{"view":true,"edit":true,"delete":false},"hosts":{"view":true,"edit":false},"settings":{"view":true}}'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'User Manager');
