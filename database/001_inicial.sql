CREATE TABLE IF NOT EXISTS `users` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'visualizador',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `totp_secret` TEXT NULL,
  `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `totp_last_step` INT NULL,
  `last_login_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` CHAR(64) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `two_factor_pending` TINYINT(1) NOT NULL DEFAULT 0,
  `two_factor_attempts` INT NOT NULL DEFAULT 0,
  `flash` TEXT NULL,
  `ip` VARCHAR(64) NULL,
  `user_agent` VARCHAR(300) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `last_seen_at` DATETIME(3) NOT NULL,
  `expires_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_idx` (`user_id`),
  CONSTRAINT `sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(191) NULL,
  `ip` VARCHAR(64) NULL,
  `success` TINYINT(1) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `login_attempts_email_idx` (`email`, `created_at`),
  KEY `login_attempts_ip_idx` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` CHAR(36) NULL,
  `action` VARCHAR(60) NOT NULL,
  `detail` LONGTEXT NULL,
  `ip` VARCHAR(64) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_created_idx` (`created_at`),
  CONSTRAINT `audit_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `videos` (
  `id` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `status` VARCHAR(12) NOT NULL,
  `publish_at` DATETIME(3) NULL,
  `published_at` DATETIME(3) NULL,
  `duration_sec` INT NULL,
  `is_short` TINYINT(1) NOT NULL DEFAULT 0,
  `thumbnail_status` VARCHAR(15) NOT NULL DEFAULT 'desconhecido',
  `thumbnail_source` VARCHAR(15) NULL,
  `thumbnail_updated_by` CHAR(36) NULL,
  `thumbnail_updated_at` DATETIME(3) NULL,
  `views` INT NOT NULL DEFAULT 0,
  `likes` INT NOT NULL DEFAULT 0,
  `comments` INT NOT NULL DEFAULT 0,
  `views_7d` INT NULL,
  `synced_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  KEY `videos_status_idx` (`status`, `publish_at`),
  CONSTRAINT `videos_thumb_user_fk` FOREIGN KEY (`thumbnail_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `video_metrics` (
  `video_id` VARCHAR(64) NOT NULL,
  `month` CHAR(7) NOT NULL,
  `views` INT NOT NULL DEFAULT 0,
  `watch_minutes` INT NOT NULL DEFAULT 0,
  `avg_view_pct` DOUBLE NOT NULL DEFAULT 0,
  `subscribers_gained` INT NOT NULL DEFAULT 0,
  `likes` INT NOT NULL DEFAULT 0,
  `comments` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`video_id`, `month`),
  CONSTRAINT `video_metrics_video_fk` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `channel_daily` (
  `day` CHAR(10) NOT NULL,
  `views` INT NOT NULL DEFAULT 0,
  `watch_minutes` INT NOT NULL DEFAULT 0,
  `subscribers_gained` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `search_terms` (
  `month` CHAR(7) NOT NULL,
  `term` VARCHAR(191) NOT NULL,
  `views` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`month`, `term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ideas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `rationale` TEXT NOT NULL,
  `source` VARCHAR(15) NOT NULL,
  `score` INT NOT NULL DEFAULT 50,
  `status` VARCHAR(12) NOT NULL DEFAULT 'nova',
  `generated_by` VARCHAR(10) NOT NULL DEFAULT 'usuario',
  `updated_by` CHAR(36) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `ideas_user_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bot_messages` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `direction` VARCHAR(10) NOT NULL,
  `origin` VARCHAR(12) NOT NULL,
  `chat_id` VARCHAR(80) NULL,
  `sender` VARCHAR(120) NULL,
  `text` TEXT NOT NULL,
  `command` VARCHAR(40) NULL,
  `delivered` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bot_messages_created_idx` (`created_at`),
  KEY `bot_messages_chat_idx` (`chat_id`, `direction`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(40) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `updated_by` CHAR(36) NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`key`),
  CONSTRAINT `settings_user_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `integrations` (
  `key` VARCHAR(20) NOT NULL,
  `config` TEXT NULL,
  `status` VARCHAR(15) NOT NULL DEFAULT 'desconectado',
  `last_sync_at` DATETIME(3) NULL,
  `last_error` TEXT NULL,
  `updated_by` CHAR(36) NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`key`),
  CONSTRAINT `integrations_user_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
