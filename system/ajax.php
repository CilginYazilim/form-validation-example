<?php
/**
 * =====================================================================
 *  AJAX UÇ NOKTASI (Endpoint)
 *  cilginyazilim.com – Form Doğrulama Örneği
 * ---------------------------------------------------------------------
 *    action=check_username → CANLI benzersizlik kontrolü (yazarken)
 *    action=check_email    → CANLI benzersizlik kontrolü (yazarken)
 *    action=submit         → Formu SUNUCUDA TAM doğrula ve kaydet
 *
 *  HER UÇ NOKTA ÜÇ KAPIDAN GEÇER:
 *    1. Yöntem POST mu?          → değilse 405
 *    2. CSRF token geçerli mi?   → değilse 403
 *    3. Hız sınırı aşıldı mı?    → aşıldıysa 429
 *  Sıra önemlidir: hız sınırı sayacını CSRF'den SONRA artırıyoruz ki
 *  token'ı olmayan gürültü, meşru kullanıcının kotasını yemesin.
 * =====================================================================
 */

declare(strict_types=1);

/* Bkz. system/config.php – dosyaların doğrudan çağrılmasını engelleyen
 * işaret. ajax.php dışarıya AÇIK olan tek dosyadır; işareti o basar. */
define('CY_APP', true);

require __DIR__ . '/config.php';
require __DIR__ . '/rules.php';
require __DIR__ . '/function.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Yalnızca POST istekleri kabul edilir.', 405);
}

$action = isset($_POST['action']) ? strtolower(trim((string) $_POST['action'])) : '';

try {
    switch ($action) {
        case 'check_username':
            handle_check_username($db);
            break;

        case 'check_email':
            handle_check_email($db);
            break;

        case 'submit':
            handle_submit($db);
            break;

        default:
            json_error('Geçersiz işlem.', 400);
    }
} catch (PDOException $e) {
    error_log('[VALIDATION] Veritabani hatasi: ' . $e->getMessage());
    json_error(APP_DEBUG ? 'Veritabanı hatası: ' . $e->getMessage() : 'Beklenmeyen bir veritabanı hatası oluştu.', 500);
} catch (Throwable $e) {
    error_log('[VALIDATION] Hata: ' . $e->getMessage());
    json_error(APP_DEBUG ? 'Hata: ' . $e->getMessage() : 'Beklenmeyen bir hata oluştu.', 500);
}


/* =====================================================================
 *  1) CANLI BENZERSİZLİK KONTROLLERİ
 * =====================================================================
 *  ÖNEMLİ: Bu iki uç nokta yalnızca KULLANICI DENEYİMİ İÇİNDİR.
 *  "Müsait" demeleri, kaydın kesin başarılı olacağını GARANTİ ETMEZ —
 *  iki kullanıcı AYNI ANDA aynı adı kontrol edip ikisi de "müsait"
 *  görebilir (klasik "TOCTOU": time-of-check / time-of-use açığı).
 *  Bu yüzden handle_submit() BENZERSİZLİĞİ YENİDEN kontrol eder VE
 *  veritabanındaki UNIQUE indeks son sözü söyler — bu iki katman
 *  olmadan, eşzamanlı istekler mükerrer kayıt oluşturabilirdi.
 *
 *  SAYIM (enumeration) AÇIĞI — BİLİNEN VE KABUL EDİLEN ÖDÜNLEŞİM:
 *  Bu uç noktalar tanım gereği "bu e-posta kayıtlı mı?" sorusunu
 *  yanıtlar. Yani elinde e-posta listesi olan biri, hangi adreslerin
 *  bu sitede kayıtlı olduğunu öğrenebilir. Bu açığı KAPATMANIN tek
 *  gerçek yolu özelliği kaldırmaktır — ki bu projenin anlattığı şey
 *  tam da o özelliktir.
 *
 *  ZAMANLAMA (timing) TARAFI ÖLÇÜLDÜ, SORUN ÇIKMADI: kayıtlı ve
 *  kayıtsız adres için 150'şer dönüşümlü istekte medyan fark 0,065 ms,
 *  ölçümün kendi gürültüsü 0,431 ms. Yani zamanlamadan bilgi sızmıyor —
 *  zaten sızmasına gerek yok, cevap düz metin olarak veriliyor.
 *
 *  ALINAN ÖNLEM: hız sınırı (bkz. rate_limit). Sayımı imkânsız değil
 *  PAHALI kılar. Gerekçesi ve sınırları README'de yazılıdır.
 * ------------------------------------------------------------------ */
function handle_check_username(PDO $db): void
{
    require_csrf();
    rate_limit('check', ...RATE_LIMIT_CHECK);

    [$username, $error] = validate_username($_POST['username'] ?? '');

    if ($error !== null) {
        json_response(['success' => true, 'available' => false, 'reason' => $error]);
    }

    $exists = username_exists($db, $username);

    json_response([
        'success'   => true,
        'available' => !$exists,
        'reason'    => $exists ? 'Bu kullanıcı adı zaten alınmış.' : null,
    ]);
}

function handle_check_email(PDO $db): void
{
    require_csrf();
    /* AYNI KOVA (bucket) kullanılır: check_username ve check_email tek
     * bir kotayı paylaşır. Ayrı kova verseydik sayım yapan biri iki
     * kotayı da doldurup iki kat istek atardı; kullanıcı açısındansa
     * iki alan aynı formda ve aynı tempoda doldurulur. */
    rate_limit('check', ...RATE_LIMIT_CHECK);

    [$email, $error] = validate_email($_POST['email'] ?? '');

    if ($error !== null) {
        json_response(['success' => true, 'available' => false, 'reason' => $error]);
    }

    $exists = email_exists($db, $email);

    json_response([
        'success'   => true,
        'available' => !$exists,
        'reason'    => $exists ? 'Bu e-posta adresi zaten kayıtlı.' : null,
    ]);
}


/* =====================================================================
 *  2) FORM GÖNDERİMİ — SUNUCUDAKİ TAM DOĞRULAMA
 * =====================================================================
 *  Her alan BAĞIMSIZ doğrulanır ve TÜM hatalar TEK SEFERDE toplanır.
 *  Kullanıcıyı "bir hatayı düzelt, sonrakini gör" döngüsüne sokmak
 *  kötü bir deneyimdir — özellikle uzun bir formda.
 * ------------------------------------------------------------------ */
function handle_submit(PDO $db): void
{
    require_csrf();
    /* Sınır, DOĞRULAMADAN ÖNCE uygulanır. Sonraya bıraksaydık, geçersiz
     * form gönderen bir bot sınıra hiç takılmadan sunucuyu meşgul
     * ederdi — asıl korumak istediğimiz şey, isteğin YAPTIĞI iştir. */
    rate_limit('submit', ...RATE_LIMIT_SUBMIT);

    $errors = [];

    [$fullName, $nameError]         = validate_full_name($_POST['full_name'] ?? '');
    [$email, $emailError]            = validate_email($_POST['email'] ?? '');
    [$username, $usernameError]       = validate_username($_POST['username'] ?? '');
    [$phone, $phoneError]              = validate_phone($_POST['phone'] ?? '');
    [$password, $passwordError]         = validate_password($_POST['password'] ?? '');
    [, $confirmError]                    = validate_password_confirm($_POST['password'] ?? '', $_POST['password_confirm'] ?? '');
    [$birthDate, $birthDateError]         = validate_birth_date($_POST['birth_date'] ?? '');
    [$message, $messageError]              = validate_message($_POST['message'] ?? '');
    [, $termsError]                         = validate_terms($_POST['terms'] ?? null);

    foreach ([
        'full_name'        => $nameError,
        'email'            => $emailError,
        'username'         => $usernameError,
        'phone'            => $phoneError,
        'password'         => $passwordError,
        'password_confirm' => $confirmError,
        'birth_date'       => $birthDateError,
        'message'          => $messageError,
        'terms'            => $termsError,
    ] as $field => $errorMessage) {
        if ($errorMessage !== null) {
            $errors[$field] = $errorMessage;
        }
    }

    /* --- BENZERSİZLİĞİ SON KEZ DOĞRULA -----------------------------
     * Canlı kontrol (check_email/check_username) atlatılmış olabilir
     * (JavaScript kapalı, doğrudan istek) ya da iki kullanıcı aynı
     * anda aynı değeri denemiş olabilir. Burada YENİDEN sorulur. */
    if ($emailError === null && email_exists($db, $email)) {
        $errors['email'] = 'Bu e-posta adresi zaten kayıtlı.';
    }

    if ($usernameError === null && username_exists($db, $username)) {
        $errors['username'] = 'Bu kullanıcı adı zaten alınmış.';
    }

    if ($errors !== []) {
        json_error('Lütfen formdaki hataları düzeltin.', 422, ['errors' => $errors]);
    }

    /* --- KAYDET ------------------------------------------------------
     * password_hash(): Şifreyi ASLA düz metin saklamayın. PASSWORD_DEFAULT,
     * PHP'nin o an önerdiği en güncel algoritmayı kullanır (şu an
     * bcrypt); ileride PHP daha güçlü bir algoritmaya geçerse, kodu
     * değiştirmeden otomatik faydalanırsınız. */
    try {
        $stmt = $db->prepare(
            'INSERT INTO submissions (full_name, email, username, phone, password_hash, birth_date, message)
             VALUES (:full_name, :email, :username, :phone, :password_hash, :birth_date, :message)'
        );

        $stmt->execute([
            ':full_name'      => $fullName,
            ':email'          => $email,
            ':username'       => $username,
            ':phone'          => $phone,
            ':password_hash'  => password_hash($password, PASSWORD_DEFAULT),
            ':birth_date'     => $birthDate,
            ':message'        => $message,
        ]);
    } catch (PDOException $e) {
        /* NADİR YARIŞ DURUMU: Canlı kontrol + son kontrol ikisi de
         * "müsait" dese bile, aynı anda gelen İKİNCİ bir istek araya
         * girip ilk kaydı ATABİLİR. Bu durumda UNIQUE indeks
         * (uniq_submissions_email / uniq_submissions_username) hatayı
         * MySQL seviyesinde yakalar (SQLSTATE 23000). Kullanıcıya
         * anlaşılır bir mesajla geri döneriz; ham SQL hatası SIZDIRILMAZ. */
        if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), '1062')) {
            json_error('Bu e-posta veya kullanıcı adı, siz formu doldururken başka biri tarafından alındı. Lütfen tekrar deneyin.', 409);
        }

        throw $e;
    }

    json_success('Kayıt başarıyla oluşturuldu.', ['id' => (int) $db->lastInsertId()]);
}
