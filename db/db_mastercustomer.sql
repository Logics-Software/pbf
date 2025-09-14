-- Create mastercustomer table
DROP TABLE IF EXISTS `mastercustomer`;
CREATE TABLE `mastercustomer` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kodecustomer` VARCHAR(50) NOT NULL UNIQUE,
  `namacustomer` VARCHAR(200) NOT NULL,
  `alamatcustomer` TEXT DEFAULT NULL,
  `notelepon` VARCHAR(20) DEFAULT NULL,
  `contactperson` VARCHAR(100) DEFAULT NULL,
  `kodesales` VARCHAR(50) DEFAULT NULL,
  `namasales` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('aktif','non_aktif') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_kodecustomer` (`kodecustomer`),
  INDEX `idx_namacustomer` (`namacustomer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data
INSERT INTO `mastercustomer` (`kodecustomer`, `namacustomer`, `alamatcustomer`, `notelepon`, `contactperson`, `kodesales`, `namasales`, `status`) VALUES
('CUST001', 'PT. Sehat Selalu', 'Jl. Kesehatan No. 123, Jakarta Pusat', '021-1234567', 'Budi Santoso', 'S001', 'Sales Satu', 'aktif'),
('CUST002', 'CV. Medika Jaya', 'Jl. Medis No. 456, Bandung', '022-2345678', 'Siti Rahayu', 'S002', 'Sales Dua', 'aktif'),
('CUST003', 'UD. Farmasi Mandiri', 'Jl. Obat No. 789, Surabaya', '031-3456789', 'Ahmad Wijaya', 'S001', 'Sales Satu', 'aktif'),
('CUST004', 'PT. Kesehatan Prima', 'Jl. Sehat No. 321, Medan', '061-4567890', 'Maria Sari', 'S003', 'Sales Tiga', 'aktif'),
('CUST005', 'CV. Apotek Sejahtera', 'Jl. Apotek No. 654, Yogyakarta', '0274-5678901', 'Joko Susilo', 'S002', 'Sales Dua', 'aktif');
