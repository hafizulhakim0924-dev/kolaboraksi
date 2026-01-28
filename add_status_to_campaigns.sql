-- SQL untuk menambahkan kolom status ke tabel campaigns
-- Status: NULL atau kosong = approved (untuk data lama yang belum ada fitur ini)
-- Status: 'pending' = menunggu persetujuan admin
-- Status: 'approved' = sudah disetujui dan muncul di frontend
-- Status: 'rejected' = ditolak oleh admin

ALTER TABLE `campaigns` 
ADD COLUMN `status` VARCHAR(20) DEFAULT NULL 
COMMENT 'Status kampanye: NULL/approved (auto-approved), pending, approved, rejected' 
AFTER `type`;

-- Update semua data yang kosong/NULL menjadi 'approved' (untuk backward compatibility)
UPDATE `campaigns` SET `status` = 'approved' WHERE `status` IS NULL OR `status` = '';

-- Buat tabel admin utama jika belum ada
CREATE TABLE IF NOT EXISTS `admin_utama` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert admin utama default (password: admin123 - harus di-hash dengan password_hash di PHP)
-- Password hash untuk 'admin123' menggunakan bcrypt
INSERT INTO `admin_utama` (`username`, `password`, `nama`, `email`) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Utama', 'admin@kolaboraksi.com')
ON DUPLICATE KEY UPDATE `username`=`username`;

