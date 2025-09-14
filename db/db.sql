-- Create database (run once, or create manually)
-- CREATE DATABASE IF NOT EXISTS `pbf` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `pbf`;

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `namalengkap` VARCHAR(100) NOT NULL,
  `alamat` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('operator','manajemen','sales','customer','admin') NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `kodesales` VARCHAR(50) DEFAULT NULL,
  `kodecustomer` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('aktif','non aktif') NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed admin user (password: admin123)
INSERT INTO `user` (`username`, `password`, `namalengkap`, `alamat`, `role`, `email`, `kodesales`, `kodecustomer`, `status`)
VALUES (
  'admin',
  '$2y$10$6vS1Xj67L9a9h0l8S8e7OOUNB3m7a1xFQTHQY7a2mF4sV8qBvM4D2',
  'Administrator',
  'Office',
  'admin',
  'admin@example.com',
  NULL,
  NULL,
  'aktif'
);

-- Additional sample users
INSERT INTO `user` (`username`, `password`, `namalengkap`, `alamat`, `role`, `email`, `kodesales`, `kodecustomer`, `status`) VALUES
('operator1', '$2y$10$6vS1Xj67L9a9h0l8S8e7OOUNB3m7a1xFQTHQY7a2mF4sV8qBvM4D2', 'Operator Satu', 'Office', 'operator', 'operator1@example.com', NULL, NULL, 'aktif'),
('manager', '$2y$10$6vS1Xj67L9a9h0l8S8e7OOUNB3m7a1xFQTHQY7a2mF4sV8qBvM4D2', 'Manajemen Utama', 'HQ', 'manajemen', 'manager@example.com', NULL, NULL, 'aktif'),
('sales01', '$2y$10$6vS1Xj67L9a9h0l8S8e7OOUNB3m7a1xFQTHQY7a2mF4sV8qBvM4D2', 'Sales Satu', 'Jakarta', 'sales', 'sales01@example.com', 'S001', NULL, 'aktif'),
('cust01', '$2y$10$6vS1Xj67L9a9h0l8S8e7OOUNB3m7a1xFQTHQY7a2mF4sV8qBvM4D2', 'Customer Satu', 'Surabaya', 'customer', 'cust01@example.com', NULL, 'C001', 'aktif'),
('nonaktif', '$2y$10$6vS1Xj67L9a9h0l8S8e7OOUNB3m7a1xFQTHQY7a2mF4sV8qBvM4D2', 'User Non Aktif', 'Bandung', 'sales', 'na@example.com', 'S999', NULL, 'non aktif');

-- Note: Hash corresponds to password 'admin123'

