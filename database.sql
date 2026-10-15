-- =====================================================
-- FINAL DATABASE — Live Streaming Platform (complete)
-- Import ONCE on a fresh database (phpMyAdmin or):
--   mysql -u root --default-character-set=utf8mb4 < database.sql
--
-- Covers every module in api.md:
--   users + wallets + VIP, hosts, agencies, resellers,
--   sub_admins, roles, gifts, banners, levels,
--   notifications, settings, vip_plans, recharges,
--   sessions, streams, stream_viewers, stream_gifts,
--   wallet ledger.
--
-- Default admin login: admin@livestream.com / Admin@123
-- =====================================================

-- NOTE for cPanel / phpMyAdmin:
-- Select your database first (e.g. saptahi1_smartlive), then import this file.
-- (CREATE DATABASE / USE are commented out to prevent permission errors on hosting)
-- CREATE DATABASE IF NOT EXISTS live_streaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE live_streaming;


-- -----------------------------------------------------
-- App users (viewers / streamers)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       VARCHAR(32)  NULL UNIQUE,     -- formatted auto-increment ID e.g. smart-lv-000001
    name          VARCHAR(60)  NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(20)      NULL,
    country_code  VARCHAR(10)      NULL,
    country       VARCHAR(60)      NULL,
    avatar        VARCHAR(255)     NULL,
    bio           VARCHAR(255)     NULL,
    vip           TINYINT(1)   NOT NULL DEFAULT 0,
    vip_expires_at DATETIME        NULL,
    level         INT UNSIGNED NOT NULL DEFAULT 1,
    xp            INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_user_id (user_id),
    INDEX idx_users_status (status),
    INDEX idx_users_vip (vip)
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Admin panel accounts
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(60)  NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Sequences (monotonic counters, e.g. custom user IDs)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS app_sequences (
    seq_name   VARCHAR(50) PRIMARY KEY,
    last_value INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Sessions for users, admins and sub-admins (token auth)
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS sessions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type  ENUM('user','admin','sub_admin') NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    token      VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sessions_token (token),
    INDEX idx_sessions_who (user_type, user_id)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Wallets (1 per user) + coin ledger
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS wallets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL UNIQUE,
    coins      INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    type          ENUM('recharge','admin_recharge','gift_sent','gift_received','admin_credit','admin_debit') NOT NULL,
    coins         INT UNSIGNED NOT NULL,
    balance_after INT NOT NULL,
    stream_id     INT UNSIGNED NULL,
    description   VARCHAR(255) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tx_user (user_id, id),
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Agencies (hosts belong to an agency)
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
-- Hosts (streamers managed by the agency/admin)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS hosts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED     NULL,               -- optional link to a users account
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

-- Monthly salary / payout records per host
CREATE TABLE IF NOT EXISTS host_salaries (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id    INT UNSIGNED NOT NULL,
    amount     DECIMAL(12,2) NOT NULL,
    month      CHAR(7)      NOT NULL,              -- e.g. 2026-09
    note       VARCHAR(255) NULL,
    paid_at    TIMESTAMP    NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_salary_host (host_id, month),
    CONSTRAINT fk_salary_host FOREIGN KEY (host_id) REFERENCES hosts (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Resellers (sell coin packages to users)
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
-- Sub admins + roles / permissions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL,
    description VARCHAR(255) NULL,
    permissions JSON NULL,                          -- {"users":{"view":true,"edit":false}}
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
    permissions   JSON NULL,                        -- ["users.view","hosts.view"]
    status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subadmins_status (status),
    CONSTRAINT fk_subadmins_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE SET NULL
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Gift catalog (admin-managed; App reads it too)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS gifts (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(60) NOT NULL,
    category  VARCHAR(40)     NULL,
    icon      VARCHAR(16) NOT NULL DEFAULT 'gift',
    emoji     VARCHAR(16)     NULL,
    coins     INT UNSIGNED NOT NULL,
    price     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_url VARCHAR(255)    NULL,
    active    TINYINT(1)  NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gifts_name (name),
    INDEX idx_gifts_active (active)
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Banners (app home screen)
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
-- User levels (XP progression + rewards)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS levels (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level      INT UNSIGNED NOT NULL UNIQUE,
    title      VARCHAR(60)  NOT NULL,
    xp_required INT UNSIGNED NOT NULL DEFAULT 0,
    reward     INT UNSIGNED NOT NULL DEFAULT 0,    -- coins granted on reaching the level
    color      CHAR(7)      NOT NULL DEFAULT '#3b82f6',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Notifications (draft -> send to an audience)
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
-- Global app settings (key/value)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(60) PRIMARY KEY,
    setting_value VARCHAR(500) NOT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- VIP plans + recharge history
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

-- -----------------------------------------------------
-- Live streams
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS streams (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id      INT UNSIGNED NOT NULL,
    channel      VARCHAR(64) NOT NULL UNIQUE,   -- pass to the realtime socket later
    title        VARCHAR(120) NOT NULL,
    category     VARCHAR(40) NOT NULL DEFAULT 'Popular', -- Freshers, Popular, Spotlight, PK Matches
    tag          VARCHAR(40) NULL DEFAULT 'Rising Star', -- Super Star, Big Star, Rising Star
    description  VARCHAR(255) NULL,
    cover        VARCHAR(255) NULL,
    status       ENUM('live','ended') NOT NULL DEFAULT 'live',
    viewer_count INT UNSIGNED NOT NULL DEFAULT 0,
    peak_viewers INT UNSIGNED NOT NULL DEFAULT 0,
    total_views  INT UNSIGNED NOT NULL DEFAULT 0,
    coins_earned INT UNSIGNED NOT NULL DEFAULT 0,
    started_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at     DATETIME NULL,
    INDEX idx_streams_status (status, id),
    INDEX idx_streams_cat (status, category),
    CONSTRAINT fk_streams_host FOREIGN KEY (host_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS stream_viewers (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stream_id INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at   DATETIME NULL,
    INDEX idx_sv_stream (stream_id, user_id, left_at),
    CONSTRAINT fk_sv_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
    CONSTRAINT fk_sv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Gifts sent during streams (coin transfer sender -> host)
CREATE TABLE IF NOT EXISTS stream_gifts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stream_id   INT UNSIGNED NOT NULL,
    gift_id     INT UNSIGNED NOT NULL,
    sender_id   INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    quantity    INT UNSIGNED NOT NULL DEFAULT 1,
    coins       INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sg_stream (stream_id, id),
    CONSTRAINT fk_sg_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE,
    CONSTRAINT fk_sg_gift FOREIGN KEY (gift_id) REFERENCES gifts (id) ON DELETE CASCADE,
    CONSTRAINT fk_sg_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_sg_receiver FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Party Audio / Voice Rooms
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS audio_rooms (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id       INT UNSIGNED NOT NULL,
    channel       VARCHAR(64) NOT NULL UNIQUE,
    title         VARCHAR(120) NOT NULL,
    category      VARCHAR(40) NOT NULL DEFAULT 'Party',
    cover         VARCHAR(255) NULL,
    status        ENUM('live','ended') NOT NULL DEFAULT 'live',
    listeners     INT UNSIGNED NOT NULL DEFAULT 0,
    peak_listeners INT UNSIGNED NOT NULL DEFAULT 0,
    coins_earned  INT UNSIGNED NOT NULL DEFAULT 0,
    started_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at      DATETIME NULL,
    INDEX idx_ar_status (status, id),
    CONSTRAINT fk_ar_host FOREIGN KEY (host_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS room_seats (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id    INT UNSIGNED NOT NULL,
    seat_index TINYINT UNSIGNED NOT NULL DEFAULT 1,
    user_id    INT UNSIGNED NULL,
    is_muted   TINYINT(1) NOT NULL DEFAULT 0,
    joined_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_room_seat (room_id, seat_index),
    CONSTRAINT fk_rs_room FOREIGN KEY (room_id) REFERENCES audio_rooms (id) ON DELETE CASCADE,
    CONSTRAINT fk_rs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Store Items & User Backpack (My Bag)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS store_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    type        ENUM('frame','entry','ride','badge','bubble') NOT NULL DEFAULT 'frame',
    coins       INT UNSIGNED NOT NULL DEFAULT 0,
    days        INT UNSIGNED NOT NULL DEFAULT 30,
    image_url   VARCHAR(255) NOT NULL,
    preview_url VARCHAR(255) NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_store_type (type, active)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS user_bag (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    item_id    INT UNSIGNED NOT NULL,
    is_equipped TINYINT(1) NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ub_user (user_id, item_id),
    CONSTRAINT fk_ub_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ub_item FOREIGN KEY (item_id) REFERENCES store_items (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- User Follows (Social)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS user_follows (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_id  INT UNSIGNED NOT NULL,
    following_id INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_follow (follower_id, following_id),
    CONSTRAINT fk_uf_follower FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_uf_following FOREIGN KEY (following_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- -----------------------------------------------------
-- Shorts (Short Videos) & Likes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS shorts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(255) NOT NULL,
    video_url  VARCHAR(255) NOT NULL,
    cover_url  VARCHAR(255) NULL,
    views      INT UNSIGNED NOT NULL DEFAULT 0,
    likes      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_shorts_user (user_id),
    CONSTRAINT fk_shorts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS short_likes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    short_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_short_like (short_id, user_id),
    CONSTRAINT fk_sl_short FOREIGN KEY (short_id) REFERENCES shorts (id) ON DELETE CASCADE,
    CONSTRAINT fk_sl_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;


-- =====================================================
-- SEED DATA
-- =====================================================

-- Default admin (password: Admin@123)
INSERT INTO admins (name, email, password_hash)
SELECT 'Super Admin', 'admin@livestream.com',
       '$2y$10$0/on5JiaKHDppXtzTpANauymrCV73MGTmbUjAoFs7SU.oWp7zXVPS'
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE email = 'admin@livestream.com');

-- Gift catalog
INSERT INTO gifts (name, category, icon, emoji, coins, price, active) VALUES
    ('Rose',   'Flowers', 'rose',   '🌹', 1,    0.10, 1),
    ('Heart',  'Love',    'heart',  '❤️', 5,    0.50, 1),
    ('Teddy',  'Toys',    'teddy',  '🧸', 20,   2.00, 1),
    ('Car',    'Luxury',  'car',    '🚗', 50,   5.00, 1),
    ('Rocket', 'Special', 'rocket', '🚀', 100,  10.00, 1),
    ('Crown',  'Royalty', 'crown',  '👑', 500,  50.00, 1),
    ('Lion',   'Animals', 'lion',   '🦁', 1000, 99.00, 1)
ON DUPLICATE KEY UPDATE coins = VALUES (coins);

-- User levels (XP ladder)
INSERT INTO levels (level, title, xp_required, reward, color) VALUES
    (1,  'Newbie',    0,       0,     '#94a3b8'),
    (10, 'Bronze',    5000,    100,   '#cd7f32'),
    (20, 'Silver',    20000,   500,   '#c0c0c0'),
    (30, 'Gold',      50000,   1000,  '#f59e0b'),
    (50, 'Platinum',  150000,  5000,  '#38bdf8'),
    (70, 'Diamond',   300000,  10000, '#818cf8'),
    (90, 'Supreme',   500000,  25000, '#f43f5e')
ON DUPLICATE KEY UPDATE title = VALUES (title);

-- Global app settings
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

-- VIP plans
INSERT INTO vip_plans (name, price, currency, duration_days, bonus_coins, active) VALUES
    ('VIP Bronze',  4.99,  'USD', 30,  500,  1),
    ('VIP Silver',  9.99,  'USD', 30,  1500, 1),
    ('VIP Gold',    19.99, 'USD', 90,  6000, 1)
ON DUPLICATE KEY UPDATE price = VALUES (price);

-- Sample agency + host + reseller (only on a fresh DB)
INSERT INTO agencies (name, owner_name, email, rating, revenue)
SELECT 'Star Agency', 'Alex Kumar', 'agency@example.com', 4.8, 125000.00
WHERE NOT EXISTS (SELECT 1 FROM agencies WHERE name = 'Star Agency');

INSERT INTO hosts (name, agency_id, email, followers, level, earnings, status)
SELECT 'Elena Rodriguez', (SELECT id FROM agencies WHERE name = 'Star Agency'), 'elena@example.com', 45000, 85, 8900.00, 'offline'
WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE name = 'Elena Rodriguez');

INSERT INTO resellers (name, owner_name, email, users_served, packages, revenue)
SELECT 'Premium Reseller', 'John Smith', 'reseller@example.com', 234, 12, 45000.00
WHERE NOT EXISTS (SELECT 1 FROM resellers WHERE name = 'Premium Reseller');

-- Sample role (only on a fresh DB)
INSERT INTO roles (name, description, permissions)
SELECT 'User Manager', 'Can view and manage app users',
       '{"users":{"view":true,"edit":true,"delete":false},"hosts":{"view":true,"edit":false},"settings":{"view":true}}'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'User Manager');
