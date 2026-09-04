<?php
/**
 * =====================================================================
 *  YAPILANDIRMA DOSYASI
 *  cilginyazilim.com – Form Doğrulama Örneği
 * =====================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
 *  .env DESTEĞİ
 * ---------------------------------------------------------------------
 *  Veritabanı bilgileri bu dosyanın İÇİNDE durmak zorunda değil.
 *  Depo kökündeki ".env" dosyasına yazarsanız buradaki varsayılanlar
 *  devreye girmez — ve ".env" .gitignore içinde olduğu için parolanız
 *  depoya hiç girmez.
 *
 *  NEDEN AYRI BİR DOSYA?
 *  config.php DEPODA durur ve her dağıtımda depodaki sürümle
 *  DEĞİŞTİRİLİR; içine elle yazdığınız parola bir sonraki deploy'da
 *  silinir. .env ise deploy'un dokunmadığı bir dosyadır: bir kez
 *  oluşturursunuz, kalıcıdır.
 *
 *  DEĞER ARAMA SIRASI
 *      1. config.local.php içinde define() edilmişse o kazanır
 *         (bu dosyada varsa; aşağıdaki "! defined()" kontrolleri)
 *      2. .env dosyası
 *      3. Sunucunun gerçek ortam değişkeni (Apache SetEnv, systemd…)
 *      4. Bu dosyadaki varsayılan
 *
 *  cy_env() bilerek getenv() ile AYNI şeyi döndürür (değer ya da
 *  false). Böylece aşağıdaki satırlar olduğu gibi çalışmaya devam
 *  eder; "?:" ve "!== false" kalıplarının hiçbiri değişmedi.
 * ------------------------------------------------------------------ */
if (! function_exists('cy_env')) {
    /**
     * .env dosyasından (yoksa ortamdan) bir değer okur.
     *
     * @return string|false Değer yoksa false — getenv() ile aynı sözleşme.
     */
    function cy_env(string $key): string|false
    {
        static $env = null;

        if ($env === null) {
            $env  = [];
            $file = dirname(__DIR__) . '/.env';

            if (is_file($file) && is_readable($file)) {
                /* IGNORE_NEW_LINES + SKIP_EMPTY_LINES: satır sonlarını ve
                 * boş satırları baştan eler; ayrıştırma sadeleşir. */
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                foreach ($lines as $line) {
                    $line = trim($line);

                    // Yorum satırı ya da "=" içermeyen satır atlanır.
                    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                        continue;
                    }

                    [$name, $value] = explode('=', $line, 2);

                    $name  = trim($name);
                    $value = trim($value);

                    /* Tırnak içindeki değerlerden tırnakları at:
                     * DB_PASS="a b c" → a b c
                     * Tırnak zorunlu değildir; yalnızca boşluk içeren
                     * parolalar için gerekir. */
                    if (strlen($value) >= 2
                        && ($value[0] === '"' || $value[0] === "'")
                        && $value[strlen($value) - 1] === $value[0]
                    ) {
                        $value = substr($value, 1, -1);
                    }

                    if ($name !== '') {
                        $env[$name] = $value;
                    }
                }
            }
        }

        // .env'de varsa o; yoksa sunucunun gerçek ortam değişkeni.
        return $env[$key] ?? getenv($key);
    }
}

/* Doğrudan çağrılmaya karşı ikinci katman — bkz. system/.htaccess.
 * ÖLÇÜLEN SORUN: /system/config.php isteği HTTP 200 dönüyordu. Ekrana
 * bir şey basmıyordu, bu yüzden "zararsız" görünüyordu; değildi. Bu
 * dosya her çağrıldığında bir VERİTABANI BAĞLANTISI açar. Kimliği
 * doğrulanmamış bir istekle tetiklenebilen ve iş yapan her adres, ucuz
 * bir hizmet dışı bırakma (DoS) kaldıracıdır. Ayrıca bir yapılandırma
 * hatası (PHP'nin bir an yorumlanmaması) bu dosyanın KAYNAK KODUNU —
 * veritabanı parolasıyla birlikte— düz metin olarak servis eder. */
if (!defined('CY_APP')) {
    http_response_code(403);
    exit;
}


/* =====================================================================
 *  OTURUM GÜVENLİĞİ
 * ---------------------------------------------------------------------
 *  ÖLÇÜLEN SORUN (session_start'tan ÖNCE ayarlanmalıydı, hiç yoktu):
 *
 *  1) Çerez bayrakları YOKTU. Yanıt şuydu:
 *        Set-Cookie: PHPSESSID=...; path=/
 *     HttpOnly yok → sayfada bir XSS açığı çıksa, oturum çerezi
 *     JavaScript'ten okunabilirdi. SameSite yok → çerez, başka bir
 *     sitenin tetiklediği isteklere de eklenirdi (CSRF'nin tarayıcı
 *     seviyesindeki ilk savunması budur; token ikinci savunmadır).
 *
 *  2) OTURUM SABİTLEME (session fixation) ÇALIŞIYORDU — denendi:
 *        Cookie: PHPSESSID=saldirganinsectigikimlik1234
 *     Sunucu bu UYDURMA kimliği kabul etti, o kimlikle bir oturum
 *     açtı, içinde CSRF token üretti ve uç noktalar 200 döndü. Yani
 *     saldırgan, kurbana kendi seçtiği bir oturum kimliğini
 *     benimsetebilirdi. use_strict_mode=1, PHP'ye "sunucunun
 *     ÜRETMEDİĞİ bir kimliği kabul etme, yenisini üret" der ve bu
 *     vektörü kapatır.
 *
 *  NOT — session_regenerate_id() BU PROJEDE NEDEN TEK BAŞINA YETMEZDİ:
 *  Klasik tavsiye "girişten sonra kimliği yenile"dir; çünkü asıl risk,
 *  saldırganın önceden bildiği bir oturumun SONRADAN yetki kazanmasıdır.
 *  Bu projede giriş yoktur, dolayısıyla yenilenecek bir "yetki anı" da
 *  yoktur. Buradaki gerçek savunma use_strict_mode'dur. Yine de
 *  csrf_token() ilk kez token basarken kimliği yeniliyoruz (bkz.
 *  function.php) — "boş oturum ile veri taşıyan oturum aynı kimliği
 *  paylaşmasın" ilkesi ucuzdur ve zararı yoktur.
 * ================================================================== */
if (session_status() === PHP_SESSION_NONE) {
    // Sunucunun üretmediği oturum kimliklerini reddet (sabitleme savunması).
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // JavaScript çerezi okuyamasın
        'samesite' => 'Lax',  // Başka sitelerin tetiklediği POST'lara çerez eklenmesin
        // HTTPS altında çalışıyorsa çerez yalnızca HTTPS ile gitsin.
        // Localhost'ta (http) 'true' yazsaydık oturum HİÇ kurulamazdı;
        // bu yüzden değer sabit değil, isteğe göre belirlenir.
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);

    session_start();
}

define('DB_HOST', cy_env('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', cy_env('DB_NAME') ?: 'cy_validation');
define('DB_USER', cy_env('DB_USER') ?: 'root');
define('DB_PASS', cy_env('DB_PASS') !== false ? (string) cy_env('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 *  ZAMAN DİLİMİ
 * ---------------------------------------------------------------------
 *  ÖLÇÜLEN SORUN: php.ini'de date.timezone çoğu XAMPP kurulumunda
 *  sunucunun coğrafi diliminden farklıdır. Bu makinede PHP
 *  "Europe/Berlin", MySQL ise sistem dilimi (Europe/Istanbul)
 *  kullanıyordu; aynı anı anlatan iki satır BİR SAAT farklı görünüyordu:
 *
 *      worker günlüğü (PHP date)  : 14:03:17
 *      veritabanı  (MySQL NOW())  : 15:03:17
 *
 *  Bu depodaki zaman ARİTMETİĞİ bilinçli olarak SQL tarafında yapılır
 *  (NOW(), INTERVAL, TIMESTAMPDIFF), bu yüzden hesaplar zaten doğrudur.
 *  Kayan şey, PHP'nin ekrana/günlüğe bastığı saatti — ve demoyu
 *  deneyen biri için bu, "sistem yanlış çalışıyor" gibi görünür.
 *
 *  Çözüm: dilimi ORTAMA bırakmak yerine açıkça sabitliyoruz. Kendi
 *  sunucunuzda farklı bir dilim istiyorsanız APP_TIMEZONE ortam
 *  değişkenini tanımlamanız yeterlidir; kod değiştirmenize gerek yok.
 * ------------------------------------------------------------------ */
define('APP_TIMEZONE', cy_env('APP_TIMEZONE') ?: 'Europe/Istanbul');

// @ kullanmıyoruz: geçersiz bir dilim adı sessizce yutulmamalı.
if (in_array(APP_TIMEZONE, timezone_identifiers_list(), true)) {
    date_default_timezone_set(APP_TIMEZONE);
}

define('APP_DEBUG', true); // Canlıya alırken MUTLAKA false yapın.

error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', APP_DEBUG ? '1' : '0');


/* =====================================================================
 *  HIZ SINIRI AYARLARI  [istek adedi, pencere saniyesi]
 * ---------------------------------------------------------------------
 *  Sayılar keyfi değil, ÖLÇÜLEN gerçek kullanıma göre seçildi:
 *
 *  CHECK  (40/dk): Canlı kontrol 500 ms geciktirmelidir; formu dolduran
 *    gerçek bir kullanıcı e-posta + kullanıcı adı için toplam 10-15
 *    istek atar. 40, meşru kullanıcıya ferah gelir; e-posta listesi
 *    tarayan birine dar gelir.
 *
 *  SUBMIT (5/dk): Bir insan dakikada 5 kez kayıt olmaz. Bu sınır asıl
 *    olarak İŞLEMCİYİ korur: her başarılı gönderim bir password_hash()
 *    çağırır ve bu makinede ÖLÇÜLDÜ — bcrypt cost 10 ≈ 116 ms CPU,
 *    gönderimin toplam süresinin (~128 ms) yaklaşık %90'ı.
 * ================================================================== */
define('RATE_LIMIT_CHECK',  [40, 60]);
define('RATE_LIMIT_SUBMIT', [5,  60]);

try {
    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');

    echo APP_DEBUG
        ? 'Veritabanı bağlantı hatası: ' . $e->getMessage()
          . "\n\nVeritabanını kurmayı unuttunuz mu?  mysql -u root -p < cy_validation.sql"
        : 'Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.';

    exit;
}
