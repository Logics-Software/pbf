-- Create masterbarang table
DROP TABLE IF EXISTS `masterbarang`;
CREATE TABLE `masterbarang` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kodebarang` VARCHAR(50) NOT NULL UNIQUE,
  `namabarang` VARCHAR(200) NOT NULL,
  `satuan` VARCHAR(20) NOT NULL,
  `kodepabrik` VARCHAR(50) DEFAULT NULL,
  `namapabrik` VARCHAR(200) DEFAULT NULL,
  `kodegolongan` VARCHAR(50) DEFAULT NULL,
  `namagolongan` VARCHAR(200) DEFAULT NULL,
  `hpp` DECIMAL(15,2) DEFAULT 0.00,
  `hargabeli` DECIMAL(15,2) DEFAULT 0.00,
  `discbeli` DECIMAL(5,2) DEFAULT 0.00,
  `hargajual` DECIMAL(15,2) DEFAULT 0.00,
  `discjual` DECIMAL(5,2) DEFAULT 0.00,
  `kondisiharga` ENUM('normal','promo','diskon') NOT NULL DEFAULT 'normal',
  `stokakhir` INT DEFAULT 0,
  `foto` JSON DEFAULT NULL,
  `status` ENUM('aktif','non_aktif') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_kodebarang` (`kodebarang`),
  INDEX `idx_namabarang` (`namabarang`),
  INDEX `idx_kodepabrik` (`kodepabrik`),
  INDEX `idx_kodegolongan` (`kodegolongan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data
INSERT INTO `masterbarang` (`kodebarang`, `namabarang`, `satuan`, `kodepabrik`, `namapabrik`, `kodegolongan`, `namagolongan`, `hpp`, `hargabeli`, `discbeli`, `hargajual`, `discjual`, `kondisiharga`, `stokakhir`, `foto`, `status`) VALUES
('BRG001', 'Paracetamol 500mg', 'Tablet', 'PAB001', 'Kimia Farma', 'GOL001', 'Obat Bebas', 500.00, 600.00, 5.00, 800.00, 10.00, 'normal', 150, NULL, 'aktif'),
('BRG002', 'Amoxicillin 500mg', 'Kapsul', 'PAB002', 'Dexa Medica', 'GOL002', 'Obat Keras', 1200.00, 1400.00, 0.00, 1800.00, 5.00, 'normal', 75, NULL, 'aktif'),
('BRG003', 'Vitamin C 1000mg', 'Tablet', 'PAB003', 'Sido Muncul', 'GOL001', 'Obat Bebas', 800.00, 950.00, 10.00, 1200.00, 15.00, 'promo', 200, NULL, 'aktif'),
('BRG004', 'Masker Medis', 'Pcs', 'PAB004', 'Medika', 'GOL003', 'Alat Kesehatan', 200.00, 250.00, 0.00, 350.00, 0.00, 'normal', 500, NULL, 'aktif'),
('BRG005', 'Hand Sanitizer 100ml', 'Botol', 'PAB005', 'Antis', 'GOL003', 'Alat Kesehatan', 150.00, 180.00, 5.00, 250.00, 20.00, 'diskon', 100, NULL, 'aktif');

-- Migration script to convert existing single photo to JSON array format
-- Run this if you have existing data with single photo paths
-- UPDATE masterbarang SET foto = JSON_ARRAY(foto) WHERE foto IS NOT NULL AND foto != '';
