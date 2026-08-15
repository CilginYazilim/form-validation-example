<?php
/**
 * =====================================================================
 *  DOĞRULAMA KURALLARI – TEK KAYNAK (single source of truth)
 *  cilginyazilim.com – Form Doğrulama Örneği
 * ---------------------------------------------------------------------
 *  NEDEN BÖYLE BİR DOSYA VAR?
 *
 *  Bu proje aynı kuralı İKİ AYRI DİLDE uygulamak zorundadır: sunucuda
 *  PHP (güvenlik sınırı), tarayıcıda JavaScript (anında geri bildirim).
 *  Kuralları iki yere elle yazmak, "iki liste birbirinden sessizce
 *  ayrışır" sorununu doğurur. Bu ayrışma teorik değildir; bu depoda
 *  ÖLÇÜLDÜ (istemcinin gerçek formu sürülerek):
 *
 *    e-posta 191 karakter   → sunucu REDDETTİ, istemci KABUL ETTİ
 *    şifre   73  karakter   → sunucu REDDETTİ, istemci KABUL ETTİ
 *    web adresi 268 karakter→ sunucu REDDETTİ, istemci KABUL ETTİ
 *    mesaj 495 harf+20 boşluk → sunucu KABUL ETTİ, istemci REDDETTİ
 *    ad soyad 60 astral harf  → sunucu KABUL ETTİ, istemci REDDETTİ
 *    kullanıcı adı "şş"       → iki taraf FARKLI hata mesajı verdi
 *
 *  Bunlar "unutulmuş üç satır" değildir; tek bir kuralın iki kopyası
 *  olduğu her yerde ZAMANLA kaçınılmaz olarak oluşurlar. Yorumla
 *  "bunlar aynı olmalı" demek (eski hâlde her JS doğrulayıcısının
 *  üstünde 'bkz. validate_x()' yazıyordu) ayrışmayı ENGELLEMEDİ —
 *  yorum, kod değiştiğinde derlenmez, test edilmez, kırılmaz.
 *
 *  ÇÖZÜM: SINIRLARI VERİ HÂLİNE GETİRMEK.
 *  Sayısal sınırlar, desenler ve hata mesajları BURADA bir kez
 *  tanımlanır. PHP doğrulayıcıları bu diziyi okur; index.php aynı
 *  diziyi JSON olarak sayfaya gömer ve validation.js de AYNI değerleri
 *  okur. Artık "100" sayısı kodda TEK yerde vardır — iki tarafın
 *  ayrışması, bir sayıyı iki yerde değiştirmeyi unutmakla değil,
 *  ancak bu dosyayı bilerek bozmakla mümkündür.
 *
 *  BU ÇÖZÜM NEYİ KAPSAMAZ (dürüst sınır):
 *  Yalnızca VERİYE dönüştürülebilen kısım paylaşılır. Telefon
 *  normalleştirmesi, yaş hesabı, URL ayrıştırma gibi YORDAMSAL
 *  (procedural) kısımlar her dilde ayrı yazılmak zorundadır; PHP'nin
 *  filter_var'ı ile tarayıcının URL sınıfı aynı kod değildir. Bu
 *  yüzden paylaşılan spesifikasyon "sınırlar + desenler + mesajlar"
 *  ile sınırlıdır ve geri kalan fark, README'de açıkça yazılıdır.
 *
 *  DESEN (pattern) KISITI:
 *  Buradaki 'pattern' değerleri HEM PCRE (PHP) HEM DE JavaScript
 *  RegExp tarafından AYNI anlamda yorumlanabilen alt kümeyle
 *  sınırlıdır: \p{L}, \p{M}, \d, \s, karakter sınıfları, çapalar.
 *  PHP'ye özgü (lookbehind sınırları, \A \z, /x kipi) hiçbir şey
 *  KULLANILMAZ — kullanılsaydı desen tarayıcıda sessizce farklı
 *  davranır ve çözmeye çalıştığımız sorunu geri getirirdi.
 * =====================================================================
 */

declare(strict_types=1);

if (!defined('CY_APP')) {
    http_response_code(403);
    exit;
}

/**
 * Alan bazlı kural tanımları.
 *
 *   required : Alan zorunlu mu (boş bırakılabilir mi)
 *   min/max  : KOD NOKTASI (code point) cinsinden uzunluk sınırı.
 *              PHP mb_strlen(), JS [...value].length ile sayar —
 *              ikisi de kod noktası sayar. JS'in .length'i UTF-16
 *              KOD BİRİMİ sayar ve emoji/astral harfleri 2 sayardı;
 *              ölçümde ad soyad ayrışmasının sebebi tam olarak buydu.
 *   pattern  : İki dilde de aynı anlama gelen düzenli ifade (bkz. üst not)
 *   flags    : Desen bayrakları ('u' = Unicode)
 *   messages : {min} / {max} / {age} yer tutucuları çalışma anında dolar
 */
function validation_rules(): array
{
    static $rules = null;

    if ($rules !== null) {
        return $rules;
    }

    return $rules = [
        'full_name' => [
            'required'  => true,
            'min'       => 2,
            'max'       => 100,
            // \p{L} harf, \p{M} aksan işareti: "Ayşe", "Gülşen", "O'Brien",
            // "Jean-Luc" geçer; rakam, <script>, emoji geçmez.
            'pattern'   => "^[\\p{L}\\p{M}\\s.'-]+$",
            'flags'     => 'u',
            'messages'  => [
                'required' => 'Ad soyad boş bırakılamaz.',
                'length'   => 'Ad soyad {min}-{max} karakter arasında olmalıdır.',
                'pattern'  => 'Ad soyad yalnızca harf ve boşluk içerebilir.',
            ],
        ],

        'email' => [
            'required'  => true,
            'min'       => 3,
            // 190: utf8mb4 + InnoDB'de bir VARCHAR sütuna UNIQUE indeks
            // koyabilmenin pratik sınırı (191 karakter × 4 bayt ≈ 767 bayt,
            // eski InnoDB indeks sınırı). Şema ile doğrulama BİLEREK
            // aynı sayıya bakar; sütun 190 iken doğrulama 255'e izin
            // verse, veritabanı kaydı sessizce kırpar veya hata verirdi.
            'max'       => 190,
            'messages'  => [
                'required' => 'E-posta boş bırakılamaz.',
                'length'   => 'E-posta en fazla {max} karakter olabilir.',
                'format'   => 'Geçerli bir e-posta adresi giriniz.',
            ],
        ],

        'username' => [
            'required'  => true,
            'min'       => 3,
            'max'       => 20,
            // Küçük harfle başlar. "1admin" gibi bir ad, sayısal ID
            // bekleyen bir uçta karışıklık yaratabilir.
            'pattern'   => '^[a-z][a-z0-9_]*$',
            'flags'     => '',
            'messages'  => [
                'required' => 'Kullanıcı adı boş bırakılamaz.',
                'length'   => 'Kullanıcı adı {min}-{max} karakter arasında olmalıdır.',
                'pattern'  => 'Kullanıcı adı küçük harfle başlamalı; yalnızca a-z, 0-9 ve alt çizgi (_) içerebilir.',
                'taken'    => 'Bu kullanıcı adı zaten alınmış.',
            ],
        ],

        'phone' => [
            'required'  => false,
            'max'       => 20,
            // Rakamlar ayıklandıktan SONRA uygulanır (bkz. validate_phone).
            'pattern'   => '^05\\d{9}$',
            'flags'     => '',
            'messages'  => [
                'pattern'  => 'Geçerli bir cep telefonu numarası giriniz (05XX XXX XX XX).',
            ],
        ],

        'password' => [
            'required'  => true,
            'min'       => 8,
            // 72: bcrypt'in SERT sınırı. password_hash() bu uzunluktan
            // sonrasını sessizce yok sayar; kullanıcıya söylemezseniz
            // "şifremi uzattım ama eskisi de çalışıyor" durumu doğar.
            'max'       => 72,
            // Büyük + küçük + rakam. Karmaşıklık yığını yerine uzunluğa
            // öncelik veren NIST önerisiyle makul bir orta yol.
            'pattern'   => '^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).+$',
            'flags'     => '',
            'messages'  => [
                'required' => 'Şifre boş bırakılamaz.',
                'length'   => 'Şifre en az {min} karakter olmalıdır.',
                'max'      => 'Şifre en fazla {max} karakter olabilir.',
                'pattern'  => 'Şifre en az bir büyük harf, bir küçük harf ve bir rakam içermelidir.',
            ],
        ],

        'password_confirm' => [
            'required'  => true,
            'messages'  => [
                'required' => 'Şifre tekrarı boş bırakılamaz.',
                'match'    => 'Şifreler eşleşmiyor.',
            ],
        ],

        'birth_date' => [
            'required'  => false,
            // Yaş sınırı ARTIK burada. Eskiden PHP'de MIN_AGE_YEARS
            // sabiti, JavaScript'te ise elle yazılmış "18" vardı;
            // sabiti 21 yapmak istemciyi sessizce 18'de bırakırdı.
            'min_age'   => 18,
            'messages'  => [
                'format'   => 'Geçerli bir tarih giriniz.',
                'future'   => 'Doğum tarihi gelecekte olamaz.',
                'age'      => 'Bu formu doldurmak için en az {age} yaşında olmalısınız.',
            ],
        ],

        'website' => [
            'required'  => false,
            'max'       => 255,
            'messages'  => [
                'length'   => 'Web adresi en fazla {max} karakter olabilir.',
                'format'   => 'Geçerli bir web adresi giriniz.',
                'host'     => 'Geçerli bir web adresi giriniz (örn. ornek.com).',
            ],
        ],

        'message' => [
            'required'  => false,
            'max'       => 500,
            'messages'  => [
                'length'   => 'Mesaj en fazla {max} karakter olabilir.',
            ],
        ],

        'terms' => [
            'required'  => true,
            'messages'  => [
                'required' => 'Devam etmek için sözleşmeyi onaylamalısınız.',
            ],
        ],
    ];
}

/**
 * Tek bir alanın kural tanımını döndürür.
 */
function rule(string $field): array
{
    $rules = validation_rules();

    if (!isset($rules[$field])) {
        throw new InvalidArgumentException("Tanımsız doğrulama alanı: {$field}");
    }

    return $rules[$field];
}

/**
 * Mesaj şablonundaki {min} / {max} / {age} yer tutucularını doldurur.
 * Yer tutucu kullanmanın sebebi: sınır değerini mesaja ELLE yazarsanız
 * (örn. "en fazla 190 karakter"), sınırı değiştirdiğinizde mesaj
 * yalan söylemeye başlar ve kimse fark etmez.
 */
function rule_message(string $field, string $key, string $fallback = 'Geçersiz değer.'): string
{
    $rule     = rule($field);
    $template = $rule['messages'][$key] ?? $fallback;

    return strtr($template, [
        '{min}' => (string) ($rule['min'] ?? ''),
        '{max}' => (string) ($rule['max'] ?? ''),
        '{age}' => (string) ($rule['min_age'] ?? ''),
    ]);
}

/**
 * Paylaşılan kuralların ORTAK kısmını uygular: zorunluluk, uzunluk, desen.
 * Alan başına özel mantık (e-posta biçimi, telefon normalleştirme, yaş
 * hesabı) çağıran fonksiyonda kalır — çünkü onlar veriye değil YORDAMA
 * bağlıdır ve iki dilde birebir aynı yazılamaz.
 *
 * @return ?string Hata mesajı ya da null
 */
function rule_check(string $field, string $value): ?string
{
    $rule = rule($field);

    if ($value === '') {
        return ($rule['required'] ?? false) ? rule_message($field, 'required') : null;
    }

    // mb_strlen: KOD NOKTASI sayar. JavaScript tarafı da [...value].length
    // ile kod noktası sayar; ikisi aynı sonucu verir.
    $length = mb_strlen($value, 'UTF-8');

    if (isset($rule['min']) && $length < $rule['min']) {
        return rule_message($field, 'length');
    }

    if (isset($rule['max']) && $length > $rule['max']) {
        // Şifrede alt ve üst sınırın mesajı farklıdır ("en az 8" /
        // "en fazla 72"); ayrı bir 'max' anahtarı varsa o kullanılır.
        return rule_message($field, isset($rule['messages']['max']) ? 'max' : 'length');
    }

    if (isset($rule['pattern']) && !preg_match('/' . $rule['pattern'] . '/' . ($rule['flags'] ?? ''), $value)) {
        return rule_message($field, 'pattern');
    }

    return null;
}

/**
 * Kuralların İSTEMCİYE gönderilecek hâli.
 *
 * NEDEN "messages" DE GÖNDERİLİYOR? Çünkü ayrışma yalnızca sayılarda
 * olmaz: aynı kuralın iki farklı cümleyle anlatılması da bir ayrışmadır
 * ve kullanıcı, sunucudan dönen mesajın istemcinin dediğinden başka bir
 * şey söylediğini görür.
 *
 * NEDEN BU BİLGİ SIZINTISI DEĞİLDİR? Doğrulama kuralları zaten GİZLİ
 * DEĞİLDİR — formu bir kez deneyerek hepsi öğrenilir. Gizli olan tek
 * şey (veritabanı içeriği, oturum verisi) burada yer almaz.
 */
function client_rules(): array
{
    $out = [];

    foreach (validation_rules() as $field => $rule) {
        $out[$field] = [
            'required' => $rule['required'] ?? false,
            'min'      => $rule['min'] ?? null,
            'max'      => $rule['max'] ?? null,
            'minAge'   => $rule['min_age'] ?? null,
            'pattern'  => $rule['pattern'] ?? null,
            'flags'    => $rule['flags'] ?? '',
            'messages' => array_map(
                static fn(string $key): string => rule_message($field, $key),
                array_combine(array_keys($rule['messages']), array_keys($rule['messages']))
            ),
        ];
    }

    return $out;
}
