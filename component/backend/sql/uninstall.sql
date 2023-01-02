--  @package   FediverseForJoomla
--  @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
--  @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later

-- Watch out! The drop order is important since we have foreign keys.
DROP TABLE IF EXISTS `#__activitypub_block` CASCADE;
DROP TABLE IF EXISTS `#__activitypub_queue` CASCADE;
DROP TABLE IF EXISTS `#__activitypub_followers` CASCADE;
DROP TABLE IF EXISTS `#__activitypub_actors` CASCADE;
-- These are tables without foreign keys; they can be dropped as-is
DROP TABLE IF EXISTS `#__activitypub_fediblock`;
