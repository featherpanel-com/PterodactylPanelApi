-- Plugin Migration: pterodactylpanelapi - add-api-key-type
-- Description: Add type column to differentiate admin and client API keys
-- Created: 2025-11-11 09:00:00
-- Plugin-specific migration for pterodactylpanelapi
ALTER TABLE `featherpanel_pterodactylpanelapi_pterodactyl_api_key`
ADD COLUMN `type` ENUM ('admin', 'client') NOT NULL DEFAULT 'admin' AFTER `key`,
ADD INDEX `featherpanel_pterodactylpanelapi_api_key_type_idx` (`type`),
ADD INDEX `featherpanel_pterodactylpanelapi_api_key_type_created_by_idx` (`type`, `created_by`);