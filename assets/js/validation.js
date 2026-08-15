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
 *  yazılıydı ve sessizce ayrışmışlardı (e-postada 190, şifrede 72, web
 *  adresinde 255 sınırı istemcide HİÇ YOKTU; sunucu reddediyor,
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

        // bkz. function.php validate_website() — opsiyonel alan.
        website: function (value) {
            value = (value || '').trim();

            if (value === '') { return ok(); }

            var rule = ruleOf('website');
            var url  = /^https?:\/\//i.test(value) ? value : 'https://' + value;

            // Uzunluk, şema EKLENDİKTEN sonra ölçülür — sunucu da öyle yapar.
            var result = ruleCheck('website', url);
            if (!result.valid) { return result; }

            try {
                var parsed = new URL(url); // Tarayıcının kendi ayrıştırıcısı; geçersizse throw eder.

                /* bkz. function.php validate_website() — aynı zayıflık
                 * burada da giderilir: "https://hicbirsey" tarayıcı için
                 * sözdizimsel olarak GEÇERLİDİR ama gerçek bir alan
                 * adına benzemez (nokta içermez). */
                if (parsed.hostname.indexOf('.') === -1) {
                    return fail(rule.messages.host);
                }
            } catch (e) {
                return fail(rule.messages.format);
            }

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

    function showFieldState($field, result) {
        var $feedback = $('[data-error-for="' + $field.attr('id') + '"]');

        $field.toggleClass('is-invalid', !result.valid).toggleClass('is-valid', result.valid);
        $feedback.text(result.valid ? '' : result.message);
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
                    $field.removeClass('is-valid').addClass('is-invalid');
                    $('[data-error-for="' + field + '"]').text(response.reason);
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
     *  SON GÖNDERİMLER LİSTESİ
     * ============================================================== */
    function loadSubmissions() {
        post({ action: 'list' }).done(function (response) {
            var $list = $('#submission_list').empty();

            if (response.submissions.length === 0) {
                $list.append($('<li>', { 'class': 'list-group-item text-muted', text: 'Henüz gönderim yok.' }));
                return;
            }

            $.each(response.submissions, function (_, row) {
                var $item = $('<li>', { 'class': 'list-group-item d-flex justify-content-between align-items-center' });

                // .text() KULLANILIR: full_name/username kullanıcı
                // girdisidir, .html() olsaydı XSS açığı oluşurdu.
                $('<span>').append(
                    $('<strong>', { text: row.full_name }),
                    document.createTextNode(' @' + row.username)
                ).appendTo($item);

                $('<small>', { 'class': 'text-muted', text: row.created_at }).appendTo($item);

                $list.append($item);
            });
        }).fail(function () {
            $('#submission_list').empty().append(
                $('<li>', { 'class': 'list-group-item text-muted', text: 'Liste yüklenemedi.' })
            );
        });
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
        ['full_name', 'phone', 'birth_date', 'website'].forEach(function (name) {
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

        /* --- Form gönderimi --- */
        $('#validation_form').on('submit', function (event) {
            event.preventDefault();

            // TÜM alanları sırayla doğrula; ilk geçersiz alana odaklan.
            var fieldOrder = [
                'full_name', 'email', 'username', 'phone',
                'password', 'password_confirm', 'birth_date', 'website', 'message', 'terms'
            ];

            var firstInvalid = null;

            fieldOrder.forEach(function (name) {
                var valid = validateAndShow(name);

                if (!valid && firstInvalid === null) {
                    firstInvalid = name;
                }
            });

            if (firstInvalid !== null) {
                $('#' + firstInvalid).trigger('focus');
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
                $('.is-valid, .is-invalid').removeClass('is-valid is-invalid');
                $('[data-error-for]').text('');
                updatePasswordMeter('');
                $('#message_counter').text('0 / ' + messageMax);
                $('#username_status, #email_status').empty();
                loadSubmissions();
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};

                notify(res.description || 'Kayıt oluşturulamadı.', 'danger');

                $.each(res.errors || {}, function (field, message) {
                    var $field = $('#' + field);
                    if ($field.length) {
                        $field.addClass('is-invalid').removeClass('is-valid');
                    }
                    $('[data-error-for="' + field + '"]').text(message);
                });
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
            loadSubmissions();
        });
    }

    return { init: init };

})(jQuery);
