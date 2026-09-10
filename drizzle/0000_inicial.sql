CREATE TABLE `audit_logs` (
	`id` int AUTO_INCREMENT NOT NULL,
	`user_id` varchar(36),
	`action` varchar(60) NOT NULL,
	`detail` longtext,
	`ip` varchar(64),
	`created_at` datetime(3) NOT NULL,
	CONSTRAINT `audit_logs_id` PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `bot_messages` (
	`id` int AUTO_INCREMENT NOT NULL,
	`direction` varchar(10) NOT NULL,
	`origin` varchar(12) NOT NULL,
	`chat_id` varchar(80),
	`sender` varchar(120),
	`text` text NOT NULL,
	`command` varchar(40),
	`delivered` boolean NOT NULL DEFAULT true,
	`created_at` datetime(3) NOT NULL,
	CONSTRAINT `bot_messages_id` PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `channel_daily` (
	`day` varchar(10) NOT NULL,
	`views` int NOT NULL DEFAULT 0,
	`watch_minutes` int NOT NULL DEFAULT 0,
	`subscribers_gained` int NOT NULL DEFAULT 0,
	CONSTRAINT `channel_daily_day` PRIMARY KEY(`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `ideas` (
	`id` int AUTO_INCREMENT NOT NULL,
	`title` varchar(200) NOT NULL,
	`rationale` text NOT NULL,
	`source` varchar(15) NOT NULL,
	`score` int NOT NULL DEFAULT 50,
	`status` varchar(12) NOT NULL DEFAULT 'nova',
	`generated_by` varchar(10) NOT NULL DEFAULT 'usuario',
	`updated_by` varchar(36),
	`created_at` datetime(3) NOT NULL,
	`updated_at` datetime(3) NOT NULL,
	CONSTRAINT `ideas_id` PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `integrations` (
	`key` varchar(20) NOT NULL,
	`config` text,
	`status` varchar(15) NOT NULL DEFAULT 'desconectado',
	`last_sync_at` datetime(3),
	`last_error` text,
	`updated_by` varchar(36),
	`updated_at` datetime(3) NOT NULL,
	CONSTRAINT `integrations_key` PRIMARY KEY(`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `login_attempts` (
	`id` int AUTO_INCREMENT NOT NULL,
	`email` varchar(191),
	`ip` varchar(64),
	`success` boolean NOT NULL,
	`created_at` datetime(3) NOT NULL,
	CONSTRAINT `login_attempts_id` PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `search_terms` (
	`month` varchar(7) NOT NULL,
	`term` varchar(191) NOT NULL,
	`views` int NOT NULL DEFAULT 0,
	CONSTRAINT `search_terms_month_term_pk` PRIMARY KEY(`month`,`term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `sessions` (
	`id` varchar(64) NOT NULL,
	`user_id` varchar(36) NOT NULL,
	`two_factor_pending` boolean NOT NULL DEFAULT false,
	`two_factor_attempts` int NOT NULL DEFAULT 0,
	`ip` varchar(64),
	`user_agent` varchar(300),
	`created_at` datetime(3) NOT NULL,
	`last_seen_at` datetime(3) NOT NULL,
	`expires_at` datetime(3) NOT NULL,
	CONSTRAINT `sessions_id` PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `settings` (
	`key` varchar(40) NOT NULL,
	`value` longtext NOT NULL,
	`updated_by` varchar(36),
	`updated_at` datetime(3) NOT NULL,
	CONSTRAINT `settings_key` PRIMARY KEY(`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `users` (
	`id` varchar(36) NOT NULL,
	`name` varchar(120) NOT NULL,
	`email` varchar(191) NOT NULL,
	`password_hash` varchar(255) NOT NULL,
	`role` varchar(20) NOT NULL DEFAULT 'visualizador',
	`active` boolean NOT NULL DEFAULT true,
	`must_change_password` boolean NOT NULL DEFAULT false,
	`totp_secret` text,
	`totp_enabled` boolean NOT NULL DEFAULT false,
	`totp_last_step` int,
	`last_login_at` datetime(3),
	`created_at` datetime(3) NOT NULL,
	CONSTRAINT `users_id` PRIMARY KEY(`id`),
	CONSTRAINT `users_email_unique` UNIQUE(`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `video_metrics` (
	`video_id` varchar(64) NOT NULL,
	`month` varchar(7) NOT NULL,
	`views` int NOT NULL DEFAULT 0,
	`watch_minutes` int NOT NULL DEFAULT 0,
	`avg_view_pct` double NOT NULL DEFAULT 0,
	`subscribers_gained` int NOT NULL DEFAULT 0,
	`likes` int NOT NULL DEFAULT 0,
	`comments` int NOT NULL DEFAULT 0,
	CONSTRAINT `video_metrics_video_id_month_pk` PRIMARY KEY(`video_id`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
CREATE TABLE `videos` (
	`id` varchar(64) NOT NULL,
	`title` varchar(255) NOT NULL,
	`status` varchar(12) NOT NULL,
	`publish_at` datetime(3),
	`published_at` datetime(3),
	`duration_sec` int,
	`is_short` boolean NOT NULL DEFAULT false,
	`thumbnail_status` varchar(15) NOT NULL DEFAULT 'desconhecido',
	`thumbnail_source` varchar(15),
	`thumbnail_updated_by` varchar(36),
	`thumbnail_updated_at` datetime(3),
	`views` int NOT NULL DEFAULT 0,
	`likes` int NOT NULL DEFAULT 0,
	`comments` int NOT NULL DEFAULT 0,
	`views_7d` int,
	`synced_at` datetime(3),
	CONSTRAINT `videos_id` PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--> statement-breakpoint
ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `ideas` ADD CONSTRAINT `ideas_updated_by_users_id_fk` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `integrations` ADD CONSTRAINT `integrations_updated_by_users_id_fk` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `sessions` ADD CONSTRAINT `sessions_user_id_users_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `settings` ADD CONSTRAINT `settings_updated_by_users_id_fk` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `video_metrics` ADD CONSTRAINT `video_metrics_video_id_videos_id_fk` FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `videos` ADD CONSTRAINT `videos_thumbnail_updated_by_users_id_fk` FOREIGN KEY (`thumbnail_updated_by`) REFERENCES `users`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `audit_logs_created_idx` ON `audit_logs` (`created_at`);--> statement-breakpoint
CREATE INDEX `bot_messages_created_idx` ON `bot_messages` (`created_at`);--> statement-breakpoint
CREATE INDEX `login_attempts_email_idx` ON `login_attempts` (`email`,`created_at`);--> statement-breakpoint
CREATE INDEX `login_attempts_ip_idx` ON `login_attempts` (`ip`,`created_at`);--> statement-breakpoint
CREATE INDEX `sessions_user_idx` ON `sessions` (`user_id`);--> statement-breakpoint
CREATE INDEX `videos_status_idx` ON `videos` (`status`,`publish_at`);