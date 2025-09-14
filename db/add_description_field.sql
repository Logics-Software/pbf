-- Add description field to masterbarang table for online shop
-- This field will store detailed product descriptions

ALTER TABLE masterbarang 
ADD COLUMN deskripsi TEXT NULL 
COMMENT 'Deskripsi detail produk untuk online shop' 
AFTER namabarang;

-- Update existing products with sample descriptions
UPDATE masterbarang SET deskripsi = 'Obat batuk herbal yang efektif meredakan batuk kering dan berdahak. Terbuat dari bahan alami yang aman untuk dikonsumsi sehari-hari.' WHERE kodebarang = 'OBT001';

UPDATE masterbarang SET deskripsi = 'Vitamin C dengan dosis tinggi untuk meningkatkan daya tahan tubuh. Cocok untuk konsumsi harian dan membantu proses pemulihan.' WHERE kodebarang = 'OBT002';

UPDATE masterbarang SET deskripsi = 'Paracetamol 500mg untuk meredakan demam dan sakit kepala. Aman untuk dewasa dan anak-anak dengan dosis yang tepat.' WHERE kodebarang = 'OBT003';

-- Add index for better performance when searching descriptions
CREATE INDEX idx_masterbarang_deskripsi ON masterbarang(deskripsi(100));
