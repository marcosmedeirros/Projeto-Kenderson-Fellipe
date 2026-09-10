CREATE TABLE IF NOT EXISTS `thumbnails` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(160) NOT NULL,
  `notes` TEXT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'rascunho',
  `file_name` VARCHAR(80) NOT NULL,
  `mime` VARCHAR(30) NOT NULL,
  `width` INT NULL,
  `height` INT NULL,
  `size_bytes` INT NOT NULL,
  `video_id` VARCHAR(64) NULL,
  `uploaded_by` CHAR(36) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `thumbnails_video_idx` (`video_id`),
  CONSTRAINT `thumbnails_video_fk` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `thumbnails_user_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `videos`
  ADD COLUMN `youtube_id` VARCHAR(20) NULL AFTER `id`,
  ADD COLUMN `source` VARCHAR(10) NOT NULL DEFAULT 'youtube' AFTER `status`,
  ADD COLUMN `thumbnail_id` INT NULL AFTER `thumbnail_status`,
  ADD COLUMN `notes` TEXT NULL,
  ADD COLUMN `created_by` CHAR(36) NULL,
  ADD COLUMN `created_at` DATETIME(3) NULL,
  ADD COLUMN `updated_at` DATETIME(3) NULL,
  ADD KEY `videos_youtube_idx` (`youtube_id`),
  ADD CONSTRAINT `videos_thumbnail_fk` FOREIGN KEY (`thumbnail_id`) REFERENCES `thumbnails` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `videos_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `users`
  ADD COLUMN `temp_password_expires_at` DATETIME(3) NULL AFTER `must_change_password`;

UPDATE `videos` SET `source` = 'exemplo' WHERE `id` LIKE 'demo-%';

UPDATE `videos` SET `created_at` = COALESCE(`synced_at`, NOW(3)), `updated_at` = COALESCE(`synced_at`, NOW(3)) WHERE `created_at` IS NULL;
