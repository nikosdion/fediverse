--  @package   FediverseForJoomla
--  @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
--  @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later

CREATE TABLE IF NOT EXISTS `#__activitypub_actors`
(
    `id` SERIAL,
    `user_id` BIGINT(20) NOT NULL DEFAULT 0,
    `type` ENUM('Person', 'Organization', 'Service') NOT NULL DEFAULT 'Person',
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `username` VARCHAR(255) NOT NULL DEFAULT '',
    `params` TEXT NULL DEFAULT NULL,
    'created' DATETIME NULL DEFAULT NULL,
    'created_by' BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `#__activitypub_actors_username` (`username`(100))
) ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__activitypub_followers`
(
    `id` SERIAL,
    `actor_id` BIGINT(20) UNSIGNED NOT NULL,
    `follower_actor` MEDIUMTEXT,
    `username` VARCHAR(512) NOT NULL,
    `domain` VARCHAR(512) NOT NULL,
    `follow_id` MEDIUMTEXT,
    `inbox` MEDIUMTEXT,
    `shared_inbox` MEDIUMTEXT,
    `created_on` DATETIME NULL DEFAULT NULL,
    UNIQUE KEY `#__activitypub_followers_unique` (`actor_id`, `username`(100), `domain`(100)),
    INDEX `#__activitypub_followers_username` (`username`(100)),
    INDEX `#__activitypub_followers_domain` (`domain`(100)),
    FOREIGN KEY `#__activitypub_followers_actor_id` (`actor_id`) REFERENCES `#__activitypub_actors`(`id`) ON DELETE CASCADE
) ENGINE InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__activitypub_queue`
(
    `id` SERIAL,
    `activity` MEDIUMTEXT,
    `inbox` MEDIUMTEXT,
    `actor_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    `follower_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    `retry_count` INT(2) NOT NULL DEFAULT 0,
    `next_try` DATETIME NOT NULL DEFAULT NOW(),
    FOREIGN KEY `#__activitypub_queue_actor_id` (`actor_id`) REFERENCES `#__activitypub_actors`(`id`) ON DELETE CASCADE,
    FOREIGN KEY `#__activitypub_queue_follower_id` (`follower_id`) REFERENCES `#__activitypub_followers`(`id`) ON DELETE CASCADE
) ENGINE InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__activitypub_block`
(
    `id` SERIAL,
    `actor_id` BIGINT(20) UNSIGNED NOT NULL,
    `username` VARCHAR(512) NOT NULL,
    `domain` VARCHAR(512) NOT NULL,

    FOREIGN KEY `#__activitypub_block_actor_id` (`actor_id`) REFERENCES `#__activitypub_actors`(`id`) ON DELETE CASCADE
) ENGINE InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__activitypub_fediblock`
(
    `id` SERIAL,
    `domain` VARCHAR(512) NOT NULL,
    `note` MEDIUMTEXT
) ENGINE InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;