-- SQL untuk membuat table donations dan payment_logs (VERSI AMAN)
-- Versi ini akan menghapus index yang sudah ada terlebih dahulu sebelum membuat yang baru
-- Import file ini ke phpMyAdmin untuk membuat table yang diperlukan

-- Table: donations
-- Menyimpan data donasi dari user
CREATE TABLE IF NOT EXISTS `donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `donor_name` varchar(255) NOT NULL,
  `donor_email` varchar(255) NOT NULL,
  `donor_phone` varchar(20) NOT NULL,
  `amount` int(11) NOT NULL COMMENT 'Jumlah donasi (tanpa fee)',
  `fee_total` int(11) NOT NULL DEFAULT 0 COMMENT 'Total biaya admin',
  `total_amount` int(11) NOT NULL COMMENT 'Total yang harus dibayar (amount + fee)',
  `payment_method` varchar(50) NOT NULL COMMENT 'Metode pembayaran (QRIS, BCA, dll)',
  `payment_channel` varchar(100) DEFAULT NULL COMMENT 'Nama channel pembayaran',
  `tripay_reference` varchar(100) DEFAULT NULL COMMENT 'Reference dari Tripay',
  `tripay_merchant_ref` varchar(100) DEFAULT NULL COMMENT 'Merchant reference',
  `status` enum('UNPAID','PAID','EXPIRED','FAILED') NOT NULL DEFAULT 'UNPAID',
  `payment_url` varchar(500) DEFAULT NULL COMMENT 'URL untuk pembayaran',
  `qr_url` varchar(500) DEFAULT NULL COMMENT 'URL QR Code untuk pembayaran',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 jika anonim, 0 jika tidak',
  `message` text DEFAULT NULL COMMENT 'Pesan dari donatur',
  `paid_at` datetime DEFAULT NULL COMMENT 'Waktu pembayaran berhasil',
  `expired_at` datetime DEFAULT NULL COMMENT 'Waktu kadaluarsa pembayaran',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `tripay_reference` (`tripay_reference`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: payment_logs
-- Menyimpan log callback dari Tripay untuk debugging
CREATE TABLE IF NOT EXISTS `payment_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tripay_reference` varchar(100) NOT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'payment_status',
  `status` varchar(50) NOT NULL,
  `payload` text NOT NULL COMMENT 'JSON payload dari Tripay',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tripay_reference` (`tripay_reference`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hapus index yang mungkin sudah ada (ignore error jika tidak ada)
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'donations' 
               AND index_name = 'idx_donations_campaign_status');
SET @sqlstmt := IF(@exist > 0, 'DROP INDEX idx_donations_campaign_status ON donations', 'SELECT "Index tidak ada"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'donations' 
               AND index_name = 'idx_donations_tripay_ref');
SET @sqlstmt := IF(@exist > 0, 'DROP INDEX idx_donations_tripay_ref ON donations', 'SELECT "Index tidak ada"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'payment_logs' 
               AND index_name = 'idx_payment_logs_ref');
SET @sqlstmt := IF(@exist > 0, 'DROP INDEX idx_payment_logs_ref ON payment_logs', 'SELECT "Index tidak ada"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Buat index baru
CREATE INDEX idx_donations_campaign_status ON donations(campaign_id, status);
CREATE INDEX idx_donations_tripay_ref ON donations(tripay_reference);
CREATE INDEX idx_payment_logs_ref ON payment_logs(tripay_reference);


