-- =====================================================
-- Migration 2: Wallet + Live Streaming tables
-- Safe to import multiple times (idempotent).
--   mysql -u root --default-character-set=utf8mb4 < database_migrate_2.sql
-- =====================================================

USE live_streaming;

-- 1 coin per user account
CREATE TABLE IF NOT EXISTS wallets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL UNIQUE,
    coins      INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Coin ledger: every balance change is recorded here
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    type          ENUM('recharge','gift_sent','gift_received','admin_credit','admin_debit') NOT NULL,
    coins         INT UNSIGNED NOT NULL,
    balance_after INT NOT NULL,
    stream_id     INT UNSIGNED NULL,
    description   VARCHAR(255) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tx_user (user_id, id),
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Live streams
CREATE TABLE IF NOT EXISTS streams (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_id      INT UNSIGNED NOT NULL,
    channel      VARCHAR(64) NOT NULL UNIQUE,   -- pass to the realtime socket later
    title        VARCHAR(120) NOT NULL,
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
    CONSTRAINT fk_streams_host FOREIGN KEY (host_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Who is watching / has watched a stream (local tracking; realtime calc later)
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

-- Gift catalog
CREATE TABLE IF NOT EXISTS gifts (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(60) NOT NULL,
    icon  VARCHAR(16) NOT NULL DEFAULT 'gift',
    coins INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_gifts_name (name)
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

-- Seed gift catalog
INSERT INTO gifts (name, icon, coins) VALUES
    ('Rose',   'rose',   1),
    ('Heart',  'heart',  5),
    ('Teddy',  'teddy',  20),
    ('Car',    'car',    50),
    ('Rocket', 'rocket', 100),
    ('Crown',  'crown',  500),
    ('Lion',   'lion',   1000)
ON DUPLICATE KEY UPDATE coins = VALUES (coins);
