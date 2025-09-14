-- Script untuk menambahkan fields baru ke tabel masterbarang
-- Fields: kandungan, supplier, kemasan, nie

-- Menambahkan kolom kandungan
ALTER TABLE `masterbarang` 
ADD COLUMN `kandungan` TEXT DEFAULT NULL COMMENT 'Kandungan/komposisi barang' AFTER `deskripsi`;

-- Menambahkan kolom supplier
ALTER TABLE `masterbarang` 
ADD COLUMN `supplier` VARCHAR(200) DEFAULT NULL COMMENT 'Nama supplier/pemasok' AFTER `kandungan`;

-- Menambahkan kolom kemasan
ALTER TABLE `masterbarang` 
ADD COLUMN `kemasan` VARCHAR(100) DEFAULT NULL COMMENT 'Jenis kemasan barang' AFTER `supplier`;

-- Menambahkan kolom NIE (Nomor Izin Edar)
ALTER TABLE `masterbarang` 
ADD COLUMN `nie` VARCHAR(100) DEFAULT NULL COMMENT 'Nomor Izin Edar' AFTER `kemasan`;

-- Menambahkan index untuk field supplier untuk performa pencarian
ALTER TABLE `masterbarang` 
ADD INDEX `idx_supplier` (`supplier`);

-- Menambahkan index untuk field NIE untuk performa pencarian
ALTER TABLE `masterbarang` 
ADD INDEX `idx_nie` (`nie`);

-- Update beberapa data contoh (opsional)
-- UPDATE `masterbarang` SET 
--     `kandungan` = 'Paracetamol 500mg',
--     `supplier` = 'PT. Kimia Farma',
--     `kemasan` = 'Strip 10 tablet',
--     `nie` = 'NIE123456789'
-- WHERE `kodebarang` = 'BRG001' LIMIT 1;
