-- Update enumerasi kondisi barang untuk mendukung normal, promo, sale, spesial, deals
-- File: db/update_kondisi_barang_enum_v2.sql

-- 1. Update field kondisiharga untuk mendukung nilai baru (termasuk promo)
ALTER TABLE masterbarang 
MODIFY COLUMN kondisiharga ENUM('normal', 'promo', 'sale', 'spesial', 'deals') 
DEFAULT 'normal' 
COMMENT 'Kondisi harga barang: normal, promo, sale, spesial, deals';

-- 2. Update data existing jika ada nilai yang tidak sesuai
UPDATE masterbarang 
SET kondisiharga = 'normal' 
WHERE kondisiharga NOT IN ('normal', 'promo', 'sale', 'spesial', 'deals') 
OR kondisiharga IS NULL;

-- 3. Set default value untuk field yang kosong
UPDATE masterbarang 
SET kondisiharga = 'normal' 
WHERE kondisiharga = '';

-- 4. Verifikasi data setelah update
SELECT 
    kondisiharga,
    COUNT(*) as jumlah_barang
FROM masterbarang 
GROUP BY kondisiharga
ORDER BY kondisiharga;
