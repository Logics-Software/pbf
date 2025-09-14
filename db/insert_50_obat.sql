-- Insert 50 sample medicine products
-- This script adds various types of medicines with different manufacturers and categories

INSERT INTO `masterbarang` (`kodebarang`, `namabarang`, `satuan`, `kodepabrik`, `namapabrik`, `kodegolongan`, `namagolongan`, `hpp`, `hargabeli`, `discbeli`, `hargajual`, `discjual`, `kondisiharga`, `stokakhir`, `foto`, `status`) VALUES

-- Obat Bebas (Over-the-counter medicines)
('BRG006', 'Paracetamol 500mg Strip', 'Strip', 'PAB001', 'Kimia Farma', 'GOL001', 'Obat Bebas', 1200.00, 1400.00, 5.00, 1800.00, 10.00, 'normal', 200, NULL, 'aktif'),
('BRG007', 'Ibuprofen 400mg', 'Tablet', 'PAB002', 'Dexa Medica', 'GOL001', 'Obat Bebas', 800.00, 950.00, 0.00, 1200.00, 15.00, 'promo', 150, NULL, 'aktif'),
('BRG008', 'Vitamin C 1000mg', 'Tablet', 'PAB003', 'Sido Muncul', 'GOL001', 'Obat Bebas', 600.00, 750.00, 10.00, 1000.00, 20.00, 'diskon', 300, NULL, 'aktif'),
('BRG009', 'Vitamin D3 1000 IU', 'Kapsul', 'PAB004', 'Medika', 'GOL001', 'Obat Bebas', 1500.00, 1800.00, 5.00, 2500.00, 12.00, 'normal', 100, NULL, 'aktif'),
('BRG010', 'Multivitamin Anak', 'Botol', 'PAB005', 'Antis', 'GOL001', 'Obat Bebas', 2500.00, 3000.00, 0.00, 4000.00, 8.00, 'normal', 80, NULL, 'aktif'),
('BRG011', 'Calcium Carbonate 500mg', 'Tablet', 'PAB001', 'Kimia Farma', 'GOL001', 'Obat Bebas', 400.00, 500.00, 0.00, 700.00, 0.00, 'normal', 250, NULL, 'aktif'),
('BRG012', 'Zinc 20mg', 'Kapsul', 'PAB002', 'Dexa Medica', 'GOL001', 'Obat Bebas', 1200.00, 1400.00, 5.00, 1800.00, 10.00, 'normal', 120, NULL, 'aktif'),
('BRG013', 'Omega 3 1000mg', 'Kapsul', 'PAB003', 'Sido Muncul', 'GOL001', 'Obat Bebas', 3000.00, 3500.00, 0.00, 4500.00, 15.00, 'promo', 60, NULL, 'aktif'),
('BRG014', 'Probiotik Lactobacillus', 'Kapsul', 'PAB004', 'Medika', 'GOL001', 'Obat Bebas', 2000.00, 2400.00, 10.00, 3200.00, 18.00, 'diskon', 90, NULL, 'aktif'),
('BRG015', 'Ginkgo Biloba 120mg', 'Tablet', 'PAB005', 'Antis', 'GOL001', 'Obat Bebas', 1800.00, 2100.00, 0.00, 2800.00, 12.00, 'normal', 70, NULL, 'aktif'),

-- Obat Keras (Prescription medicines)
('BRG016', 'Amoxicillin 500mg', 'Kapsul', 'PAB001', 'Kimia Farma', 'GOL002', 'Obat Keras', 1500.00, 1800.00, 0.00, 2500.00, 5.00, 'normal', 100, NULL, 'aktif'),
('BRG017', 'Cefadroxil 500mg', 'Kapsul', 'PAB002', 'Dexa Medica', 'GOL002', 'Obat Keras', 2000.00, 2300.00, 5.00, 3000.00, 8.00, 'normal', 80, NULL, 'aktif'),
('BRG018', 'Ciprofloxacin 500mg', 'Tablet', 'PAB003', 'Sido Muncul', 'GOL002', 'Obat Keras', 1200.00, 1400.00, 0.00, 1800.00, 0.00, 'normal', 120, NULL, 'aktif'),
('BRG019', 'Metformin 500mg', 'Tablet', 'PAB004', 'Medika', 'GOL002', 'Obat Keras', 800.00, 950.00, 10.00, 1300.00, 15.00, 'diskon', 150, NULL, 'aktif'),
('BRG020', 'Lisinopril 10mg', 'Tablet', 'PAB005', 'Antis', 'GOL002', 'Obat Keras', 1000.00, 1200.00, 0.00, 1600.00, 10.00, 'normal', 90, NULL, 'aktif'),
('BRG021', 'Simvastatin 20mg', 'Tablet', 'PAB001', 'Kimia Farma', 'GOL002', 'Obat Keras', 1500.00, 1750.00, 5.00, 2300.00, 12.00, 'normal', 110, NULL, 'aktif'),
('BRG022', 'Omeprazole 20mg', 'Kapsul', 'PAB002', 'Dexa Medica', 'GOL002', 'Obat Keras', 1200.00, 1400.00, 0.00, 1800.00, 8.00, 'normal', 95, NULL, 'aktif'),
('BRG023', 'Losartan 50mg', 'Tablet', 'PAB003', 'Sido Muncul', 'GOL002', 'Obat Keras', 1800.00, 2100.00, 10.00, 2800.00, 18.00, 'diskon', 75, NULL, 'aktif'),
('BRG024', 'Amlodipine 5mg', 'Tablet', 'PAB004', 'Medika', 'GOL002', 'Obat Keras', 900.00, 1050.00, 0.00, 1400.00, 10.00, 'normal', 130, NULL, 'aktif'),
('BRG025', 'Furosemide 40mg', 'Tablet', 'PAB005', 'Antis', 'GOL002', 'Obat Keras', 600.00, 700.00, 5.00, 950.00, 12.00, 'normal', 85, NULL, 'aktif'),

-- Obat Herbal
('BRG026', 'Temulawak Kapsul', 'Kapsul', 'PAB001', 'Kimia Farma', 'GOL003', 'Obat Herbal', 800.00, 950.00, 0.00, 1300.00, 15.00, 'promo', 200, NULL, 'aktif'),
('BRG027', 'Kunyit Asam', 'Botol', 'PAB002', 'Dexa Medica', 'GOL003', 'Obat Herbal', 1200.00, 1400.00, 10.00, 1800.00, 20.00, 'diskon', 150, NULL, 'aktif'),
('BRG028', 'Jahe Merah Kapsul', 'Kapsul', 'PAB003', 'Sido Muncul', 'GOL003', 'Obat Herbal', 1000.00, 1200.00, 5.00, 1600.00, 12.00, 'normal', 180, NULL, 'aktif'),
('BRG029', 'Sambiloto Kapsul', 'Kapsul', 'PAB004', 'Medika', 'GOL003', 'Obat Herbal', 900.00, 1050.00, 0.00, 1400.00, 10.00, 'normal', 120, NULL, 'aktif'),
('BRG030', 'Daun Sirsak Kapsul', 'Kapsul', 'PAB005', 'Antis', 'GOL003', 'Obat Herbal', 1500.00, 1750.00, 10.00, 2300.00, 18.00, 'diskon', 90, NULL, 'aktif'),
('BRG031', 'Mengkudu Kapsul', 'Kapsul', 'PAB001', 'Kimia Farma', 'GOL003', 'Obat Herbal', 1100.00, 1300.00, 5.00, 1700.00, 15.00, 'promo', 110, NULL, 'aktif'),
('BRG032', 'Brotowali Kapsul', 'Kapsul', 'PAB002', 'Dexa Medica', 'GOL003', 'Obat Herbal', 800.00, 950.00, 0.00, 1300.00, 8.00, 'normal', 140, NULL, 'aktif'),
('BRG033', 'Mahkota Dewa Kapsul', 'Kapsul', 'PAB003', 'Sido Muncul', 'GOL003', 'Obat Herbal', 1300.00, 1500.00, 10.00, 2000.00, 20.00, 'diskon', 80, NULL, 'aktif'),
('BRG034', 'Daun Kelor Kapsul', 'Kapsul', 'PAB004', 'Medika', 'GOL003', 'Obat Herbal', 1000.00, 1200.00, 5.00, 1600.00, 12.00, 'normal', 160, NULL, 'aktif'),
('BRG035', 'Kumis Kucing Kapsul', 'Kapsul', 'PAB005', 'Antis', 'GOL003', 'Obat Herbal', 700.00, 850.00, 0.00, 1200.00, 10.00, 'normal', 170, NULL, 'aktif'),

-- Alat Kesehatan
('BRG036', 'Masker N95', 'Pcs', 'PAB001', 'Kimia Farma', 'GOL004', 'Alat Kesehatan', 5000.00, 6000.00, 0.00, 8000.00, 15.00, 'promo', 500, NULL, 'aktif'),
('BRG037', 'Hand Sanitizer 250ml', 'Botol', 'PAB002', 'Dexa Medica', 'GOL004', 'Alat Kesehatan', 8000.00, 9500.00, 10.00, 12000.00, 20.00, 'diskon', 300, NULL, 'aktif'),
('BRG038', 'Termometer Digital', 'Pcs', 'PAB003', 'Sido Muncul', 'GOL004', 'Alat Kesehatan', 15000.00, 18000.00, 5.00, 25000.00, 12.00, 'normal', 50, NULL, 'aktif'),
('BRG039', 'Tensimeter Digital', 'Pcs', 'PAB004', 'Medika', 'GOL004', 'Alat Kesehatan', 25000.00, 30000.00, 0.00, 40000.00, 10.00, 'normal', 30, NULL, 'aktif'),
('BRG040', 'Oximeter Digital', 'Pcs', 'PAB005', 'Antis', 'GOL004', 'Alat Kesehatan', 35000.00, 40000.00, 10.00, 55000.00, 18.00, 'diskon', 25, NULL, 'aktif'),
('BRG041', 'Glukometer Kit', 'Set', 'PAB001', 'Kimia Farma', 'GOL004', 'Alat Kesehatan', 120000.00, 140000.00, 5.00, 180000.00, 15.00, 'promo', 20, NULL, 'aktif'),
('BRG042', 'Stetoskop', 'Pcs', 'PAB002', 'Dexa Medica', 'GOL004', 'Alat Kesehatan', 80000.00, 95000.00, 0.00, 130000.00, 8.00, 'normal', 15, NULL, 'aktif'),
('BRG043', 'Sphygmomanometer', 'Pcs', 'PAB003', 'Sido Muncul', 'GOL004', 'Alat Kesehatan', 60000.00, 70000.00, 10.00, 95000.00, 20.00, 'diskon', 12, NULL, 'aktif'),
('BRG044', 'Nebulizer', 'Pcs', 'PAB004', 'Medika', 'GOL004', 'Alat Kesehatan', 200000.00, 230000.00, 5.00, 300000.00, 12.00, 'normal', 8, NULL, 'aktif'),
('BRG045', 'Infus Set', 'Set', 'PAB005', 'Antis', 'GOL004', 'Alat Kesehatan', 15000.00, 18000.00, 0.00, 25000.00, 10.00, 'normal', 100, NULL, 'aktif'),

-- Kosmetik & Perawatan
('BRG046', 'Sunscreen SPF 50', 'Tube', 'PAB001', 'Kimia Farma', 'GOL005', 'Kosmetik', 25000.00, 30000.00, 10.00, 40000.00, 20.00, 'diskon', 80, NULL, 'aktif'),
('BRG047', 'Moisturizer Facial', 'Tube', 'PAB002', 'Dexa Medica', 'GOL005', 'Kosmetik', 35000.00, 40000.00, 5.00, 55000.00, 15.00, 'promo', 60, NULL, 'aktif'),
('BRG048', 'Shampoo Anti Dandruff', 'Botol', 'PAB003', 'Sido Muncul', 'GOL005', 'Kosmetik', 18000.00, 21000.00, 0.00, 28000.00, 12.00, 'normal', 120, NULL, 'aktif'),
('BRG049', 'Body Lotion', 'Botol', 'PAB004', 'Medika', 'GOL005', 'Kosmetik', 22000.00, 26000.00, 10.00, 35000.00, 18.00, 'diskon', 90, NULL, 'aktif'),
('BRG050', 'Face Cleanser', 'Tube', 'PAB005', 'Antis', 'GOL005', 'Kosmetik', 15000.00, 18000.00, 5.00, 25000.00, 10.00, 'normal', 100, NULL, 'aktif'),
('BRG051', 'Serum Vitamin C', 'Botol', 'PAB001', 'Kimia Farma', 'GOL005', 'Kosmetik', 45000.00, 52000.00, 0.00, 70000.00, 15.00, 'promo', 40, NULL, 'aktif'),
('BRG052', 'Toner Facial', 'Botol', 'PAB002', 'Dexa Medica', 'GOL005', 'Kosmetik', 28000.00, 33000.00, 10.00, 45000.00, 20.00, 'diskon', 70, NULL, 'aktif'),
('BRG053', 'Lip Balm', 'Pcs', 'PAB003', 'Sido Muncul', 'GOL005', 'Kosmetik', 12000.00, 14000.00, 5.00, 18000.00, 12.00, 'normal', 150, NULL, 'aktif'),
('BRG054', 'Eye Cream', 'Tube', 'PAB004', 'Medika', 'GOL005', 'Kosmetik', 55000.00, 65000.00, 0.00, 85000.00, 8.00, 'normal', 30, NULL, 'aktif'),
('BRG055', 'Face Mask Sheet', 'Pcs', 'PAB005', 'Antis', 'GOL005', 'Kosmetik', 8000.00, 9500.00, 10.00, 13000.00, 18.00, 'diskon', 200, NULL, 'aktif');
