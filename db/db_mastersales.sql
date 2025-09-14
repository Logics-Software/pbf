-- Create mastersales table
DROP TABLE IF EXISTS `mastersales`;
CREATE TABLE `mastersales` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kodesales` VARCHAR(50) NOT NULL UNIQUE,
  `namasales` VARCHAR(200) NOT NULL,
  `alamatsales` TEXT DEFAULT NULL,
  `notelepon` VARCHAR(20) DEFAULT NULL,
  `status` ENUM('aktif','non_aktif') NOT NULL DEFAULT 'aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_kodesales` (`kodesales`),
  INDEX `idx_namasales` (`namasales`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data
INSERT INTO `mastersales` (`kodesales`, `namasales`, `alamatsales`, `notelepon`, `status`) VALUES
('S001', 'Sales Satu', 'Jl. Sales No. 123, Jakarta Pusat', '021-1111111', 'aktif'),
('S002', 'Sales Dua', 'Jl. Sales No. 456, Bandung', '022-2222222', 'aktif'),
('S003', 'Sales Tiga', 'Jl. Sales No. 789, Surabaya', '031-3333333', 'aktif'),
('S004', 'Sales Empat', 'Jl. Sales No. 321, Medan', '061-4444444', 'aktif'),
('S005', 'Sales Lima', 'Jl. Sales No. 654, Yogyakarta', '0274-5555555', 'aktif');
