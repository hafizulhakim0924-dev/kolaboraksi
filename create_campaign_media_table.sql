-- SQL untuk membuat table campaign_media
-- Table ini digunakan untuk menyimpan multiple images dan videos untuk setiap kampanye

CREATE TABLE IF NOT EXISTS `campaign_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `media_type` enum('image','video') NOT NULL DEFAULT 'image',
  `media_path` varchar(255) NOT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `display_order` (`display_order`),
  CONSTRAINT `campaign_media_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index untuk performa query
CREATE INDEX idx_campaign_media_campaign_order ON campaign_media(campaign_id, display_order);

