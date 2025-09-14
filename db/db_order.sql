-- Database schema untuk transaksi order
-- Tabel headerorder untuk data header transaksi order

CREATE TABLE `headerorder` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `noorder` VARCHAR(50) NOT NULL UNIQUE,
  `tanggalorder` DATE NOT NULL,
  `kodecustomer` VARCHAR(50) NOT NULL,
  `namacustomer` VARCHAR(200) NOT NULL,
  `kodesales` VARCHAR(50) DEFAULT NULL,
  `namasales` VARCHAR(200) DEFAULT NULL,
  `totalorder` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('idle','proses','faktur','kirim','terima','batal') NOT NULL DEFAULT 'idle',
  `iduser` INT UNSIGNED NOT NULL,
  `nofaktur` VARCHAR(50) DEFAULT NULL,
  `tanggalfaktur` DATE DEFAULT NULL,
  `namapengirim` VARCHAR(200) DEFAULT NULL,
  `tanggalterima` DATE DEFAULT NULL,
  `konfirmasiterima` ENUM('belum','sudah','ditolak') NOT NULL DEFAULT 'belum',
  `catatanterima` TEXT DEFAULT NULL,
  `fotobukti` JSON DEFAULT NULL,
  `rating` TINYINT UNSIGNED DEFAULT NULL,
  `komentar` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_noorder` (`noorder`),
  KEY `idx_tanggalorder` (`tanggalorder`),
  KEY `idx_kodecustomer` (`kodecustomer`),
  KEY `idx_kodesales` (`kodesales`),
  KEY `idx_status` (`status`),
  KEY `idx_iduser` (`iduser`),
  KEY `idx_nofaktur` (`nofaktur`),
  KEY `idx_tanggalterima` (`tanggalterima`),
  KEY `idx_konfirmasiterima` (`konfirmasiterima`),
  KEY `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel detailorder untuk data detail item barang yang diorder

CREATE TABLE `detailorder` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `noorder` VARCHAR(50) NOT NULL,
  `kodebarang` VARCHAR(50) NOT NULL,
  `namabarang` VARCHAR(200) NOT NULL,
  `satuan` VARCHAR(20) NOT NULL,
  `jumlah` INT UNSIGNED NOT NULL DEFAULT 1,
  `hargasatuan` INT UNSIGNED NOT NULL DEFAULT 0,
  `discount` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `totalharga` INT UNSIGNED NOT NULL DEFAULT 0,
  `nourut` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_noorder` (`noorder`),
  KEY `idx_kodebarang` (`kodebarang`),
  KEY `idx_nourut` (`noorder`, `nourut`),
  CONSTRAINT `fk_detailorder_headerorder` FOREIGN KEY (`noorder`) REFERENCES `headerorder` (`noorder`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data untuk testing
INSERT INTO `headerorder` (`noorder`, `tanggalorder`, `kodecustomer`, `namacustomer`, `kodesales`, `namasales`, `totalorder`, `status`, `iduser`, `tanggalterima`, `konfirmasiterima`, `catatanterima`, `rating`, `komentar`) VALUES
('ORD250100001', '2024-01-15', 'C001', 'Customer Satu', 'S001', 'Sales Satu', 150000, 'idle', 1, NULL, 'belum', NULL, NULL, NULL),
('ORD250100002', '2024-01-16', 'C002', 'Customer Dua', 'S002', 'Sales Dua', 275000, 'proses', 1, NULL, 'belum', NULL, NULL, NULL),
('ORD250100003', '2024-01-17', 'C003', 'Customer Tiga', 'S001', 'Sales Satu', 425000, 'terima', 1, '2024-01-20', 'sudah', 'Barang diterima dengan baik, kondisi sesuai pesanan', 5, 'Pelayanan sangat memuaskan, barang sampai tepat waktu');

INSERT INTO `detailorder` (`noorder`, `kodebarang`, `namabarang`, `satuan`, `jumlah`, `hargasatuan`, `discount`, `totalharga`, `nourut`) VALUES
('ORD250100001', 'BRG001', 'Barang Satu', 'Pcs', 10, 15000, 0.00, 150000, 1),
('ORD250100002', 'BRG002', 'Barang Dua', 'Kg', 5, 50000, 5.00, 237500, 1),
('ORD250100002', 'BRG003', 'Barang Tiga', 'Pcs', 2, 25000, 0.00, 50000, 2),
('ORD250100003', 'BRG001', 'Barang Satu', 'Pcs', 15, 15000, 10.00, 202500, 1),
('ORD250100003', 'BRG004', 'Barang Empat', 'Box', 3, 75000, 0.00, 225000, 2);
