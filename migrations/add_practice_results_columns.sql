-- Alıştırma sonuçları tablosuna eksik kolonları ekle
-- Bu dosyayı phpMyAdmin'de çalıştırın veya MySQL komut satırından import edin

-- practice_results tablosuna yeni kolonlar ekle
ALTER TABLE `practice_results` 
ADD COLUMN IF NOT EXISTS `bank` varchar(100) DEFAULT 'Genel' AFTER `time_taken`,
ADD COLUMN IF NOT EXISTS `category` varchar(100) DEFAULT 'Genel' AFTER `bank`,
ADD COLUMN IF NOT EXISTS `difficulty` varchar(50) DEFAULT 'Belirtilmemiş' AFTER `category`,
ADD COLUMN IF NOT EXISTS `answers` longtext AFTER `difficulty`,
ADD COLUMN IF NOT EXISTS `detailed_results` longtext AFTER `answers`;

-- Index ekle
ALTER TABLE `practice_results`
ADD INDEX IF NOT EXISTS `idx_bank` (`bank`),
ADD INDEX IF NOT EXISTS `idx_category` (`category`);
