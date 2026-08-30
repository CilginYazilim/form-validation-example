<?php
/**
 * =====================================================================
 *  ANA SAYFA (Sunum Katmanı)
 *  cilginyazilim.com – Form Doğrulama Örneği
 * =====================================================================
 */

declare(strict_types=1);

/* Bkz. system/config.php – dosyaların doğrudan çağrılmasını engelleyen işaret. */
define('CY_APP', true);

require __DIR__ . '/system/config.php';
require __DIR__ . '/system/rules.php';
require __DIR__ . '/system/function.php';

$csrfToken = csrf_token();

/* Doğrulama kurallarının İSTEMCİYE gidecek kopyası. Bu satır, projenin
 * en önemli mimari kararıdır: JavaScript artık sınırları KENDİ
 * kopyasından değil, sunucunun tek kaynağından okur. Bkz. system/rules.php */
$clientRules = client_rules();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <!-- viewport-fit=cover: çentikli (notch) telefonlarda sayfa ekranın
         tamamını kullanır; güvenli alan boşlukları CSS'te env() ile
         verilir (bkz. style.css → MOBİL). maximum-scale YAZILMAZ:
         yakınlaştırmayı kapatmak bir erişilebilirlik ihlalidir. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="author" content="Çılgın Yazılım - cilginyazilim.com">
    <meta name="description" content="PHP ile istemci ve sunucu çift katmanlı form doğrulama örneği: canlı geri bildirim, şifre gücü, AJAX benzersizlik kontrolü.">

    <!-- Mobil tarayıcının adres çubuğu, sayfanın zeminiyle aynı renge
         boyanır. İki ayrı değer: cihaz teması değiştiğinde şerit de değişsin. -->
    <meta name="theme-color" content="#f2f7fd" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#070f1a" media="(prefers-color-scheme: dark)">

    <meta name="csrf-token" content="<?= e($csrfToken) ?>">

    <title>Form Doğrulama Örneği | Çılgın Yazılım</title>

    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/cilginyazilim.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">

    <script>
        /* TEMA — SAYFA ÇİZİLMEDEN ÖNCE UYGULANIR.
         *
         * Bu blok bilerek <head> içinde ve SATIR İÇİDİR. validation.js
         * sayfanın sonunda yüklenir; temayı orada uygulasaydık kullanıcı
         * önce AÇIK temayı görür, sonra ekran koyuya "sıçrardı" (flash of
         * wrong theme). Koyu temayı tercih eden birine her açılışta yarım
         * saniyelik beyaz ekran göstermek, özellikle telefonda ve
         * karanlıkta, kabul edilebilir değil.
         *
         * Seçim yapılmadıysa hiçbir öznitelik YAZILMAZ: o durumda
         * cilginyazilim.css'teki prefers-color-scheme sorgusu, yani
         * İŞLETİM SİSTEMİNİN tercihi geçerli olur. */
        (function () {
            try {
                var saved = localStorage.getItem('cy-theme');
                if (saved === 'dark' || saved === 'light') {
                    document.documentElement.setAttribute('data-cy-theme', saved);
                }
            } catch (e) { /* Gizli sekmede localStorage erişimi hata verebilir. */ }
        })();
    </script>
</head>

<body class="cy-app">

    <div class="cy-topbar"></div>

    <div class="container cy-container py-3 py-md-4 py-lg-5">

        <div class="row g-3 g-lg-4">

            <!-- ================================================================
                 ANA FORM
                 ================================================================ -->
            <div class="col-lg-8">
                <div class="cy-card">
                    <div class="cy-card__header cy-page-header">
                        <a class="cy-brand" href="https://cilginyazilim.com" target="_blank" rel="noopener">
                            <span class="cy-brand__mark">
                                <img src="assets/images/logo.png" alt="Çılgın Yazılım logosu">
                            </span>
                            <div class="cy-brand__text">
                                <h1 class="cy-brand__title">Form Doğrulama Örneği</h1>
                                <p class="cy-brand__subtitle">
                                    İstemci + sunucu çift katman &middot; cilginyazilim.com
                                </p>
                            </div>
                        </a>

                        <!-- Tema düğmesi. type="button": varsayılan "submit"
                             davranışına güvenmiyoruz. -->
                        <button type="button" class="cy-theme-toggle" id="theme_toggle"
                                aria-label="Koyu / açık tema değiştir" title="Koyu / açık tema">
                            <span class="cy-theme-toggle__icon" aria-hidden="true"></span>
                        </button>
                    </div>

                    <div class="cy-card__body">
                        <form id="validation_form" novalidate>
                            <div class="alert alert-danger d-none" id="form_alert" role="alert"></div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                <!-- maxlength de kuraldan gelir: aynı sınırın ÜÇÜNCÜ bir elle
                                     yazılmış kopyası olmasın (PHP + JS + HTML).
                                     autocapitalize="words": telefonda baş harfleri klavye
                                     kendisi büyütür. -->
                                <input type="text" id="full_name" name="full_name" class="form-control"
                                       maxlength="<?= (int) $clientRules['full_name']['max'] ?>"
                                       autocomplete="name" autocapitalize="words"
                                       enterkeyhint="next" aria-describedby="full_name_error">
                                <div class="invalid-feedback" id="full_name_error" data-error-for="full_name"></div>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                    <!-- inputmode="email": mobil klavyede "@" ve "." doğrudan
                                         görünür. autocapitalize/autocorrect kapalı: iOS aksi
                                         hâlde adresin ilk harfini büyütür ve yazılanı
                                         "düzeltmeye" çalışır. -->
                                    <input type="email" id="email" name="email" class="form-control"
                                           maxlength="<?= (int) $clientRules['email']['max'] ?>"
                                           autocomplete="email" inputmode="email"
                                           autocapitalize="none" autocorrect="off" spellcheck="false"
                                           enterkeyhint="next" aria-describedby="email_error email_status">
                                    <div class="invalid-feedback" id="email_error" data-error-for="email"></div>
                                    <!-- aria-live="polite": "Müsait" sonucu ekran okuyucuya da
                                         duyurulur; görsel yeşil tik tek başına yetmez. -->
                                    <div class="cy-field-status" id="email_status" role="status" aria-live="polite"></div>
                                </div>

                                <div class="col-sm-6">
                                    <label for="username" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control"
                                           maxlength="<?= (int) $clientRules['username']['max'] ?>"
                                           autocomplete="username" inputmode="text"
                                           autocapitalize="none" autocorrect="off" spellcheck="false"
                                           enterkeyhint="next" aria-describedby="username_error username_status">
                                    <div class="invalid-feedback" id="username_error" data-error-for="username"></div>
                                    <div class="cy-field-status" id="username_status" role="status" aria-live="polite"></div>
                                </div>
                            </div>

                            <div class="row g-3 mt-0">
                                <div class="col-sm-6">
                                    <label for="phone" class="form-label">Telefon</label>
                                    <!-- inputmode="tel": telefonda harf klavyesi değil TUŞ
                                         TAKIMI açılır; type="tel" bunu her tarayıcıda tek
                                         başına garanti etmez. -->
                                    <input type="tel" id="phone" name="phone" class="form-control"
                                           placeholder="05XX XXX XX XX" autocomplete="tel"
                                           inputmode="tel" enterkeyhint="next"
                                           aria-describedby="phone_help phone_error">
                                    <div class="form-text" id="phone_help">Boş bırakılabilir.</div>
                                    <div class="invalid-feedback" id="phone_error" data-error-for="phone"></div>
                                </div>

                                <div class="col-sm-6">
                                    <label for="birth_date" class="form-label">Doğum Tarihi</label>
                                    <input type="date" id="birth_date" name="birth_date" class="form-control"
                                           autocomplete="bday" aria-describedby="birth_date_help birth_date_error">
                                    <div class="form-text" id="birth_date_help">Boş bırakılabilir; girilirse <?= (int) $clientRules['birth_date']['minAge'] ?>+ yaş kontrol edilir.</div>
                                    <div class="invalid-feedback" id="birth_date_error" data-error-for="birth_date"></div>
                                </div>
                            </div>

                            <div class="row g-3 mt-0">
                                <div class="col-sm-6">
                                    <label for="password" class="form-label">Şifre <span class="text-danger">*</span></label>
                                    <!-- Göster/gizle düğmesi mobilde bir nezaket DEĞİL,
                                         gerekliliktir: küçük bir klavyede büyük harf + küçük
                                         harf + rakam zorunluluğu olan bir şifreyi görmeden
                                         yazmak, formun en sık terk edildiği yerdir. -->
                                    <div class="cy-password">
                                        <input type="password" id="password" name="password" class="form-control"
                                               autocomplete="new-password" enterkeyhint="next"
                                               aria-describedby="password_meter_label password_error">
                                        <button type="button" class="cy-password__toggle"
                                                data-toggle-password="password"
                                                aria-label="Şifreyi göster" aria-pressed="false">
                                            <span class="cy-password__icon" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                    <!-- Şifre gücü ölçer: --cy-strength-width JS tarafından yazılır -->
                                    <div class="cy-strength" id="password_meter">
                                        <div class="cy-strength__bar"></div>
                                    </div>
                                    <div class="form-text" id="password_meter_label" role="status" aria-live="polite">&nbsp;</div>
                                    <div class="invalid-feedback" id="password_error" data-error-for="password"></div>
                                </div>

                                <div class="col-sm-6">
                                    <label for="password_confirm" class="form-label">Şifre Tekrar <span class="text-danger">*</span></label>
                                    <div class="cy-password">
                                        <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                                               autocomplete="new-password" enterkeyhint="next"
                                               aria-describedby="password_confirm_error">
                                        <button type="button" class="cy-password__toggle"
                                                data-toggle-password="password_confirm"
                                                aria-label="Şifreyi göster" aria-pressed="false">
                                            <span class="cy-password__icon" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback" id="password_confirm_error" data-error-for="password_confirm"></div>
                                </div>
                            </div>

                            <div class="mb-3 mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label for="message" class="form-label mb-0">Mesaj</label>
                                    <small class="text-muted" id="message_counter">0 / <?= (int) $clientRules['message']['max'] ?></small>
                                </div>
                                <textarea id="message" name="message" class="form-control" rows="3"
                                          maxlength="<?= (int) $clientRules['message']['max'] ?>"
                                          enterkeyhint="done" aria-describedby="message_counter message_error"></textarea>
                                <div class="invalid-feedback" id="message_error" data-error-for="message"></div>
                            </div>

                            <div class="mb-3 form-check cy-check">
                                <input type="checkbox" id="terms" name="terms" class="form-check-input"
                                       aria-describedby="terms_error">
                                <label for="terms" class="form-check-label">
                                    <!-- Eskiden href="#" + onclick="return false" idi:
                                         dokunulunca hiçbir şey olmayan bir bağlantı,
                                         telefonda "bozuk" izlenimi verir. Artık gerçekten
                                         bir metin açılır. -->
                                    <button type="button" class="cy-link-btn" data-bs-toggle="modal" data-bs-target="#terms_modal">Kullanım şartlarını</button>
                                    okudum ve kabul ediyorum.
                                </label>
                                <div class="invalid-feedback" id="terms_error" data-error-for="terms"></div>
                            </div>

                            <button type="submit" id="submit_button" class="btn cy-btn cy-btn--primary w-100 cy-submit">
                                <span class="spinner-border spinner-border-sm me-1 d-none" id="submit_spinner" role="status" aria-hidden="true"></span>
                                Kaydı Oluştur
                            </button>
                        </form>
                    </div>

                    <div class="cy-card__footer d-flex flex-wrap justify-content-between gap-2">
                        <span>CSRF korumalı AJAX &middot; Sunucu, istemciyle AYNI kuralları TEKRAR uygular</span>
                        <span>PHP <?= e(PHP_VERSION) ?></span>
                    </div>
                </div>
            </div>

            <!-- ================================================================
                 YAN PANEL: doğrulama katmanları özeti
                 ----------------------------------------------------------------
                 MOBİLDE KAPALI BAŞLAR. Bu panel açıklayıcı metindir; formu
                 doldurmak için gerekli değildir. Telefonda formun ALTINDA üç
                 paragraf hâlinde durunca gönder düğmesinden sonra gereksiz bir
                 kaydırma kuyruğu bırakıyordu. Masaüstünde (lg ve üstü) açık
                 gelir ve katlama düğmesi hiç görünmez.
                 ================================================================ -->
            <div class="col-lg-4">
                <div class="cy-card cy-info">
                    <button type="button" class="cy-info__toggle collapsed d-lg-none"
                            data-bs-toggle="collapse" data-bs-target="#info_panel"
                            aria-expanded="false" aria-controls="info_panel">
                        <span>İki Katmanlı Doğrulama</span>
                        <span class="cy-info__chevron" aria-hidden="true"></span>
                    </button>

                    <div class="collapse d-lg-block" id="info_panel">
                        <div class="cy-card__body">
                            <h2 class="h6 mb-2 d-none d-lg-block">İki Katmanlı Doğrulama</h2>
                            <p class="small text-muted mb-2">
                                İstemci (JavaScript) tarafındaki kontroller SADECE kullanıcı
                                deneyimi içindir. Gerçek güvenlik sınırı, her zaman
                                <code>system/function.php</code> içindeki sunucu
                                doğrulayıcılarıdır.
                            </p>
                            <p class="small text-muted mb-2">
                                İki taraf da AYNI kuralı uygular çünkü sınırlar
                                <code>system/rules.php</code>'de TEK KEZ tanımlanır;
                                JavaScript o sayıları kendi kopyasından değil,
                                sunucudan okur.
                            </p>
                            <p class="small text-muted mb-0">
                                Kullanıcı adı ve e-posta için "müsait mi?" kontrolü
                                CANLI olarak sorulur, ama kayıt anında SUNUCU bunu
                                YENİDEN kontrol eder — iki kullanıcı aynı anda aynı
                                adı denerse, veritabanındaki <code>UNIQUE</code>
                                indeks son sözü söyler.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cy-footer-note mt-4">
            <p class="mb-1">
                Bu açık kaynak örnek, <a href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a>
                tarafından geliştirilmiştir. MIT lisanslıdır.
            </p>
            <p class="mb-1">
                Kaynak kod:
                <a href="https://github.com/CilginYazilim/form-validation-example"
                   target="_blank" rel="noopener">github.com/CilginYazilim/form-validation-example</a>
            </p>
            <p class="mb-0">
                Daha fazla örnek kod:
                <a href="https://cilginyazilim.com/kutuphane"
                   target="_blank" rel="noopener">cilginyazilim.com/kutuphane</a>
            </p>
        </div>
    </div>

    <!-- ====================================================================
         KULLANIM ŞARTLARI
         Onay kutusunun yanındaki bağlantının GERÇEKTEN bir şey açması için.
         ==================================================================== -->
    <div class="modal fade cy-modal" id="terms_modal" tabindex="-1" aria-labelledby="terms_modal_title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6 mb-0" id="terms_modal_title">Kullanım Şartları</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2">
                        Bu sayfa, <strong>form doğrulamayı anlatan açık kaynaklı bir örnektir</strong>;
                        gerçek bir üyelik hizmeti değildir.
                    </p>
                    <p class="small mb-2">
                        Gönderdiğiniz veriler yalnızca örneğin çalıştığını göstermek için
                        kaydedilir. <strong>Gerçek bir şifrenizi veya gerçek kişisel
                        bilgilerinizi girmeyin.</strong> Şifre alanı, saklamadan önce
                        <code>password_hash()</code> ile özetlenir — yani düz metin olarak
                        tutulmaz — ama bu, örnek bir kurulumu gerçek bir hesap gibi
                        kullanmanız için sebep değildir.
                    </p>
                    <p class="small mb-0">
                        Kaynak kodu MIT lisanslıdır; dilediğiniz gibi indirip
                        kullanabilirsiniz. Ayrıntı için
                        <a href="https://github.com/CilginYazilim/form-validation-example" target="_blank" rel="noopener">depoya</a>
                        bakabilirsiniz.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn cy-btn cy-btn--primary" data-bs-dismiss="modal">Anladım</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="toast_container"></div>

    <script src="assets/js/jquery-3.7.0.js"></script>
    <script src="assets/js/bootstrap.bundle.js"></script>
    <script src="assets/js/validation.js?v=<?= filemtime(__DIR__ . '/assets/js/validation.js') ?>"></script>
    <script>
        CyValidation.init({
            endpoint:  'system/ajax.php',
            csrfToken: <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>,
            // Sunucudaki system/rules.php'nin AYNISI. Elle yazılmış hiçbir
            // sınır yok; "100" veya "72" gibi bir sayıyı validation.js
            // içinde ararsanız BULAMAZSINIZ — kasıtlıdır.
            rules: <?= json_encode($clientRules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        });
    </script>
</body>
</html>
