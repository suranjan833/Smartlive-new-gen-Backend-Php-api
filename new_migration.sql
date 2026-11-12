-- ========================================================================
-- SMARTLIVE — NEW DATABASE MIGRATION SCRIPT (ONLY NEW CHANGES)
-- Database: saptahi1_smartlive
-- Run this in phpMyAdmin -> SQL tab to apply all newly added fields & tables.
-- ========================================================================

-- 1. Ensure utf8mb4 encoding for emojis and symbols (✨, 🎵, 🎮, 💕, etc.)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 2. Convert existing tables to utf8mb4 so emojis never throw #1366 error
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `streams` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. Add user_id (smart-lv-XXXXXX), google_id, country_code, dob, and gender to `users`
-- (Safe: check your phpMyAdmin, if any column already exists, just remove that single line)
ALTER TABLE `users`
  ADD COLUMN `user_id` VARCHAR(32) NULL UNIQUE AFTER `id`,
  ADD COLUMN `google_id` VARCHAR(100) NULL UNIQUE AFTER `email`,
  ADD COLUMN `country_code` VARCHAR(10) NULL AFTER `phone`,
  ADD COLUMN `dob` DATE NULL AFTER `country`,
  ADD COLUMN `gender` ENUM('male','female','other') NULL AFTER `dob`,
  ADD INDEX `idx_users_user_id` (`user_id`);

-- 4. Create Persistent Sequence Generator Table (guarantees deleted user IDs are never reused)
CREATE TABLE IF NOT EXISTS `app_sequences` (
    `seq_name`   VARCHAR(50) PRIMARY KEY,
    `last_value` INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE = InnoDB;

-- Initialize user sequence if not present
INSERT INTO `app_sequences` (`seq_name`, `last_value`) 
VALUES ('user_id', (SELECT IFNULL(MAX(id), 0) FROM `users`))
ON DUPLICATE KEY UPDATE `last_value` = `last_value`;

-- 5. Add category & badge tag to `streams` table
ALTER TABLE `streams`
  ADD COLUMN `category` VARCHAR(40) NOT NULL DEFAULT 'Popular' AFTER `title`,
  ADD COLUMN `tag` VARCHAR(40) NULL DEFAULT 'Rising Star' AFTER `category`,
  ADD INDEX `idx_streams_cat` (`status`, `category`);

-- 6. Create Party Audio Rooms Tables (if not already created)
CREATE TABLE IF NOT EXISTS `audio_rooms` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `host_id`        INT UNSIGNED NOT NULL,
    `channel`        VARCHAR(64) NOT NULL UNIQUE,
    `title`          VARCHAR(120) NOT NULL,
    `category`       VARCHAR(40) NOT NULL DEFAULT 'Party',
    `cover`          VARCHAR(255) NULL,
    `status`         ENUM('live','ended') NOT NULL DEFAULT 'live',
    `listeners`      INT UNSIGNED NOT NULL DEFAULT 0,
    `peak_listeners` INT UNSIGNED NOT NULL DEFAULT 0,
    `coins_earned`   INT UNSIGNED NOT NULL DEFAULT 0,
    `started_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at`       DATETIME NULL,
    INDEX `idx_ar_status` (`status`, `id`),
    CONSTRAINT `fk_ar_host` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

-- 7. Create 12 Microphone Seats Table for Audio Party Rooms
CREATE TABLE IF NOT EXISTS `room_seats` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id`    INT UNSIGNED NOT NULL,
    `seat_index` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `user_id`    INT UNSIGNED NULL,
    `is_muted`   TINYINT(1) NOT NULL DEFAULT 0,
    `joined_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_room_seat` (`room_id`, `seat_index`),
    INDEX `idx_rs_user` (`user_id`),
    CONSTRAINT `fk_rs_room` FOREIGN KEY (`room_id`) REFERENCES `audio_rooms` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

-- 8. Create Store Items Table (Frames, Entrance Rides, Badges)
CREATE TABLE IF NOT EXISTS `store_items` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(60) NOT NULL,
    `type`       ENUM('frame','ride','entry','badge','bubble') NOT NULL,
    `coins`      INT UNSIGNED NOT NULL DEFAULT 0,
    `days`       INT UNSIGNED NOT NULL DEFAULT 30,
    `image_url`  VARCHAR(255) NOT NULL,
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- 9. Create User Backpack / Inventory Table
CREATE TABLE IF NOT EXISTS `user_bag` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `item_id`     INT UNSIGNED NOT NULL,
    `is_equipped` TINYINT(1) NOT NULL DEFAULT 0,
    `expires_at`  DATETIME NOT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ub_user` (`user_id`, `is_equipped`),
    CONSTRAINT `fk_ub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ub_item` FOREIGN KEY (`item_id`) REFERENCES `store_items` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

-- 10. Create Social Follows Table
CREATE TABLE IF NOT EXISTS `user_follows` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `follower_id`  INT UNSIGNED NOT NULL,
    `following_id` INT UNSIGNED NOT NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_follow` (`follower_id`, `following_id`),
    CONSTRAINT `fk_uf_follower`  FOREIGN KEY (`follower_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_uf_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

-- 11. Create Shorts / Video Reels Table
CREATE TABLE IF NOT EXISTS `shorts` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `title`      VARCHAR(150) NOT NULL,
    `video_url`  VARCHAR(255) NOT NULL,
    `cover_url`  VARCHAR(255) NULL,
    `views`      INT UNSIGNED NOT NULL DEFAULT 0,
    `likes`      INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_shorts_user` (`user_id`),
    CONSTRAINT `fk_shorts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

-- 12. Create Short Likes Table
CREATE TABLE IF NOT EXISTS `short_likes` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `short_id`   INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_short_user` (`short_id`, `user_id`),
    CONSTRAINT `fk_sl_short` FOREIGN KEY (`short_id`) REFERENCES `shorts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sl_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;
