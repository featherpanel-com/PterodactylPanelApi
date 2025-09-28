-- Plugin Migration: pterodactylpanelapi - pterodactyl-api-key
-- Description: The api keys table form pterodactyl!
-- Created: 2025-09-26 15:14:11
-- Plugin-specific migration for pterodactylpanelapi
CREATE TABLE
	IF NOT EXISTS `featherpanel_pterodactylpanelapi_pterodactyl_api_key` (
		`id` INT NOT NULL AUTO_INCREMENT,
		`name` VARCHAR(255) NOT NULL,
		`key` VARCHAR(255) NOT NULL,
		`last_used` DATETIME NULL,
		`created_by` INT NOT NULL,
		`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		`deleted` ENUM ('false', 'true') NOT NULL DEFAULT 'false',
		FOREIGN KEY (`created_by`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE,
		PRIMARY KEY (`id`)
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;