SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for ci_users
-- ----------------------------
DROP TABLE IF EXISTS `ci_users`;
CREATE TABLE `ci_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for ci_events
-- ----------------------------
DROP TABLE IF EXISTS `ci_events`;
CREATE TABLE `ci_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `checkin_enabled` tinyint(1) DEFAULT 1,
  `expected_guests` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_event_slug` (`slug`),
  UNIQUE KEY `idx_event_code` (`event_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for ci_event_tables
-- ----------------------------
DROP TABLE IF EXISTS `ci_event_tables`;
CREATE TABLE `ci_event_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 10,
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_table_event` (`event_id`),
  CONSTRAINT `fk_table_event` FOREIGN KEY (`event_id`) REFERENCES `ci_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for ci_guests
-- ----------------------------
DROP TABLE IF EXISTS `ci_guests`;
CREATE TABLE `ci_guests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `normalized_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_group` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organization` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_people` int(11) DEFAULT 1,
  `table_id` int(11) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_guest_event` (`event_id`),
  KEY `fk_guest_table` (`table_id`),
  KEY `idx_normalized_phone` (`event_id`, `normalized_phone`),
  CONSTRAINT `fk_guest_event` FOREIGN KEY (`event_id`) REFERENCES `ci_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_guest_table` FOREIGN KEY (`table_id`) REFERENCES `ci_event_tables` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for ci_checkins
-- ----------------------------
DROP TABLE IF EXISTS `ci_checkins`;
CREATE TABLE `ci_checkins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `full_name_entered` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_entered` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address_entered` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `people_count` int(11) DEFAULT 1,
  `match_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'matched',
  `checkin_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_checkin_event` (`event_id`),
  KEY `fk_checkin_guest` (`guest_id`),
  KEY `fk_checkin_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_checkin_event` FOREIGN KEY (`event_id`) REFERENCES `ci_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checkin_guest` FOREIGN KEY (`guest_id`) REFERENCES `ci_guests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_checkin_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `ci_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table structure for ci_activity_logs
-- ----------------------------
DROP TABLE IF EXISTS `ci_activity_logs`;
CREATE TABLE `ci_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_log_user` (`user_id`),
  KEY `fk_log_event` (`event_id`),
  CONSTRAINT `fk_log_event` FOREIGN KEY (`event_id`) REFERENCES `ci_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `ci_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
