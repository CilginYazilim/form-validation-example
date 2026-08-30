/* =====================================================================
 *  CANLI FORM DOĞRULAMA
 *  cilginyazilim.com – Form Doğrulama Örneği
 * ---------------------------------------------------------------------
 *  ALTIN KURAL (bu dosyanın her satırında geçerlidir):
 *  Buradaki HİÇBİR kontrol güvenlik önlemi değildir. Tarayıcı
 *  konsolundan `CyValidation` nesnesini silip formu doğrudan
 *  gönderebilirsiniz — sunucu (system/rules.php + function.php +
 *  ajax.php) YİNE DE her şeyi kendi başına doğrular. Buradaki kod
 *  SADECE "hatayı görmek için sayfanın yenilenmesini beklememek"
 *  için vardır.
 *
 *  BU DOSYADA SAYI ARAMAYIN.
 *  "En fazla 100 karakter", "en az 8", "72", "18 yaş" gibi hiçbir
 *  sınır burada YAZILI DEĞİLDİR. Hepsi sunucudaki system/rules.php'den
 *  gelir ve index.php tarafından init({ rules: … }) ile aktarılır.
 *  Sebebi ölçülmüş bir sorundur: eskiden sınırlar iki yerde elle
 *  yazılıydı ve sessizce ayrışmışlardı (e-postadaki 190 ve şifredeki 72
 *  karakter sınırı istemcide HİÇ YOKTU; sunucu reddediyor,
 *  istemci kabul ediyordu). Bir sayıyı iki yerde güncellemeyi
 *  unutmak insan hatasıdır; sayıyı tek yere koymak o hatayı
 *  imkânsız kılar.
 * ================================================================== */

/* global jQuery, bootstrap */

var CyValidation = (function ($) {
    'use strict';

    var config = { endpoint: 'system/ajax.php', csrfToken: '', rules: {} };

    var checkTimers = {};


    /* =================================================================
     *  PAYLAŞILAN KURALLARIN UYGULANMASI
     * -----------------------------------------------------------------
     *  ruleCheck(), sunucudaki rule_check() ile AYNI sırayı izler:
     *  zorunluluk → uzunluk → desen. Aynı sıra, aynı mesaj: kullanıcı
     *  aynı girdi için istemciden ve sunucudan aynı cümleyi görür.
     * ============================================================== */

    /**
     * KOD NOKTASI sayar — .length DEĞİL.
     *
     * JavaScript'in .length'i UTF-16 KOD BİRİMİ sayar: temel çok dilli
     * düzlemin dışındaki bir harf (matematiksel harfler, çoğu emoji)
     * 2 sayılır. PHP'nin mb_strlen'i ise kod noktası sayar. Ölçülen
     * ayrışma buydu: 60 astral harften oluşan bir ad soyad sunucuda
     * 60 (geçer), istemcide 120 (reddedilir) sayılıyordu. [...value]
     * dizisi kod noktalarına böler ve iki taraf aynı sayıyı bulur.
     */
    function codePointLength(value) {
        return Array.from(value).length;
    }

    function ruleOf(field) {
        return config.rules[field] || {};
    }

    /** Sunucudaki rule_check()'in birebir karşılığı. */
    function ruleCheck(field, value) {
        var rule = ruleOf(field);
        var messages = rule.messages || {};

        if (value === '') {
            return rule.required ? fail(messages.required) : ok();
        }

        var length = codePointLength(value);

        if (rule.min !== null && rule.min !== undefined && length < rule.min) {
            return fail(messages.length);
        }

        if (rule.max !== null && rule.max !== undefined && length > rule.max) {
            // Şifrede alt/üst sınırın mesajı farklıdır (bkz. rules.php).
            return fail(messages.max || messages.length);
        }

        if (rule.pattern && !new RegExp(rule.pattern, rule.flags || '').test(value)) {
            return fail(messages.pattern);
        }

        return ok();
    }


    /* =================================================================
     *  ALAN BAZLI DOĞRULAYICILAR
     * -----------------------------------------------------------------
     *  Her fonksiyon { valid, message } döndürür ve yalnızca alana
     *  ÖZGÜ YORDAMI içerir (normalleştirme, tarih hesabı, URL
     *  ayrıştırma). Sınırlar ve mesajlar ruleCheck() üzerinden gelir.
     * ============================================================== */

    var validators = {
        // bkz. function.php validate_full_name()
        full_name: function (value) {
            // Sunucu da AYNI normalleştirmeyi yapar: "Ali    Veli" →
            // "Ali Veli". Eskiden istemci bunu yapmıyordu ve iki taraf
            // farklı uzunluk sayıyordu.
            value = (value || '').replace(/\s+/gu, ' ').trim();

            return ruleCheck('full_name', value);
        },

        // bkz. function.php validate_email() — benzersizlik AYRICA
        // AJAX ile kontrol edilir (bkz. scheduleAvailabilityCheck).
        email: function (value) {
            value = (value || '').trim().toLowerCase();

            var result = ruleCheck('email', value);
            if (!result.valid) { return result; }

            /* Biçim deseni BİLEREK sunucudakinden gevşektir: kesin
             * doğrulama PHP'nin filter_var(FILTER_VALIDATE_EMAIL)'idir
             * ve onun tam karşılığı tarayıcıda yoktur. Gevşek istemci,
             * sunucunun KABUL EDECEĞİ bir adresi reddetme hatasına
             * düşmez; ters yön yalnızca fazladan bir sunucu turudur.
             * Bu fark bilinçlidir ve README'de yazılıdır. */
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return fail(ruleOf('email').messages.format);
            }

            return ok();
        },

        // bkz. function.php validate_username()
        username: function (value) {
            return ruleCheck('username', (value || '').trim());
        },

        // bkz. function.php validate_phone() — opsiyonel alan.
        phone: function (value) {
            value = (value || '').trim();

            if (value === '') { return ok(); }

            var digits = value.replace(/\D/g, '');

            if (digits.length === 12 && digits.indexOf('90') === 0) {
                digits = '0' + digits.slice(2);
            }

            // Desen, rakamlar AYIKLANDIKTAN SONRA uygulanır — sunucudaki
            // sırayla aynı.
            return ruleCheck('phone', digits);
        },

        // bkz. function.php validate_password()
        password: function (value) {
            return ruleCheck('password', value || '');
        },

        password_confirm: function (value) {
            var rule = ruleOf('password_confirm');

            if ((value || '') === '') { return fail(rule.messages.required); }
            if (value !== $('#password').val()) { return fail(rule.messages.match); }

            return ok();
        },

        // bkz. function.php validate_birth_date() — opsiyonel alan.
        birth_date: function (value) {
            value = (value || '').trim();

            if (value === '') { return ok(); }

            var rule = ruleOf('birth_date');
            var age  = calculateAge(value);

            if (age === null) { return fail(rule.messages.format); }
            if (age < 0) { return fail(rule.messages.future); }
            // Yaş sınırı da sunucudan gelir: rules.php'de min_age'i 21
            // yaparsanız BU satır da 21'e döner, elle düzeltme gerekmez.
            if (age < rule.minAge) { return fail(rule.messages.age); }

            return ok();
        },

        message: function (value) {
            // trim(): sunucu da trim eder. Eskiden etmiyorduk ve
            // "495 harf + 20 boşluk" girdisini istemci reddederken
            // sunucu kabul ediyordu (ölçüldü).
            return ruleCheck('message', (value || '').trim());
        },

        terms: function () {
            if (!$('#terms').is(':checked')) { return fail(ruleOf('terms').messages.required); }
            return ok();
        }
    };

    function ok() { return { valid: true, message: '' }; }
    function fail(message) { return { valid: false, message: message || 'Geçersiz değer.' }; }

    /**
     * 'YYYY-MM-DD' değerinden yaş hesaplar. Geçersiz tarih için null,
     * gelecekteki tarih için negatif sayı döner (fail mesajı buna göre seçilir).
     */
    function calculateAge(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        if (!match) { return null; }

        var date = new Date(value + 'T00:00:00');
        if (isNaN(date.getTime())) { return null; }

        var today = new Date();
        var age = today.getFullYear() - date.getFullYear();
        var monthDiff = today.getMonth() - date.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < date.getDate())) {
            age--;
        }

        return date > today ? -1 : age;
    }


    /* =================================================================
     *  ARAYÜZ YARDIMCILARI
     * ============================================================== */

    /**
     * Bir alanın hata satırını yazar/siler.
     *
     * NEDEN AYRI BİR FONKSİYON? Hata metni ÜÇ ayrı yerden yazılıyor
     * (anlık doğrulama, canlı benzersizlik yanıtı, sunucunun 422
     * cevabı). Eskiden üçü de kendi satırında `.text(...)` çağırıyordu;
     * .is-shown sınıfı eklenince üçünü de ayrı ayrı düzeltmek
     * gerekecekti — ve biri unutulacaktı.
     *
     * .is-shown NEDEN GEREKLİ? Bootstrap'in .invalid-feedback'i
     * "hemen önceki kardeşim .is-invalid mi?" diye bakar. Şifre
     * alanları göster/gizle düğmesi yüzünden bir sarmalayıcının içine
     * girdi ve bu kardeşlik kırıldı. Görünürlüğü sınıfla yönetmek,
     * kuralı alanın DOM'daki yerinden bağımsız kılar (bkz. style.css).
     */
    function setFieldError(field, message) {
        var $feedback = $('[data-error-for="' + field + '"]');

        $feedback.text(message || '').toggleClass('is-shown', !!message);
    }

    function showFieldState($field, result) {
        $field
            .toggleClass('is-invalid', !result.valid)
            .toggleClass('is-valid', result.valid)
            /* Ekran okuyucu, alanın geçersiz olduğunu rengi görerek
             * anlayamaz; aria-invalid bunu SÖYLER. */
            .attr('aria-invalid', result.valid ? null : 'true');

        setFieldError($field.attr('id'), result.valid ? '' : result.message);
    }

    /** Bütün alanların hata/geçerlilik izlerini siler (gönderim sonrası). */
    function clearAllFieldStates() {
        $('.is-valid, .is-invalid').removeClass('is-valid is-invalid');
        $('[aria-invalid]').removeAttr('aria-invalid');
        $('[data-error-for]').text('').removeClass('is-shown');
        $('#username_status, #email_status').empty();
    }

    function validateAndShow(name) {
        var $field = $('#' + name);
        var result = validators[name]($field.val());

        showFieldState($field, result);

        return result.valid;
    }

    function notify(message, type) {
        var $toast = $(
            '<div class="toast cy-toast cy-toast--' + (type || 'success') + '"' +
                 ' role="alert" aria-live="assertive" aria-atomic="true">' +
                '<div class="d-flex">' +
                    '<div class="toast-body"></div>' +
                    '<button type="button" class="btn-close btn-close-white me-2 m-auto"' +
                          ' data-bs-dismiss="toast" aria-label="Kapat"></button>' +
                '</div>' +
            '</div>'
        );

        $toast.find('.toast-body').text(message);
        $('#toast_container').append($toast);

        var toast = new bootstrap.Toast($toast[0], { delay: 4500 });
        $toast.on('hidden.bs.toast', function () { $toast.remove(); });
        toast.show();
    }

    function post(data) {
        data.csrf_token = config.csrfToken;
        return $.ajax({ url: config.endpoint, method: 'POST', dataType: 'json', data: data });
    }

    /**
     * Hatalı alanı EKRANA GETİR, sonra odakla.
     *
     * ÖLÇÜLEN SORUN (mobil): Gönder düğmesi telefonda sayfanın en
     * altındadır. Formun başındaki bir alan hatalıysa .focus() tek
     * başına yetmiyordu; tarayıcı alanı ekrana getiriyor ama aynı anda
     * klavye açılıp görünür alanı yarıya indiriyor, hata satırı
     * klavyenin ALTINDA kalıyordu. Kullanıcı "bir şey oldu ama ne?"
     * diyordu. Önce alanı ekranın ORTASINA kaydırıp sonra odaklamak,
     * hata metnini klavyenin üstünde bırakır.
     *
     * scrollIntoView'ı .focus()'tan ÖNCE çağırıyoruz: tersi sırada
     * tarayıcının kendi otomatik kaydırması bizimkinin üzerine yazar.
     */
    function revealField($field) {
        var node = $field[0];

        if (!node) { return; }

        if (typeof node.scrollIntoView === 'function') {
            try {
                node.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) {
                // Eski tarayıcılar seçenek nesnesini anlamaz.
                node.scrollIntoView();
            }
        }

        node.focus({ preventScroll: true });
    }


    /* =================================================================
     *  ŞİFREYİ GÖSTER / GİZLE
     * -----------------------------------------------------------------
     *  Alan type="password" ↔ type="text" arasında geçer. Bu, mobilde
     *  bir süs değil gerekliliktir: küçük bir klavyede büyük harf +
     *  küçük harf + rakam zorunluluğu olan bir şifreyi göremeden yazmak,
     *  formun en sık terk edildiği yerdir.
     *
     *  ODAK VE İMLEÇ KORUNUR: type değiştirmek imleci alanın SONUNA
     *  atar. Kullanıcı şifrenin ortasında bir harfi düzeltirken göze
     *  bastıysa imleci kaybetmesi kabul edilemez; konumu okuyup geri
     *  yazıyoruz.
     * ============================================================== */
    function togglePassword($button) {
        var $field   = $('#' + $button.data('toggle-password'));
        var node     = $field[0];
        var revealed = $button.attr('aria-pressed') === 'true';

        if (!node) { return; }

        var start = node.selectionStart;
        var end   = node.selectionEnd;

        node.type = revealed ? 'password' : 'text';

        $button
            .attr('aria-pressed', revealed ? 'false' : 'true')
            .attr('aria-label', revealed ? 'Şifreyi göster' : 'Şifreyi gizle');

        /* setSelectionRange, type="password"/"text" dışındaki alanlarda
         * hata fırlatır; burada ikisinden biri olduğu kesin ama yine de
         * konum okunamadıysa (null) dokunmuyoruz. */
        if (start !== null && end !== null) {
            try { node.setSelectionRange(start, end); } catch (e) { /* yok say */ }
        }
    }

    /** Gönderim sonrası: açık kalmış hiçbir şifre ekranda durmasın. */
    function hideAllPasswords() {
        $('[data-toggle-password]').each(function () {
            var $button = $(this);

            if ($button.attr('aria-pressed') === 'true') {
                togglePassword($button);
            }
        });
    }


    /* =================================================================
     *  TEMA (koyu / açık)
     * -----------------------------------------------------------------
     *  Temanın SAYFA ÇİZİLMEDEN ÖNCE uygulanması gerekir; o iş
     *  index.php'nin <head> bloğunda yapılır (bkz. oradaki yorum).
     *  Burada yalnızca DEĞİŞTİRME vardır.
     *
     *  Seçim yapılmamışsa data-cy-theme özniteliği HİÇ YAZILMAZ:
     *  o durumda cilginyazilim.css'teki prefers-color-scheme, yani
     *  işletim sisteminin tercihi geçerlidir. İlk tıklamada "şu an
     *  hangi temadayız?" sorusunu tarayıcıya soruyoruz — kendi
     *  varsayımımızı yazsaydık, koyu temadaki bir kullanıcının ilk
     *  tıklaması hiçbir şeyi değiştirmemiş gibi görünürdü.
     * ============================================================== */
    function currentTheme() {
        var explicit = document.documentElement.getAttribute('data-cy-theme');

        if (explicit === 'dark' || explicit === 'light') { return explicit; }

        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }

    function toggleTheme() {
        var next = currentTheme() === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-cy-theme', next);

        try {
            localStorage.setItem('cy-theme', next);
        } catch (e) { /* Gizli sekmede yazma engellenebilir; tema yine de değişti. */ }
    }


    /* =================================================================
     *  ŞİFRE GÜCÜ ÖLÇER
     * -----------------------------------------------------------------
     *  ÖLÇÜLEN SORUN: Ölçer, sunucunun REDDETTİĞİ bir şifreye "Güçlü"
     *  diyordu. "abcdefghijkl!" → ölçer 3/4 puan verip "Güçlü" yazıyor,
     *  ama ne istemci ne sunucu kabul ediyor (büyük harf ve rakam yok).
     *  Yani kullanıcı, yeşile yakın bir çubuk görüp formu gönderiyor ve
     *  hata yiyordu. Bir gösterge, ölçtüğü şeyin KABUL EDİLİP
     *  EDİLMEYECEĞİ hakkında yanlış izlenim veriyorsa zararlıdır.
     *
     *  ÇÖZÜM: "Ne kadar güçlü?" sorusu, "kabul edilebilir mi?"
     *  sorusundan SONRA gelir. Zorunlu kural sağlanmadıkça puan
     *  1'i (Zayıf) geçemez. Kural sağlandığında ölçer yeniden anlamlı
     *  olur ve ek çeşitliliği (uzunluk, özel karakter) ödüllendirir.
     *
     *  NEDEN 0 DEĞİL DE 1'DE SINIRLANDI? 0 ("Çok Zayıf") tüm çubuğu
     *  boş gösterir ve kullanıcı yazdıkça hiçbir ilerleme görmez;
     *  ilerleme hissi, uzun bir şifreyi yazmayı sürdürmenin motive
     *  edici kısmıdır. Sınırlama, "yalan söyleme" ile "hiç geri
     *  bildirim verme" arasındaki orta yoldur.
     * ============================================================== */
    function passwordScore(value) {
        var score = 0;
        var rule  = ruleOf('password');

        if (codePointLength(value) >= rule.min) { score++; }
        if (codePointLength(value) >= rule.min * 1.5) { score++; }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { score++; }
        if (/\d/.test(value)) { score++; }
        if (/[^A-Za-z0-9]/.test(value)) { score++; }

        score = Math.min(score, 4);

        // Zorunlu kural sağlanmadıysa puanı kıs (bkz. üstteki not).
        if (!ruleCheck('password', value).valid) {
            score = Math.min(score, 1);
        }

        return score;
    }

    function updatePasswordMeter(value) {
        var labels = ['Çok Zayıf', 'Zayıf', 'Orta', 'Güçlü', 'Çok Güçlü'];
        var score = value === '' ? -1 : passwordScore(value);

        var $meter = $('#password_meter');
        $meter.removeClass('cy-strength--0 cy-strength--1 cy-strength--2 cy-strength--3 cy-strength--4');

        if (score === -1) {
            $meter.css('--cy-strength-width', '0%');
            $('#password_meter_label').text('');
            return;
        }

        $meter.addClass('cy-strength--' + score);
        $meter.css('--cy-strength-width', ((score + 1) / 5 * 100) + '%');
        $('#password_meter_label').text(labels[score]);
    }


    /* =================================================================
     *  CANLI BENZERSİZLİK KONTROLÜ (kullanıcı adı / e-posta)
     * -----------------------------------------------------------------
     *  GECİKTİRME (debounce): Her tuş vuruşunda sunucuya sormak,
     *  "kullaniciadi" için 12 gereksiz sorgu demektir. Kullanıcı
     *  500 ms yazmayı bıraktığında TEK istek atılır. Bu yalnızca bir
     *  nezaket değil, sunucudaki hız sınırıyla uyum meselesidir:
     *  sınır dakikada 40 kontroldür (bkz. config.php) ve geciktirme
     *  olmasaydı tek bir alanı doldurmak bile sınırı zorlardı.
     * ============================================================== */
    function scheduleAvailabilityCheck(field, action) {
        var $field  = $('#' + field);
        var $status = $('#' + field + '_status');

        clearTimeout(checkTimers[field]);

        // Önce İSTEMCİ TARAFI biçim kontrolü: biçim zaten geçersizse
        // sunucuya sormanın anlamı yok (ve boşuna kota harcamanın da).
        var localResult = validators[field]($field.val());
        showFieldState($field, localResult);

        if (!localResult.valid) {
            $status.text('');
            return;
        }

        $status.html('<span class="spinner-border spinner-border-sm text-muted" aria-hidden="true"></span>');

        checkTimers[field] = setTimeout(function () {
            var payload = {};
            payload[field] = $field.val();

            post($.extend({ action: action }, payload)).done(function (response) {
                if (response.available) {
                    // Olumlu sonuç YALNIZCA burada (yeşil ipucu) gösterilir;
                    // .invalid-feedback zaten boştur, tekrar dokunulmaz.
                    $status.html('<span class="text-success">&#10003; Müsait</span>');
                    $field.removeClass('is-invalid').addClass('is-valid');
                } else {
                    // Hata mesajı TEK YERDE (.invalid-feedback) gösterilir —
                    // formun geri kalanıyla AYNI görsel dilde. #username_status
                    // burada BİLEREK boş bırakılır; aksi hâlde aynı metin
                    // iki kez (kırmızı X satırı + invalid-feedback) görünürdü.
                    $status.empty();
                    $field.removeClass('is-valid').addClass('is-invalid').attr('aria-invalid', 'true');
                    setFieldError(field, response.reason);
                }
            }).fail(function (xhr) {
                $status.empty();

                /* Hız sınırına takıldıysak (429) kullanıcıya SÖYLE.
                 * Sessizce yutmak, "yeşil tik neden gelmiyor?" diye
                 * bakan kullanıcıyı arayüzün bozuk olduğuna inandırır. */
                if (xhr.status === 429) {
                    notify((xhr.responseJSON || {}).description
                        || 'Çok fazla istek gönderdiniz, lütfen biraz bekleyin.', 'danger');
                }
            });
        }, 500);
    }


    /* =================================================================
     *  OLAY BAĞLAMA
     * ============================================================== */
    function bindEvents() {
        /* --- Basit alanlar: blur'da doğrula (kullanıcı alandan
         * çıktığında), input'ta ise SADECE zaten hatalıysa anında
         * güncelle (kullanıcı düzeltirken kırmızıyı erkenden
         * kaybettirmemek için "her tuşta doğrula" tercih edilmedi —
         * bu, kullanıcı hâlâ yazarken sürekli kırmızı/yeşil yanıp
         * sönmesini önler). */
        ['full_name', 'phone', 'birth_date'].forEach(function (name) {
            var $field = $('#' + name);

            $field.on('blur', function () { validateAndShow(name); });
            $field.on('input', function () {
                if ($field.hasClass('is-invalid')) { validateAndShow(name); }
            });
        });

        /* --- Canlı benzersizlik: kullanıcı adı / e-posta --- */
        $('#username').on('input', function () { scheduleAvailabilityCheck('username', 'check_username'); });
        $('#email').on('input', function () { scheduleAvailabilityCheck('email', 'check_email'); });

        /* --- Şifre: güç ölçer + eşleşme --- */
        $('#password').on('input', function () {
            updatePasswordMeter($(this).val());

            if ($(this).hasClass('is-invalid') || $(this).val() !== '') { validateAndShow('password'); }
            if ($('#password_confirm').val() !== '') { validateAndShow('password_confirm'); }
        });

        $('#password_confirm').on('input blur', function () { validateAndShow('password_confirm'); });

        /* --- Mesaj: karakter sayacı --- */
        var messageMax = ruleOf('message').max;

        $('#message').on('input', function () {
            // Sayaç da KOD NOKTASI sayar; sunucunun saydığı sayı budur.
            var length = codePointLength($(this).val());

            $('#message_counter').text(length + ' / ' + messageMax);
            $('#message_counter').toggleClass('text-danger', length > messageMax);
        });

        /* --- Sözleşme onayı --- */
        $('#terms').on('change', function () { validateAndShow('terms'); });

        /* --- Şifreyi göster / gizle ---
         * Delege edilmiş bağlama: iki düğme için ayrı ayrı seçici
         * yazmak yerine tek kural. */
        $(document).on('click', '[data-toggle-password]', function () {
            togglePassword($(this));
        });

        /* --- Koyu / açık tema --- */
        $('#theme_toggle').on('click', toggleTheme);

        /* --- Form gönderimi --- */
        $('#validation_form').on('submit', function (event) {
            event.preventDefault();

            // TÜM alanları sırayla doğrula; ilk geçersiz alana odaklan.
            var fieldOrder = [
                'full_name', 'email', 'username', 'phone',
                'password', 'password_confirm', 'birth_date', 'message', 'terms'
            ];

            var firstInvalid = null;

            fieldOrder.forEach(function (name) {
                var valid = validateAndShow(name);

                if (!valid && firstInvalid === null) {
                    firstInvalid = name;
                }
            });

            if (firstInvalid !== null) {
                revealField($('#' + firstInvalid));
                notify('Lütfen formdaki hataları düzeltin.', 'danger');
                return;
            }

            var $button = $('#submit_button').prop('disabled', true);
            $('#submit_spinner').removeClass('d-none');

            post($(this).serializeArray().reduce(function (acc, field) {
                acc[field.name] = field.value;
                return acc;
            }, { terms: $('#terms').is(':checked') ? '1' : '0' }))
            .done(function (response) {
                notify(response.description, 'success');
                $('#validation_form')[0].reset();
                clearAllFieldStates();
                hideAllPasswords();
                updatePasswordMeter('');
                $('#message_counter').text('0 / ' + messageMax).removeClass('text-danger');
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};

                notify(res.description || 'Kayıt oluşturulamadı.', 'danger');

                var $firstServerInvalid = null;

                $.each(res.errors || {}, function (field, message) {
                    var $field = $('#' + field);
                    if ($field.length) {
                        $field.addClass('is-invalid').removeClass('is-valid').attr('aria-invalid', 'true');
                        if ($firstServerInvalid === null) { $firstServerInvalid = $field; }
                    }
                    setFieldError(field, message);
                });

                /* Sunucu hatası, ekranın GÖRÜNMEYEN bir yerindeki alana ait
                 * olabilir; telefonda gönder düğmesi en altta olduğu için
                 * bu neredeyse kesindir. Kullanıcıyı oraya götür. */
                if ($firstServerInvalid !== null) { revealField($firstServerInvalid); }
            })
            .always(function () {
                $button.prop('disabled', false);
                $('#submit_spinner').addClass('d-none');
            });
        });
    }


    /* =================================================================
     *  BAŞLANGIÇ
     * ============================================================== */
    function init(options) {
        $.extend(config, options || {});

        /* Kural seti gelmediyse ERKEN ve GÜRÜLTÜLÜ başarısız ol.
         * Sessizce devam etseydik ruleCheck() her alana "geçerli"
         * derdi ve form, hiçbir istemci kontrolü olmadan çalışıyormuş
         * gibi görünürdü — sunucu yine korur ama sorun günlerce fark
         * edilmezdi. */
        if (!config.rules || !config.rules.full_name) {
            throw new Error('CyValidation: kural seti (rules) verilmedi. Bkz. system/rules.php ve index.php.');
        }

        $(function () {
            bindEvents();
        });
    }

    return { init: init };

})(jQuery);
