-- SQL untuk memperbaiki index yang duplicate
-- Jalankan query ini jika mendapat error "Duplicate key name"
-- File ini akan menghapus index yang sudah ada, lalu membuat yang baru

-- Hapus index yang mungkin sudah ada
DROP INDEX IF EXISTS idx_donations_campaign_status ON donations;
DROP INDEX IF EXISTS idx_donations_tripay_ref ON donations;
DROP INDEX IF EXISTS idx_payment_logs_ref ON payment_logs;

-- Jika DROP INDEX IF EXISTS tidak didukung, gunakan query manual di bawah ini:
-- (Hapus comment dan sesuaikan dengan nama index yang error)

-- ALTER TABLE donations DROP INDEX idx_donations_campaign_status;
-- ALTER TABLE donations DROP INDEX idx_donations_tripay_ref;
-- ALTER TABLE payment_logs DROP INDEX idx_payment_logs_ref;

-- Buat index baru
CREATE INDEX idx_donations_campaign_status ON donations(campaign_id, status);
CREATE INDEX idx_donations_tripay_ref ON donations(tripay_reference);
CREATE INDEX idx_payment_logs_ref ON payment_logs(tripay_reference);

