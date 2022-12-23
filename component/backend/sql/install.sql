/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

CREATE TABLE IF NOT EXISTS `#__activitypub_actors`
(
    `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) NOT NULL DEFAULT 0,
    `type` ENUM('Person', 'Organization', 'Service') NOT NULL DEFAULT 'Person',
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `username` VARCHAR(255) NOT NULL DEFAULT '',
    `params` TEXT NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `#__activitypub_actors_username` (`username`(100))
) ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;