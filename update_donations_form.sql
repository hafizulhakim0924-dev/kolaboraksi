-- Update tabel donations untuk form donasi baru
-- (sapaan Kak/Bg/Mas/Mbak, email opsional)

-- 1. Tambah kolom sapaan donatur (setelah donor_name)
ALTER TABLE `donations`
  ADD COLUMN `donor_sapaan` VARCHAR(20) DEFAULT NULL COMMENT 'Sapaan: Kak, Bg, Mas, Mbak' AFTER `donor_name`;

-- 2. Jadikan email opsional (boleh NULL)
ALTER TABLE `donations`
  MODIFY COLUMN `donor_email` VARCHAR(255) DEFAULT NULL;
