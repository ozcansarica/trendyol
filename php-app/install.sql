-- ============================================================
--  Trendyol Kar/Zarar Analiz Sistemi – MySQL Schema v3
-- ============================================================

CREATE DATABASE IF NOT EXISTS `trendyol_analiz`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `trendyol_analiz`;

-- ----------------------------------------------------------
--  Kullanıcılar
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kullanicilar` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`         VARCHAR(150) UNIQUE NOT NULL,
    `sifre`         VARCHAR(255) NOT NULL,
    `ad_soyad`      VARCHAR(100),
    `rol`           ENUM('admin','uye') DEFAULT 'uye',
    `aktif`         TINYINT(1) DEFAULT 1,
    `kayit_tarihi`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Mağazalar (her kullanıcının birden fazla mağazası olabilir)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `magazalar` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kullanici_id`   INT UNSIGNED NOT NULL,
    `magaza_adi`     VARCHAR(150) NOT NULL,
    `ty_seller_id`   VARCHAR(50),
    `ty_api_key`     VARCHAR(200),
    `ty_api_secret`  VARCHAR(200),
    `aktif`          TINYINT(1) DEFAULT 1,
    `olusturma`      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar`(`id`) ON DELETE CASCADE,
    INDEX `idx_kullanici` (`kullanici_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Sipariş kayıtları
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `siparisler` (
    `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`            INT UNSIGNED NOT NULL,
    `siparis_tarihi`       VARCHAR(30),
    `siparis_no`           VARCHAR(50),
    `ulke`                 VARCHAR(50),
    `siparis_statusu`      VARCHAR(60),
    `sirket`               VARCHAR(60),
    `odeme_yontemi`        VARCHAR(60),
    `musteri`              VARCHAR(100),
    `urun_adedi`           DECIMAL(10,2) DEFAULT 0,
    `siparis_tutari`       DECIMAL(12,2) DEFAULT 0,
    `komisyon`             DECIMAL(12,2) DEFAULT 0,
    `indirim`              DECIMAL(12,2) DEFAULT 0,
    `gonderi_kargo`        DECIMAL(12,2) DEFAULT 0,
    `iade_kargo`           DECIMAL(12,2) DEFAULT 0,
    `ceza`                 DECIMAL(12,2) DEFAULT 0,
    `iptal`                DECIMAL(12,2) DEFAULT 0,
    `iade`                 DECIMAL(12,2) DEFAULT 0,
    `diger`                DECIMAL(12,2) DEFAULT 0,
    `net_tutar`            DECIMAL(12,2) DEFAULT 0,
    `platform_hizmet`      DECIMAL(12,2) DEFAULT 0,
    `yukleme_tarihi`       DATETIME,
    `ty_urun_id`           VARCHAR(60) NULL,
    `ty_barcode`           VARCHAR(100) NULL,
    `musteri_tam_ad`       VARCHAR(100),
    `kargo_takip_no`       VARCHAR(50),
    `kargo_firmasi`        VARCHAR(60),
    `api_statusu`          VARCHAR(40),
    `shipment_package_id`  VARCHAR(50),
    `api_son_guncelleme`   DATETIME,
    UNIQUE KEY `uk_magaza_siparis` (`magaza_id`, `siparis_no`),
    INDEX `idx_magaza`  (`magaza_id`),
    INDEX `idx_ty_urun` (`ty_urun_id`),
    INDEX `idx_tarih`   (`siparis_tarihi`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Satış raporu
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `urun_satis` (
    `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`            INT UNSIGNED NOT NULL,
    `barkod`               VARCHAR(100),
    `urun_adi`             VARCHAR(300),
    `model_kodu`           VARCHAR(100),
    `kategori`             VARCHAR(100),
    `marka`                VARCHAR(100),
    `renk`                 VARCHAR(60),
    `beden`                VARCHAR(60),
    `brut_siparis`         DECIMAL(10,2) DEFAULT 0,
    `brut_satis`           DECIMAL(10,2) DEFAULT 0,
    `iptal_adedi`          DECIMAL(10,2) DEFAULT 0,
    `iptal_orani`          DECIMAL(8,4)  DEFAULT 0,
    `iade_adedi`           DECIMAL(10,2) DEFAULT 0,
    `iade_orani`           DECIMAL(8,4)  DEFAULT 0,
    `net_satis`            DECIMAL(10,2) DEFAULT 0,
    `brut_ciro`            DECIMAL(14,2) DEFAULT 0,
    `indirim_tutari`       DECIMAL(12,2) DEFAULT 0,
    `net_ciro`             DECIMAL(14,2) DEFAULT 0,
    `toplam_komisyon`      DECIMAL(12,2) DEFAULT 0,
    `ort_komisyon`         DECIMAL(10,2) DEFAULT 0,
    `ort_komisyon_orani`   DECIMAL(8,4)  DEFAULT 0,
    `ort_satis_fiyati`     DECIMAL(10,2) DEFAULT 0,
    `guncel_fiyat`         DECIMAL(10,2) DEFAULT 0,
    `guncel_stok`          DECIMAL(10,2) DEFAULT 0,
    `yukleme_tarihi`       DATETIME,
    `ty_urun_id`           VARCHAR(60) NULL,
    INDEX `idx_magaza`  (`magaza_id`),
    INDEX `idx_barkod`  (`barkod`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Trendyol API ürünleri
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trendyol_urunler` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`        INT UNSIGNED NOT NULL,
    `ty_id`            VARCHAR(60),
    `barcode`          VARCHAR(100),
    `stock_code`       VARCHAR(100),
    `product_main_id`  VARCHAR(100),
    `product_code`     VARCHAR(60),
    `title`            VARCHAR(300),
    `category_name`    VARCHAR(150),
    `brand`            VARCHAR(100),
    `color`            VARCHAR(60),
    `size`             VARCHAR(60),
    `list_price`       DECIMAL(10,2) DEFAULT 0,
    `sale_price`       DECIMAL(10,2) DEFAULT 0,
    `quantity`         INT DEFAULT 0,
    `approved`         TINYINT(1) DEFAULT 0,
    `image_url`        VARCHAR(500),
    `product_url`      VARCHAR(500),
    `son_guncelleme`   DATETIME,
    `cekme_tarihi`     DATETIME,
    UNIQUE KEY `uk_magaza_ty` (`magaza_id`, `ty_id`),
    INDEX `idx_magaza`     (`magaza_id`),
    INDEX `idx_barcode`    (`barcode`),
    INDEX `idx_stock_code` (`stock_code`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Maliyetler
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `maliyetler` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`        INT UNSIGNED NOT NULL,
    `ty_urun_id`       VARCHAR(60),
    `barcode`          VARCHAR(100),
    `urun_adi`         VARCHAR(300),
    `birim_maliyet`    DECIMAL(10,2) DEFAULT 0,
    `kargo_maliyeti`   DECIMAL(10,2) DEFAULT 0,
    `paket_maliyeti`   DECIMAL(10,2) DEFAULT 0,
    `diger_maliyet`    DECIMAL(10,2) DEFAULT 0,
    `guncelleme`       DATETIME,
    UNIQUE KEY `uk_magaza_urun` (`magaza_id`, `ty_urun_id`),
    INDEX `idx_magaza`   (`magaza_id`),
    INDEX `idx_ty_urun`  (`ty_urun_id`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Sipariş satır kalemleri
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `siparis_satirlari` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`           INT UNSIGNED NOT NULL,
    `siparis_no`          VARCHAR(50),
    `shipment_package_id` VARCHAR(50),
    `barcode`             VARCHAR(100),
    `stock_code`          VARCHAR(100),
    `product_name`        VARCHAR(300),
    `adet`                INT DEFAULT 1,
    `birim_fiyat`         DECIMAL(10,2) DEFAULT 0,
    `kalem_tutari`        DECIMAL(12,2) DEFAULT 0,
    `ty_urun_id`          VARCHAR(60) NULL,
    INDEX `idx_magaza`    (`magaza_id`),
    INDEX `idx_sipno`     (`siparis_no`),
    INDEX `idx_barcode`   (`barcode`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  MİGRASYON: Mevcut tablolara magaza_id ekle
--  (CREATE IF NOT EXISTS yeni sütunları eklemez, ALTER gerekir)
-- ============================================================

-- Varsayılan mağaza oluştur (id=1) — mevcut veriler buraya bağlanır
INSERT IGNORE INTO `kullanicilar` (id, email, sifre, ad_soyad, rol, aktif)
  VALUES (1, 'admin@localhost', '$2y$10$dummy.hash.placeholder.not.valid', 'Sistem Admin', 'admin', 1);

INSERT IGNORE INTO `magazalar` (id, kullanici_id, magaza_adi, aktif)
  VALUES (1, 1, 'Varsayılan Mağaza', 1);

-- siparisler tablosuna magaza_id ekle
ALTER TABLE `siparisler`
  ADD COLUMN IF NOT EXISTS `magaza_id` INT UNSIGNED NOT NULL DEFAULT 1 FIRST,
  ADD COLUMN IF NOT EXISTS `musteri_tam_ad`      VARCHAR(100),
  ADD COLUMN IF NOT EXISTS `kargo_takip_no`      VARCHAR(50),
  ADD COLUMN IF NOT EXISTS `kargo_firmasi`       VARCHAR(60),
  ADD COLUMN IF NOT EXISTS `api_statusu`         VARCHAR(40),
  ADD COLUMN IF NOT EXISTS `shipment_package_id` VARCHAR(50),
  ADD COLUMN IF NOT EXISTS `api_son_guncelleme`  DATETIME;

-- urun_satis tablosuna magaza_id ekle
ALTER TABLE `urun_satis`
  ADD COLUMN IF NOT EXISTS `magaza_id` INT UNSIGNED NOT NULL DEFAULT 1 FIRST;

-- trendyol_urunler tablosuna magaza_id ekle
ALTER TABLE `trendyol_urunler`
  ADD COLUMN IF NOT EXISTS `magaza_id` INT UNSIGNED NOT NULL DEFAULT 1 FIRST;

-- maliyetler tablosuna magaza_id ekle
ALTER TABLE `maliyetler`
  ADD COLUMN IF NOT EXISTS `magaza_id` INT UNSIGNED NOT NULL DEFAULT 1 FIRST;

-- siparis_satirlari tablosuna magaza_id ekle (tablo yoksa CREATE ile oluşturulur)
CREATE TABLE IF NOT EXISTS `siparis_satirlari` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`           INT UNSIGNED NOT NULL DEFAULT 1,
    `siparis_no`          VARCHAR(50),
    `shipment_package_id` VARCHAR(50),
    `barcode`             VARCHAR(100),
    `stock_code`          VARCHAR(100),
    `product_name`        VARCHAR(300),
    `adet`                INT DEFAULT 1,
    `birim_fiyat`         DECIMAL(10,2) DEFAULT 0,
    `kalem_tutari`        DECIMAL(12,2) DEFAULT 0,
    `ty_urun_id`          VARCHAR(60) NULL,
    INDEX `idx_magaza`    (`magaza_id`),
    INDEX `idx_sipno`     (`siparis_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `siparis_satirlari`
  ADD COLUMN IF NOT EXISTS `magaza_id` INT UNSIGNED NOT NULL DEFAULT 1;

-- Mevcut verileri varsayılan mağazaya bağla
UPDATE `siparisler`    SET magaza_id = 1 WHERE magaza_id = 0 OR magaza_id IS NULL;
UPDATE `urun_satis`    SET magaza_id = 1 WHERE magaza_id = 0 OR magaza_id IS NULL;
UPDATE `trendyol_urunler` SET magaza_id = 1 WHERE magaza_id = 0 OR magaza_id IS NULL;
UPDATE `maliyetler`    SET magaza_id = 1 WHERE magaza_id = 0 OR magaza_id IS NULL;-- ============================================================
--  MİGRASYON: PHP kodu üzerinden yapılır (index.php boot)
--  MySQL'de ADD COLUMN IF NOT EXISTS desteklenmediği için
--  migration INFORMATION_SCHEMA sorgusu ile PHP'de çalışır.
-- ============================================================

-- Varsayılan mağaza ve kullanıcı (migrasyon için)
INSERT IGNORE INTO `kullanicilar` (id, email, sifre, ad_soyad, rol, aktif)
  VALUES (1, 'admin@localhost', '$2y$10$dummy.hash.placeholder.not.valid', 'Sistem Admin', 'admin', 1);

INSERT IGNORE INTO `magazalar` (id, kullanici_id, magaza_adi, aktif)
  VALUES (1, 1, 'Varsayilan Magaza', 1);

-- ----------------------------------------------------------
--  Settlements (Cari Hesap Ekstresi)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settlements` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`        INT UNSIGNED NOT NULL DEFAULT 1,
    `settlement_id`    VARCHAR(80),
    `order_number`     VARCHAR(50),
    `barcode`          VARCHAR(100),
    `transaction_type` VARCHAR(40),
    `amount`           DECIMAL(12,2) DEFAULT 0,
    `commission`       DECIMAL(12,2) DEFAULT 0,
    `cargo`            DECIMAL(12,2) DEFAULT 0,
    `payout`           DECIMAL(12,2) DEFAULT 0,
    `currency`         VARCHAR(10) DEFAULT 'TRY',
    `settlement_date`  DATE,
    `payment_order_id` VARCHAR(80),
    `raw_json`         TEXT,
    `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_settlement` (`magaza_id`, `settlement_id`),
    INDEX `idx_magaza`       (`magaza_id`),
    INDEX `idx_order_number` (`order_number`),
    INDEX `idx_type`         (`transaction_type`),
    INDEX `idx_date`         (`settlement_date`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Ödeme Detay (OdemeDetay_TR_*.xlsx)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `odeme_detay` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`           INT UNSIGNED NOT NULL DEFAULT 1,
    `kayit_no`            VARCHAR(80),
    `ulke`                VARCHAR(50) DEFAULT 'Türkiye',
    `islem_tipi`          VARCHAR(60),
    `siparis_no`          VARCHAR(50),
    `siparis_tarihi`      DATETIME,
    `islem_tarihi`        DATETIME,
    `urun_adi`            TEXT,
    `barkod`              VARCHAR(200),
    `komisyon_orani`      DECIMAL(8,4) DEFAULT 0,
    `ty_hakedis`          DECIMAL(12,2) DEFAULT 0,
    `satici_hakedis`      DECIMAL(12,2) DEFAULT 0,
    `stopaj`              DECIMAL(12,2) DEFAULT 0,
    `kdv_orani`           DECIMAL(6,2) DEFAULT 0,
    `vade_suresi`         INT DEFAULT 0,
    `teslim_tarihi`       DATETIME,
    `vade_tarihi`         DATETIME,
    `toplam_tutar`        DECIMAL(12,2) DEFAULT 0,
    `musteri`             VARCHAR(150),
    `paket_no`            VARCHAR(50),
    `donem_tagi`          VARCHAR(30),
    `yukleme_tarihi`      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_magaza`    (`magaza_id`),
    INDEX `idx_sipno`     (`siparis_no`),
    INDEX `idx_islem`     (`islem_tipi`),
    INDEX `idx_vade`      (`vade_tarihi`),
    INDEX `idx_donem`     (`donem_tagi`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Müşteri Talepleri / İadeler (Claims API) — Faz 2
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `talepler` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`       INT UNSIGNED NOT NULL DEFAULT 1,
    `claim_id`        VARCHAR(80),
    `siparis_no`      VARCHAR(50),
    `line_item_id`    VARCHAR(80),
    `barcode`         VARCHAR(100),
    `urun_adi`        VARCHAR(300),
    `talep_tipi`      VARCHAR(60),
    `talep_statusu`   VARCHAR(60),
    `talep_tarihi`    DATETIME,
    `iade_tutari`     DECIMAL(12,2) DEFAULT 0,
    `musteri`         VARCHAR(150),
    `kargo_takip_no`  VARCHAR(80),
    `neden`           TEXT,
    `ty_urun_id`      VARCHAR(60) NULL,
    `raw_json`        TEXT,
    `yukleme_tarihi`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_claim` (`magaza_id`, `claim_id`),
    INDEX `idx_magaza`  (`magaza_id`),
    INDEX `idx_sipno`   (`siparis_no`),
    INDEX `idx_barcode` (`barcode`),
    INDEX `idx_tip`     (`talep_tipi`),
    INDEX `idx_status`  (`talep_statusu`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
--  Müşteri Soruları (Questions API) — Faz 2
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `musteri_sorulari` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `magaza_id`      INT UNSIGNED NOT NULL DEFAULT 1,
    `question_id`    VARCHAR(80),
    `barcode`        VARCHAR(100),
    `urun_adi`       VARCHAR(300),
    `soru_metni`     TEXT,
    `cevap_metni`    TEXT,
    `soru_tarihi`    DATETIME,
    `cevap_tarihi`   DATETIME,
    `cevap_durumu`   VARCHAR(30) DEFAULT 'Cevaplanmadı',
    `ty_urun_id`     VARCHAR(60) NULL,
    `yukleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_question` (`magaza_id`, `question_id`),
    INDEX `idx_magaza`  (`magaza_id`),
    INDEX `idx_barcode` (`barcode`),
    INDEX `idx_durum`   (`cevap_durumu`),
    FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
