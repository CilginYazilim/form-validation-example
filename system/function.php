<?php
/**
 * =====================================================================
 *  YARDIMCI FONKSİYONLAR
 *  cilginyazilim.com – Form Doğrulama Örneği
 * ---------------------------------------------------------------------
 *  BU PROJENİN ALTIN KURALI:
 *  Her doğrulama kuralı BURADA, SUNUCUDA uygulanır. assets/js/validation.js
 *  AYNI kuralları TEKRAR uygular ama SADECE kullanıcı deneyimi içindir —
 *  anlık geri bildirim vermek, formu göndermeden önce hatayı göstermek
 *  için. Kötü niyetli biri JavaScript'i hiç çalıştırmadan doğrudan
 *  system/ajax.php'ye istek gönderebilir; bu yüzden İKİ TARAF DA aynı
 *  kuralları uygular ama yalnızca BURADAKİ (sunucu) doğrulama GÜVENLİK
 *  SINIRIDIR.
 *
 *  İki tarafın AYNI kuralı uygulaması artık iyi niyete bırakılmamıştır:
 *  sınırlar, desenler ve mesajlar system/rules.php'de TEK KEZ tanımlanır
 *  ve her iki taraf da oradan okur. Bkz. system/rules.php başlığı.
 * =====================================================================
 */

declare(strict_types=1);

/* Bu dosya tek başına çağrılmak üzere yazılmamıştır; index.php ve
 * system/ajax.php onu include eder. .htaccess'i okumayan bir sunucuda
 * (nginx gibi) TEK savunma bu kontroldür. Bkz. system/.htaccess */
if (!defined('CY_APP')) {
    http_response_code(403);
    exit;
}


/* =====================================================================
 *  BÖLÜM 1 – ÇIKTI VE YANIT
 * ================================================================== */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_success(string $description, array $extra = []): void
{
    json_response(array_merge(['success' => true, 'type' => 'success', 'description' => $description], $extra));
}

function json_error(string $description, int $status = 400, array $extra = []): void
{
    json_response(array_merge(['success' => false, 'type' => 'danger', 'description' => $description], $extra), $status);
}


/* =====================================================================
 *  BÖLÜM 2 – CSRF KORUMASI
 * ================================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        /* Oturum kimliğini token'ı BASARKEN yeniliyoruz. Bu projede
         * giriş/çıkış yoktur, yani "yetki yükselmesi anı" da yoktur;
         * asıl sabitleme (fixation) savunması config.php'deki
         * session.use_strict_mode'dur. Yine de yeni bir oturumun
         * kimliği, ilk kez ANLAMLI veri (CSRF token'ı) taşımaya
         * başladığı anda değişsin: saldırganın önceden bildiği bir
         * kimliğe bağlı boş bir oturum, o veriyi hiç görmemiş olur. */
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!is_string($token) || $token === ''
        || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)) {

        /* ÖLÇÜLEN SORUN: burada eskiden 419 ("Page Expired", Laravel'in
         * icadı) dönülüyordu. Bu kurulumdaki Apache 419'u TANIMIYOR ve
         * yanıtı sessizce 500'e çeviriyordu — yani "oturumun düşmüş"
         * hatası, istemciye "sunucu çöktü" diye ulaşıyordu. 403,
         * standarttır ve anlamı doğrudur: istek anlaşıldı, ama
         * yetkilendirilmedi. */
        json_error('Oturum doğrulaması başarısız. Lütfen sayfayı yenileyin.', 403);
    }
}


/* =====================================================================
 *  BÖLÜM 3 – HIZ SINIRI (spam ve sayım koruması)
 * ---------------------------------------------------------------------
 *  ÖLÇÜLEN SORUN: Hiçbir sınır yoktu.
 *    · 60 ardışık check_email isteği → 60 kez HTTP 200. Yani uç nokta,
 *      elindeki e-posta listesini "bu sitede kayıtlı mı?" diye tek tek
 *      sorgulamak için hazır bir SAYIM (enumeration) aracıydı.
 *    · 15 ardışık submit → 15 kayıt oluştu. Her kayıt password_hash()
 *      çağırır ve bu makinede ÖLÇÜLDÜ: bcrypt cost 10 ≈ 116 ms CPU.
 *      Yani kimliği doğrulanmamış bir istekle 116 ms işlemci zamanı
 *      yaktırılabiliyordu — ucuz bir hizmet dışı bırakma kaldıracı.
 *
 *  NEDEN VERİTABANI DEĞİL DOSYA? Sınırın amacı yükü azaltmaktır; her
 *  istekte bir INSERT atmak, korumaya çalıştığınız yükün ta kendisini
 *  üretir. Bu kurulumda APCu yok (ölçüldü), bu yüzden geçici klasörde
 *  flock() ile kilitlenen küçük bir sayaç dosyası kullanılıyor.
 *
 *  NEYİ KAPSAMAZ (dürüst sınır): Anahtar IP adresidir. Dağıtık bir
 *  saldırgan (bot ağı) her istekte farklı IP kullanarak bu sınırı
 *  aşar. Hız sınırı sayımı İMKÂNSIZ kılmaz, PAHALI kılar. Sayımı
 *  tümüyle bitirmenin tek yolu canlı kontrolü kaldırmaktır — bu
 *  ödünleşimin gerekçesi README'de yazılıdır.
 * ================================================================== */

function rate_limit_dir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cy_validation_rate';

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir;
}

/**
 * Kaydı isteyen tarafın kimliği. REMOTE_ADDR kullanılır; X-Forwarded-For
 * BİLEREK okunmaz — bu başlık istemci tarafından uydurulabilir ve ters
 * vekil (reverse proxy) arkasında OLMAYAN bir kurulumda onu güvenmek,
 * hız sınırını tek satırlık bir başlıkla kapatılabilir hâle getirir.
 * Vekil arkasında çalışacaksanız burayı BİLEREK değiştirin.
 */
function client_fingerprint(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'bilinmiyor');
}

/**
 * Kayan pencere (sliding window) sayacı. Pencere içindeki istek
 * zaman damgaları saklanır; sınır aşılırsa HTTP 429 ile durulur.
 *
 * Sabit pencere (her dakikanın başında sıfırlanan sayaç) daha ucuzdur
 * ama pencere sınırında iki katı isteğe izin verir (59. saniyede 40,
 * 61. saniyede 40 daha). Kayan pencere bu boşluğu bırakmaz.
 */
function rate_limit(string $bucket, int $limit, int $windowSeconds): void
{
    $file = rate_limit_dir() . DIRECTORY_SEPARATOR
          . sha1($bucket . '|' . client_fingerprint()) . '.json';

    $handle = @fopen($file, 'c+');

    if ($handle === false) {
        /* Sayaç yazılamıyorsa (disk dolu, izin yok) isteği REDDETMİYORUZ:
         * bu bir demo formudur, koruma katmanının arızası yüzünden
         * meşru kullanıcıyı kapıda bırakmak orantısız olur. Ama sessiz
         * de kalmıyoruz — sınırın UYGULANAMADIĞI log'a düşer. */
        error_log('[VALIDATION] Hiz siniri sayaci yazilamadi: ' . $file);

        return;
    }

    flock($handle, LOCK_EX);

    $now      = microtime(true);
    $contents = stream_get_contents($handle);
    $hits     = json_decode((string) $contents, true);
    $hits     = is_array($hits) ? $hits : [];

    // Pencerenin dışında kalan damgaları at.
    $hits = array_values(array_filter($hits, static fn($t): bool => is_numeric($t) && ($now - (float) $t) < $windowSeconds));

    if (count($hits) >= $limit) {
        $retryAfter = (int) ceil($windowSeconds - ($now - (float) $hits[0]));

        flock($handle, LOCK_UN);
        fclose($handle);

        if (!headers_sent()) {
            header('Retry-After: ' . max(1, $retryAfter));
        }

        json_error(
            'Çok fazla istek gönderdiniz. Lütfen ' . max(1, $retryAfter) . ' saniye sonra tekrar deneyin.',
            429,
            ['retry_after' => max(1, $retryAfter)]
        );
    }

    $hits[] = $now;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($hits));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}


/* =====================================================================
 *  BÖLÜM 4 – DOĞRULAYICILAR
 * -----------------------------------------------------------------
 *  Her fonksiyon [temizlenmiş değer, hata|null] döndürür. Bu ortak
 *  imza, ajax.php'de tüm alanları AYNI döngü biçimiyle toplamayı
 *  sağlar (bkz. handle_submit()).
 *
 *  Sınırlar ve mesajlar system/rules.php'den gelir; buradaki kod
 *  yalnızca alana ÖZGÜ yordamı (normalleştirme, biçim kontrolü)
 *  içerir. Bir sayı görürseniz yanlış yerdedir.
 * ================================================================== */

/** @return array{0:string,1:?string} */
function validate_full_name(?string $value): array
{
    /* Boşluk sıkıştırma: "Ali    Veli" → "Ali Veli". Kullanıcının
     * yanlışlıkla bastığı fazla boşluk bir HATA değildir; veriyi
     * normalleştirip kabul etmek doğru davranıştır. İstemci tarafı da
     * artık AYNI normalleştirmeyi yapar — eskiden yapmıyordu ve iki
     * taraf farklı uzunluk sayıyordu. */
    $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

    return [$value, rule_check('full_name', $value)];
}

/** @return array{0:string,1:?string} */
function validate_email(?string $value): array
{
    // Küçük harfe çevir: "Ayse@Ornek.com" ile "ayse@ornek.com" AYNI
    // adrestir; normalleştirmezsek UNIQUE indeks ikisini ayrı sanar.
    $value = mb_strtolower(trim((string) $value), 'UTF-8');

    $error = rule_check('email', $value);

    if ($error !== null) {
        return [$value, $error];
    }

    /* Biçim kontrolü YORDAMSALDIR ve paylaşılamaz: PHP'nin
     * FILTER_VALIDATE_EMAIL'i, istemcideki basit desenden çok daha
     * titizdir. İstemcideki desen bilerek DAHA GEVŞEKTİR — gevşek
     * istemci "sunucunun kabul edeceği bir şeyi reddetme" hatasına
     * düşmez; ters yön (sunucunun reddedip istemcinin kabul etmesi)
     * yalnızca fazladan bir sunucu turu demektir. */
    if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        return [$value, rule_message('email', 'format')];
    }

    return [$value, null];
}

/** @return array{0:string,1:?string} */
function validate_username(?string $value): array
{
    $value = trim((string) $value);

    /* ÖLÇÜLEN AYRIŞMA: burada eskiden strlen() (BAYT sayar) vardı,
     * istemcide .length (UTF-16 kod birimi). "şş" girildiğinde sunucu
     * 4 sayıp desen hatası, istemci 2 sayıp uzunluk hatası veriyordu —
     * aynı girdiye iki farklı gerekçe. Artık iki taraf da KOD NOKTASI
     * sayar (mb_strlen / [...value].length) ve aynı mesajı üretir.
     * Desen zaten ASCII'ye kısıtladığı için kabul edilen değerlerde
     * bayt ile kod noktası zaten aynıdır; fark yalnızca REDDEDİLEN
     * girdinin GEREKÇESİNDEYDİ — ve kullanıcının okuduğu şey odur. */
    return [$value, rule_check('username', $value)];
}

/** @return array{0:?string,1:?string} */
function validate_phone(?string $value): array
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return [null, null];
    }

    // Rakam olmayan her şeyi at: "0555 123 45 67" → "05551234567"
    $digits = preg_replace('/\D/', '', $raw) ?? '';

    // +90 ile başlanmışsa başındaki "90"ı at, "0" ile devam etsin.
    if (str_starts_with($digits, '90') && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    }

    // Desen, rakamlar AYIKLANDIKTAN SONRA uygulanır.
    if (rule_check('phone', $digits) !== null) {
        return [$raw, rule_message('phone', 'pattern')];
    }

    // Standart biçimde sakla: "0555 123 45 67"
    $formatted = substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' '
               . substr($digits, 7, 2) . ' ' . substr($digits, 9, 2);

    return [$formatted, null];
}

/**
 * @return array{0:string,1:?string} Dönen değer HAM şifredir (hash'lemek çağıranın işidir).
 */
function validate_password(?string $value): array
{
    $value = (string) $value;
    $error = rule_check('password', $value);

    // Hata varsa şifreyi GERİ DÖNDÜRMÜYORUZ: hatalı da olsa bir parolayı
    // gereksiz yere daha fazla değişkende dolaştırmanın faydası yok.
    return [$error === null ? $value : '', $error];
}

/** @return array{0:null,1:?string} */
function validate_password_confirm(?string $password, ?string $confirm): array
{
    if ((string) $confirm === '') {
        return [null, rule_message('password_confirm', 'required')];
    }

    // hash_equals() burada GEREKLİ DEĞİLDİR (zamanlama saldırısı riski
    // yok, ikisi de kullanıcının kendi girdisi).
    if ($password !== $confirm) {
        return [null, rule_message('password_confirm', 'match')];
    }

    return [null, null];
}

/** @return array{0:?string,1:?string} */
function validate_birth_date(?string $value): array
{
    $value = trim((string) $value);

    if ($value === '') {
        return [null, null];
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if ($date === false || $date->format('Y-m-d') !== $value) {
        return [null, rule_message('birth_date', 'format')];
    }

    $today = new DateTimeImmutable('today');

    if ($date > $today) {
        return [null, rule_message('birth_date', 'future')];
    }

    // DateInterval, gün/ay/yıl kesirlerini doğru hesaplar ("29 Şubat"
    // gibi artık yıl kenar durumları dahil).
    if ($today->diff($date)->y < rule('birth_date')['min_age']) {
        return [$value, rule_message('birth_date', 'age')];
    }

    return [$value, null];
}

/** @return array{0:?string,1:?string} */
function validate_message(?string $value): array
{
    /* trim(): istemci de artık AYNI şeyi yapar. Eskiden yapmıyordu ve
     * "495 harf + 20 boşluk" girdisini istemci 515 sayıp REDDEDERKEN
     * sunucu kırpıp 495 sayıp KABUL EDİYORDU (ölçüldü). */
    $value = trim((string) $value);

    if ($value === '') {
        return [null, null];
    }

    return [$value, rule_check('message', $value)];
}

/** @return array{0:bool,1:?string} */
function validate_terms(mixed $value): array
{
    $accepted = in_array($value, ['1', 'on', 'true', true, 1], true);

    if (!$accepted) {
        return [false, rule_message('terms', 'required')];
    }

    return [true, null];
}


/* =====================================================================
 *  BÖLÜM 5 – BENZERSİZLİK KONTROLÜ (CANLI AJAX kontrolü + son doğrulama)
 * ================================================================== */

function email_exists(PDO $db, string $email): bool
{
    $stmt = $db->prepare('SELECT 1 FROM submissions WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);

    return $stmt->fetchColumn() !== false;
}

function username_exists(PDO $db, string $username): bool
{
    $stmt = $db->prepare('SELECT 1 FROM submissions WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);

    return $stmt->fetchColumn() !== false;
}


