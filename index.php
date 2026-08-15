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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Çılgın Yazılım - cilginyazilim.com">
    <meta name="description" content="PHP ile istemci ve sunucu çift katmanlı form doğrulama örneği: canlı geri bildirim, şifre gücü, AJAX benzersizlik kontrolü.">

    <meta name="csrf-token" content="<?= e($csrfToken) ?>">

    <title>Form Doğrulama Örneği | Çılgın Yazılım</title>

    <link rel="icon" type="image/png" href="assets/images/logo.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/cilginyazilim.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>

<body class="cy-app">

    <div class="cy-topbar"></div>

    <div class="container py-4 py-lg-5">

        <div class="row g-4">

            <!-- ================================================================
                 ANA FORM
                 ================================================================ -->
            <div class="col-lg-8">
                <div class="cy-card">
                    <div class="cy-card__header">
                        <div class="d-flex align-items-center gap-3">
                            <a class="cy-brand" href="https://cilginyazilim.com" target="_blank" rel="noopener">
                                <span class="cy-brand__mark">
                                    <img src="assets/images/logo.png" alt="Çılgın Yazılım logosu">
                                </span>
                                <div>
                                    <h1 class="cy-brand__title">Form Doğrulama Örneği</h1>
                                    <p class="cy-brand__subtitle">
                                        İstemci + sunucu çift katman &middot; cilginyazilim.com
                                    </p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="cy-card__body">
                        <form id="validation_form" novalidate>
                            <div class="alert alert-danger d-none" id="form_alert" role="alert"></div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                <!-- maxlength de kuraldan gelir: aynı sınırın ÜÇÜNCÜ bir elle
                                     yazılmış kopyası olmasın (PHP + JS + HTML). -->
                                <input type="text" id="full_name" name="full_name" class="form-control"
                                       maxlength="<?= (int) $clientRules['full_name']['max'] ?>" autocomplete="name">
                                <div class="invalid-feedback" data-error-for="full_name"></div>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control"
                                           maxlength="<?= (int) $clientRules['email']['max'] ?>" autocomplete="email">
                                    <div class="invalid-feedback" data-error-for="email"></div>
                                    <div class="cy-field-status" id="email_status"></div>
                                </div>

                                <div class="col-sm-6">
                                    <label for="username" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                                    <input type="text" id="username" name="username" class="form-control"
                                           maxlength="<?= (int) $clientRules['username']['max'] ?>" autocomplete="username">
                                    <div class="invalid-feedback" data-error-for="username"></div>
                                    <div class="cy-field-status" id="username_status"></div>
                                </div>
                            </div>

                            <div class="row g-3 mt-0">
                                <div class="col-sm-6">
                                    <label for="phone" class="form-label">Telefon</label>
                                    <input type="tel" id="phone" name="phone" class="form-control"
                                           placeholder="05XX XXX XX XX" autocomplete="tel">
                                    <div class="form-text">Boş bırakılabilir.</div>
                                    <div class="invalid-feedback" data-error-for="phone"></div>
                                </div>

                                <div class="col-sm-6">
                                    <label for="birth_date" class="form-label">Doğum Tarihi</label>
                                    <input type="date" id="birth_date" name="birth_date" class="form-control">
                                    <div class="form-text">Boş bırakılabilir; girilirse <?= (int) $clientRules['birth_date']['minAge'] ?>+ yaş kontrol edilir.</div>
                                    <div class="invalid-feedback" data-error-for="birth_date"></div>
                                </div>
                            </div>

                            <div class="row g-3 mt-0">
                                <div class="col-sm-6">
                                    <label for="password" class="form-label">Şifre <span class="text-danger">*</span></label>
                                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password">
                                    <!-- Şifre gücü ölçer: --cy-strength-width JS tarafından yazılır -->
                                    <div class="cy-strength" id="password_meter">
                                        <div class="cy-strength__bar"></div>
                                    </div>
                                    <div class="form-text" id="password_meter_label">&nbsp;</div>
                                    <div class="invalid-feedback" data-error-for="password"></div>
                                </div>

                                <div class="col-sm-6">
                                    <label for="password_confirm" class="form-label">Şifre Tekrar <span class="text-danger">*</span></label>
                                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" autocomplete="new-password">
                                    <div class="invalid-feedback" data-error-for="password_confirm"></div>
                                </div>
                            </div>

                            <div class="mb-3 mt-3">
                                <label for="website" class="form-label">Web Sitesi</label>
                                <input type="text" id="website" name="website" class="form-control" placeholder="ornek.com">
                                <div class="form-text">Boş bırakılabilir; "https://" yazmasanız da otomatik eklenir.</div>
                                <div class="invalid-feedback" data-error-for="website"></div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label for="message" class="form-label mb-0">Mesaj</label>
                                    <small class="text-muted" id="message_counter">0 / <?= (int) $clientRules['message']['max'] ?></small>
                                </div>
                                <textarea id="message" name="message" class="form-control" rows="3"
                                          maxlength="<?= (int) $clientRules['message']['max'] ?>"></textarea>
                                <div class="invalid-feedback" data-error-for="message"></div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" id="terms" name="terms" class="form-check-input">
                                <label for="terms" class="form-check-label">
                                    <a href="#" onclick="return false;">Kullanım şartlarını</a> okudum ve kabul ediyorum.
                                </label>
                                <div class="invalid-feedback" data-error-for="terms"></div>
                            </div>

                            <button type="submit" id="submit_button" class="btn cy-btn cy-btn--primary w-100">
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
                 YAN PANEL: son gönderimler + doğrulama katmanları özeti
                 ================================================================ -->
            <div class="col-lg-4">
                <div class="cy-card mb-4">
                    <div class="cy-card__body">
                        <h2 class="h6 mb-3">Son Gönderimler</h2>
                        <ul class="list-group list-group-flush" id="submission_list">
                            <li class="list-group-item text-muted">Yükleniyor…</li>
                        </ul>
                    </div>
                </div>

                <div class="cy-card">
                    <div class="cy-card__body">
                        <h2 class="h6 mb-2">İki Katmanlı Doğrulama</h2>
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

        <div class="cy-footer-note mt-4">
            <p class="mb-1">
                Bu açık kaynak örnek, <a href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a>
                tarafından geliştirilmiştir. MIT lisanslıdır.
            </p>
            <p class="mb-0">
                Kaynak kod:
                <a href="https://github.com/CilginYazilim/form-validation-example"
                   target="_blank" rel="noopener">github.com/CilginYazilim/form-validation-example</a>
            </p>
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
