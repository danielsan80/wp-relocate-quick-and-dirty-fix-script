SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `dan_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `media_id` int(11) NOT NULL,
  `filepath` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `guid` varchar(255) DEFAULT NULL,
  `guid_ok` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `pathes` longtext NOT NULL,
  `wp_attached_file_is_dirty` varchar(255) DEFAULT NULL,
  `wp_attached_file` varchar(255) DEFAULT NULL,
  `wp_attached_file_ok` varchar(255) DEFAULT NULL,
  `wp_attachment_metadata_is_dirty` varchar(255) DEFAULT NULL,
  `wp_attachment_metadata` varchar(255) DEFAULT NULL,
  `wp_attachment_metadata_ok` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `media_id` (`media_id`),
  KEY `status` (`status`)
);


CREATE TABLE IF NOT EXISTS `dan_reverse_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `pathes` longtext NOT NULL,
  `num` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `filename` (`filename`)
);

