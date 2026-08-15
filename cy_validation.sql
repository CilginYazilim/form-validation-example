-- ===============================================================
--  Form Doğrulama Örneği  |  Veritabanı Kurulum Dosyası
--  cilginyazilim.com
-- ---------------------------------------------------------------
--  KURULUM (iki yoldan biri):
--    1) Terminal :  mysql -u root -p < cy_validation.sql
--    2) phpMyAdmin > İçe Aktar > Dosya seç > cy_validation.sql > Başlat
--
--  DOSYA ADI = VERİTABANI ADI. Bu depoların ortak kuralıdır: kurulum
--  dosyasına bakan biri hangi veritabanının oluşacağını dosya adından
--  bilir, açıp okumak zorunda kalmaz.
-- ===============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `cy_validation`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `cy_validation`;

DROP TABLE IF EXISTS `submissions`;

-- ---------------------------------------------------------------
--  submissions – Başarıyla doğrulanmış form gönderimleri
-- ---------------------------------------------------------------
--  NOT: `password_hash` sütunu VARDIR ama bu proje bir GİRİŞ
--  SİSTEMİ DEĞİLDİR — parola yalnızca "şifreler asla düz metin
--  saklanmaz, password_hash() kullanılır" prensibini göstermek
--  için hash'lenerek tutulur. Eşlik eden bir oturum açma akışı yoktur.
-- ---------------------------------------------------------------
CREATE TABLE `submissions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `full_name`      VARCHAR(100) NOT NULL,

  -- 190: utf8mb4'te bir VARCHAR sütuna UNIQUE indeks koyabilmenin
  -- pratik sınırı (191 × 4 bayt ≈ 767 bayt, eski InnoDB indeks
  -- sınırı). system/rules.php'deki 'max' değeri BİLEREK aynı sayıdır;
  -- doğrulama 255'e izin verseydi veritabanı kaydı sessizce kırpardı.
  `email`          VARCHAR(190) NOT NULL,
  `username`       VARCHAR(20)  NOT NULL,
  `phone`          VARCHAR(20)  DEFAULT NULL,

  -- password_hash() çıktısı (60-255 karakter arası, algoritmaya göre
  -- değişir). ASLA düz metin şifre saklanmaz.
  `password_hash`  VARCHAR(255) NOT NULL,

  `birth_date`     DATE         DEFAULT NULL,
  `message`        VARCHAR(500) DEFAULT NULL,

  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- BENZERSİZLİK ZİNCİRİNİN SON HALKASI. Canlı AJAX kontrolü ve
  -- handle_submit() içindeki son kontrol, eşzamanlı iki isteğin
  -- İKİSİNE DE "müsait" diyebilir (TOCTOU). Bu iki indeks, o durumda
  -- ikinci INSERT'i MySQL seviyesinde reddeder ve uygulama hatayı
  -- HTTP 409 olarak kullanıcıya anlaşılır biçimde döndürür.
  UNIQUE KEY `uniq_submissions_email` (`email`),
  UNIQUE KEY `uniq_submissions_username` (`username`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
--  ÖRNEK VERİ – 60 kayıt
-- ---------------------------------------------------------------
--  NEDEN ÖRNEK VERİ VAR? Boş bir tabloyla kurulan proje, kendi en
--  önemli özelliğini GÖSTEREMEZ: canlı benzersizlik kontrolü
--  denenemez — çakışacak hiçbir kayıt yoktur, her kullanıcı adı ve
--  her e-posta "müsait" çıkar. Yani projeyi indiren kişi, formun
--  yazarken cevap veren yanını hiç görmez. Bu 60 kayıt sayesinde
--  kullanıcı adı alanına "ahmet" yazıp kırmızı "zaten alınmış"
--  uyarısını, "ahmet2" yazıp yeşil "✓ Müsait" ipucunu ilk denemede
--  görürsünüz.
--
--  ÖRNEK VERİ ARAYÜZDE LİSTELENMEZ. Bu tablo hiçbir uçtan okunup
--  ekrana basılmaz; yalnızca benzersizlik sorgularının (email_exists /
--  username_exists) karşılaştırma yaptığı veri kümesidir. "Neye
--  kaydedildiği" ile "neyin gösterildiği" ayrı şeylerdir.
--
--  password_hash SÜTUNUNA NE KONDU VE NEDEN SORUN DEĞİL?
--  60 satırın hepsinde AYNI, GERÇEK bir bcrypt çıktısı vardır —
--  "OrnekParola123" parolasının hash'i. Üç sebeple sorun değildir:
--
--    1) Bu proje bir GİRİŞ SİSTEMİ DEĞİLDİR. Hiçbir yerde
--       password_verify() çağrılmaz; bu sütun hiçbir kapıyı açmaz.
--       Sütun yalnızca "parola düz metin saklanmaz" prensibini
--       göstermek için vardır.
--    2) Parola KAMUYA AÇIK ve kasıtlıdır: bu satırda yazılıdır.
--       Sızabilecek bir sır yoktur.
--    3) Düz metin "OrnekParola123" YAZMADIK. Örnek veri bile olsa o
--       sütunda düz metin görmek, kopyalayarak öğrenen birine yanlış
--       deseni öğretirdi.
--
--  GERÇEK BİR SİSTEMDE BUNU YAPMAYIN: aynı hash'i çok kullanıcıya
--  vermek, "bu iki kullanıcının parolası aynı" bilgisini sızdırır.
--  password_hash() her çağrıda RASTGELE bir tuz (salt) üretir ve aynı
--  parola bile her seferinde FARKLI bir hash verir; bu dosyadaki
--  tekrar, yalnızca SQL'in password_hash() çağıramamasındandır.
--  Uygulamanın kendisi (system/ajax.php) her kayıtta password_hash()
--  çağırır — yani formdan giren gerçek kayıtlar tekil hash alır.
-- ---------------------------------------------------------------
INSERT INTO `submissions`
    (`full_name`, `email`, `username`, `phone`, `password_hash`, `birth_date`, `message`, `created_at`)
VALUES
('Ahmet Yılmaz', 'ahmet@ornek.com', 'ahmet', '0530 100 10 20', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1970-01-01', 'Formu doldururken canlı doğrulama gerçekten çok pratikti, tebrikler.', '2026-06-01 09:15:00'),
('Ayşe Kaya', 'ayse@ornek.com', 'ayse', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Kullanıcı adı müsaitlik kontrolü hoşuma gitti; yazarken anında görüyorum.', '2026-06-01 12:32:00'),
('Mehmet Demir', 'mehmet@ornek.com', 'mehmet', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Şifre gücü göstergesi işe yarıyor, uzun şifre yazmaya teşvik ediyor.', '2026-06-01 15:49:00'),
('Fatma Şahin', 'fatma@ornek.com', 'fatma', '0533 103 13 23', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Türkçe karakterli ad soyad sorunsuz kabul edildi, teşekkürler.', '2026-06-01 19:06:00'),
('Mustafa Çelik', 'mustafa@ornek.com', 'mustafa', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1971-07-03', 'Bu örneği kendi projemde kullanmayı düşünüyorum.', '2026-06-01 22:23:00'),
('Zeynep Yıldız', 'zeynep@ornek.com', 'zeynep', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Doğum tarihi alanındaki 18 yaş kontrolü iyi düşünülmüş.', '2026-06-02 01:40:00'),
('Ali Öztürk', 'ali@ornek.com', 'ali', '0536 106 16 26', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-02 04:57:00'),
('Emine Aydın', 'emine@ornek.com', 'emine', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-02 08:14:00'),
('Hüseyin Özdemir', 'huseyin@ornek.com', 'huseyin', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1973-01-01', NULL, '2026-06-02 11:31:00'),
('Hatice Arslan', 'hatice@ornek.com', 'hatice', '0539 109 19 29', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-02 14:48:00'),
('İbrahim Doğan', 'ibrahim@ornek.com', 'ibrahim', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Formu doldururken canlı doğrulama gerçekten çok pratikti, tebrikler.', '2026-06-02 18:05:00'),
('Elif Kılıç', 'elif@ornek.com', 'elif', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Kullanıcı adı müsaitlik kontrolü hoşuma gitti; yazarken anında görüyorum.', '2026-06-02 21:22:00'),
('Osman Aslan', 'osman@ornek.com', 'osman', '0542 112 22 32', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1974-07-03', 'Şifre gücü göstergesi işe yarıyor, uzun şifre yazmaya teşvik ediyor.', '2026-06-03 00:39:00'),
('Meryem Çetin', 'meryem@ornek.com', 'meryem', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Türkçe karakterli ad soyad sorunsuz kabul edildi, teşekkürler.', '2026-06-03 03:56:00'),
('Yusuf Kara', 'yusuf@ornek.com', 'yusuf', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Bu örneği kendi projemde kullanmayı düşünüyorum.', '2026-06-03 07:13:00'),
('Sultan Koç', 'sultan@ornek.com', 'sultan', '0545 115 25 35', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Doğum tarihi alanındaki 18 yaş kontrolü iyi düşünülmüş.', '2026-06-03 10:30:00'),
('Murat Kurt', 'murat@ornek.com', 'murat', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1976-01-02', NULL, '2026-06-03 13:47:00'),
('Şerife Özkan', 'serife@ornek.com', 'serife', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-03 17:04:00'),
('Hasan Şimşek', 'hasan@ornek.com', 'hasan', '0548 118 28 38', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-03 20:21:00'),
('Havva Polat', 'havva@ornek.com', 'havva', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-03 23:38:00'),
('Kemal Erdoğan', 'kemal@ornek.com', 'kemal', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1977-07-03', 'Formu doldururken canlı doğrulama gerçekten çok pratikti, tebrikler.', '2026-06-04 02:55:00'),
('Gülşen Korkmaz', 'gulsen@ornek.com', 'gulsen', '0551 121 31 41', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Kullanıcı adı müsaitlik kontrolü hoşuma gitti; yazarken anında görüyorum.', '2026-06-04 06:12:00'),
('Selim Çakır', 'selim@ornek.com', 'selim', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Şifre gücü göstergesi işe yarıyor, uzun şifre yazmaya teşvik ediyor.', '2026-06-04 09:29:00'),
('Nurten Güneş', 'nurten@ornek.com', 'nurten', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Türkçe karakterli ad soyad sorunsuz kabul edildi, teşekkürler.', '2026-06-04 12:46:00'),
('Burak Aksoy', 'burak@ornek.com', 'burak', '0554 124 34 44', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1979-01-02', 'Bu örneği kendi projemde kullanmayı düşünüyorum.', '2026-06-04 16:03:00'),
('Sevgi Bulut', 'sevgi@ornek.com', 'sevgi', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Doğum tarihi alanındaki 18 yaş kontrolü iyi düşünülmüş.', '2026-06-04 19:20:00'),
('Emre Yavuz', 'emre@ornek.com', 'emre', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-04 22:37:00'),
('Derya Bozkurt', 'derya@ornek.com', 'derya', '0557 127 37 47', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-05 01:54:00'),
('Cem Türk', 'cem@ornek.com', 'cem', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1980-07-03', NULL, '2026-06-05 05:11:00'),
('Pınar Aktaş', 'pinar@ornek.com', 'pinar', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-05 08:28:00'),
('Serkan Güler', 'serkan@ornek.com', 'serkan', '0560 130 40 50', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Formu doldururken canlı doğrulama gerçekten çok pratikti, tebrikler.', '2026-06-05 11:45:00'),
('Özlem Coşkun', 'ozlem@ornek.com', 'ozlem', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Kullanıcı adı müsaitlik kontrolü hoşuma gitti; yazarken anında görüyorum.', '2026-06-05 15:02:00'),
('Barış Çınar', 'baris@ornek.com', 'baris', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1982-01-02', 'Şifre gücü göstergesi işe yarıyor, uzun şifre yazmaya teşvik ediyor.', '2026-06-05 18:19:00'),
('Melek Sarı', 'melek@ornek.com', 'melek', '0563 133 43 53', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Türkçe karakterli ad soyad sorunsuz kabul edildi, teşekkürler.', '2026-06-05 21:36:00'),
('Onur Keskin', 'onur@ornek.com', 'onur', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Bu örneği kendi projemde kullanmayı düşünüyorum.', '2026-06-06 00:53:00'),
('Büşra Ünal', 'busra@ornek.com', 'busra', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Doğum tarihi alanındaki 18 yaş kontrolü iyi düşünülmüş.', '2026-06-06 04:10:00'),
('Volkan Taş', 'volkan@ornek.com', 'volkan', '0566 136 46 56', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1983-07-04', NULL, '2026-06-06 07:27:00'),
('Şeyma Duman', 'seyma@ornek.com', 'seyma', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-06 10:44:00'),
('Kaan Tekin', 'kaan@ornek.com', 'kaan', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-06 14:01:00'),
('Nazlı Yalçın', 'nazli@ornek.com', 'nazli', '0569 139 49 59', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-06 17:18:00'),
('Uğur Karaca', 'ugur@ornek.com', 'ugur', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1985-01-02', 'Formu doldururken canlı doğrulama gerçekten çok pratikti, tebrikler.', '2026-06-06 20:35:00'),
('Ebru Yıldırım', 'ebru@ornek.com', 'ebru', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Kullanıcı adı müsaitlik kontrolü hoşuma gitti; yazarken anında görüyorum.', '2026-06-06 23:52:00'),
('Tolga Özer', 'tolga@ornek.com', 'tolga', '0572 142 52 62', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Şifre gücü göstergesi işe yarıyor, uzun şifre yazmaya teşvik ediyor.', '2026-06-07 03:09:00'),
('Hande Acar', 'hande@ornek.com', 'hande', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Türkçe karakterli ad soyad sorunsuz kabul edildi, teşekkürler.', '2026-06-07 06:26:00'),
('Berk Uçar', 'berk@ornek.com', 'berk', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1986-07-04', 'Bu örneği kendi projemde kullanmayı düşünüyorum.', '2026-06-07 09:43:00'),
('Damla Şen', 'damla@ornek.com', 'damla', '0575 145 55 65', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Doğum tarihi alanındaki 18 yaş kontrolü iyi düşünülmüş.', '2026-06-07 13:00:00'),
('Sinan Baş', 'sinan@ornek.com', 'sinan', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-07 16:17:00'),
('Ceren Aslan', 'ceren@ornek.com', 'ceren', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-07 19:34:00'),
('Umut Ergin', 'umut@ornek.com', 'umut', '0578 148 58 68', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1988-01-03', NULL, '2026-06-07 22:51:00'),
('Merve Toprak', 'merve@ornek.com', 'merve', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-08 02:08:00'),
('Deniz Altun', 'deniz@ornek.com', 'deniz', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Formu doldururken canlı doğrulama gerçekten çok pratikti, tebrikler.', '2026-06-08 05:25:00'),
('Gökhan Yüce', 'gokhan@ornek.com', 'gokhan', '0581 151 61 71', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Kullanıcı adı müsaitlik kontrolü hoşuma gitti; yazarken anında görüyorum.', '2026-06-08 08:42:00'),
('İrem Sezer', 'irem@ornek.com', 'irem', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1989-07-04', 'Şifre gücü göstergesi işe yarıyor, uzun şifre yazmaya teşvik ediyor.', '2026-06-08 11:59:00'),
('Tuncay Bilgin', 'tuncay@ornek.com', 'tuncay', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Türkçe karakterli ad soyad sorunsuz kabul edildi, teşekkürler.', '2026-06-08 15:16:00'),
('Aslı Karadeniz', 'asli@ornek.com', 'asli', '0584 154 64 74', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Bu örneği kendi projemde kullanmayı düşünüyorum.', '2026-06-08 18:33:00'),
('Ferhat Uysal', 'ferhat@ornek.com', 'ferhat', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, 'Doğum tarihi alanındaki 18 yaş kontrolü iyi düşünülmüş.', '2026-06-08 21:50:00'),
('Sibel Ateş', 'sibel@ornek.com', 'sibel', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', '1991-01-03', NULL, '2026-06-09 01:07:00'),
('Erdem Çiftçi', 'erdem@ornek.com', 'erdem', '0587 157 67 77', '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-09 04:24:00'),
('Yasemin Kaplan', 'yasemin@ornek.com', 'yasemin', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-09 07:41:00'),
('Levent Doğru', 'levent@ornek.com', 'levent', NULL, '$2y$10$GwK1XSZ1E8Hlsd/2nAcP0uO.zUFItGdB63OOdfmvcleT/EHK33yzm', NULL, NULL, '2026-06-09 10:58:00');
