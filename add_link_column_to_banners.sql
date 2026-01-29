-- SQL untuk menambahkan kolom 'link' ke tabel banners (jika belum ada)
-- Import file ini ke phpMyAdmin jika kolom 'link' belum ada di tabel banners

-- Cek dan tambahkan kolom 'link' jika belum ada
ALTER TABLE `banners` 
ADD COLUMN IF NOT EXISTS `link` varchar(255) DEFAULT NULL AFTER `image`;

-- Jika MySQL versi lama tidak support IF NOT EXISTS, gunakan query ini:
-- ALTER TABLE `banners` ADD COLUMN `link` varchar(255) DEFAULT NULL AFTER `image`;

