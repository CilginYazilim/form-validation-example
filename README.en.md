<div align="center">

<img src="assets/images/logo.png" alt="Çılgın Yazılım" width="90">

# Form Validation Example

**Two-layer form validation — client + server, with rules from a single source.**
Live feedback · Password strength meter · AJAX uniqueness check · TOCTOU chain

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-brightgreen?style=flat-square)](LICENSE)

[cilginyazilim.com](https://cilginyazilim.com) &nbsp;·&nbsp; [Türkçe](README.md) | **English**

</div>

---

<div align="center">

<img src="assets/images/screenshot-live-validation.png" alt="Live validation: several fields red with error messages, a green ✓ Available next to the username, password strength meter filled" width="920">

<sub>The whole project in one frame: the e-mail was checked <b>live</b> and came back “already registered”,<br>
the username shows a green <b>“✓ Müsait” (Available)</b>, the strength meter reads <b>“Çok Güçlü” (Very Strong)</b>,<br>
and phone and birth date are red <b>with reasons</b>. The form answers you while you type.</sub>

</div>

---

## What does this project do?

A nine-field registration form. Every field is validated both **instantly** (JavaScript, for user experience) and **on the server** (PHP, as the security boundary). For username and e-mail, the “is this available?” question is asked **live** as you type.

But what the project actually teaches is not a form — it is three questions underneath it:

> 1. Why is client-side validation **not security**, and why is server-side validation **the only boundary**?
> 2. What happens when the same rule is written twice in two languages — and how is that solved **structurally**?
> 3. When does “this username is available” become a **lie**, and which layer catches that lie?

---

## 1. The golden rule: client-side validation is not security

> No check in the browser is a security measure.

This is not a slogan, it is a measurable fact. You can open the console and delete the `CyValidation` object, never load `assets/js/validation.js` at all, or POST straight to `system/ajax.php` without ever opening the form. That is exactly what was done in this repository — no JavaScript, a direct POST from the shell:

```
POST system/ajax.php
  full_name=x  email=gecersiz  username=1KOTU
  password=kisa  password_confirm=baska
  birth_date=2030-13-45  terms=0

→ HTTP 422
  errors: full_name, email, username, password,
          password_confirm, birth_date, terms
→ Records created: 0
```

**So what is the client layer for?** So the user doesn't have to wait for a page reload to see a mistake. That is its only job — and it is not a worthless job. It just has nothing to do with security.

**Why is the server the only boundary?** Because it is the only place the attacker does not control. Browser code runs on the user's machine, under the user's control; the place where you *state* a rule is not the place that *enforces* it.

---

## 2. One rule, two languages: the drift problem and its structural fix

This is the most instructive engineering decision in the project.

### The problem was measured

In the old version, every JavaScript validator carried a comment like `// see function.php validate_email()`. In other words, “these two lists must match” was expressed **in a comment**. Comments don't compile, aren't tested, and never break. The result, measured by driving the real form in a browser:

| Input | Server | Client | Consequence |
|-------|--------|--------|-------------|
| E-mail, 191 chars | ❌ rejected | ✅ **accepted** | User submits, then gets a server error |
| Password, 73 chars | ❌ rejected | ✅ **accepted** | Same |
| Message, 495 letters + 20 spaces | ✅ accepted | ❌ **rejected** | Valid input blocked on the client |
| Full name, 60 astral letters | ✅ accepted | ❌ **rejected** | Same (`.length` counts 120) |
| Username `"şş"` | “pattern error” | “length error” | **Two different reasons for one input** |
| Username `"şşşşşşşşşşş"` | “length error” | “pattern error” | Same, in reverse |

The absence of those two limits (190 / 72) on the client was not carelessness — it is an **inevitable outcome**: wherever the same fact is stored in two places, the two drift apart over time.

### The fix: turn the limits into data

Numeric limits, patterns and error messages are now defined **once**, in **[`system/rules.php`](system/rules.php)**:

```php
'password' => [
    'required' => true,
    'min'      => 8,
    'max'      => 72,                                   // bcrypt's hard limit
    'pattern'  => '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$',
    'messages' => [ … ],
],
```

* **PHP** validators read this array through `rule_check()`.
* **`index.php`** serialises the same array with `client_rules()` and embeds it in the page.
* **`validation.js`** reads that JSON. **Not a single number** like `72`, `190` or `100` appears in the file.
* **HTML `maxlength`** attributes are printed from the same array — so there is no third hand-written copy either.

### Proof that the binding is real

Since a comment saying “these must match” was not enough, the binding itself was measured. Two numbers were changed in `rules.php` (`full_name.max` 100 → 40, `birth_date.min_age` 18 → 21) and **no other file was touched**:

|  | Before | After |
|--|--------|-------|
| **Server** – 50-letter name | accepted | `Ad soyad 2-40 karakter arasında olmalıdır.` |
| **Client** – 100-letter name | accepted | `Ad soyad 2-40 karakter arasında olmalıdır.` |
| **Server** – date of a 19-year-old | accepted | `…en az 21 yaşında olmalısınız.` |
| **Client** – date of a 17-year-old | `…en az 18…` | `…en az 21 yaşında olmalısınız.` |

Not just the numbers — the **message text moved too**, because messages are generated from the same source via `{min}` / `{max}` / `{age}` placeholders.

### What this fix does *not* cover (an honest boundary)

Only the part that can be turned into **data** is shared. The following must be written separately in each language, and are deliberately left separate:

| Procedure | PHP | JavaScript | Why it can't be shared |
|-----------|-----|------------|------------------------|
| E-mail format | `filter_var(FILTER_VALIDATE_EMAIL)` | simple pattern | No exact browser equivalent |
| Age calculation | `DateTimeImmutable::diff()` | manual arithmetic | Different date libraries |
| Phone normalisation | `preg_replace` + `substr` | `replace` + `slice` | Same logic, different syntax |

The client's e-mail pattern is **deliberately looser**. A loose client never makes the mistake of rejecting an address the server would accept; the opposite direction merely costs one extra server round-trip. So even the **direction** in which drift is allowed is a design decision.

Shared patterns are also restricted to the subset that means **the same thing in both engines** (`\p{L}`, `\p{M}`, `\d`, `\s`, character classes, anchors). Nothing PHP-specific is used — that would make the pattern behave differently in the browser and quietly bring back the very problem we set out to solve.

---

## 3. “Is it available?” — the trade-off between UX value and account enumeration

While you type a username or e-mail, a 500 ms debounced AJAX request asks “is this available?”. This feature has **two faces**, and this repository measured both.

### The good face: a real UX win

The user doesn't fill in the whole form and only **then** hit the wall of “that username is taken, start over”. The answer arrives while typing. That is the green **“✓ Müsait”** in the screenshot.

### The bad face: account enumeration

By definition, this endpoint answers the question **“is this e-mail registered on this site?”**. Anyone holding a list of e-mail addresses can ask them one by one and learn which are registered. Unprotected, the measurement showed how easy that was: **60 consecutive queries, 60 × HTTP 200.**

The timing side was measured too — and there the news was **good**:

```
check_email, registered vs unregistered address (150 interleaved requests each)
  median difference : 0.065 ms
  p10 difference    : 0.024 ms
  measurement noise : 0.431 ms
→ No information leaks through timing.
```

It doesn't need to: the answer is given in plain text. This is a good example of a common security fallacy — check the front door before hunting for side channels.

### The decision, and why

**The live check stays. A rate limit was added on top.** Reasoning:

1. **Removing the feature would be removing the project.** This repository exists to explain exactly this feature. A “fix” that deletes what it teaches is not instructive.
2. **The leaked information is reduced to a single bit.** The application **never lists** stored data — there is no record list, search, counter or profile on screen. The only thing the endpoint can reveal is “exists / doesn't” for **one value at a time**. There is no way to pull a bulk list; the only remaining route is trying values one by one — which is exactly what the rate limit makes expensive.
3. **The limit covers both fields.** Username and e-mail share one quota. Separate quotas would have let an enumerator fill both and send twice as many requests.
4. **A rate limit makes enumeration expensive, not impossible.** This must be said honestly: a distributed attacker (a botnet) rotating IPs defeats it. The only way to end enumeration completely is to remove the feature.

**What would you do in a real product?** Make the registration flow *always appear to succeed* and deliver the outcome by e-mail (“if this address is already registered, we've sent a sign-in link”). Then the endpoint **says nothing**. The price is losing all of this live feedback. Being a demo, this repository chose the **UX side** of the trade-off and wrote its reasoning down — which is the genuinely instructive part.

### How the limits were chosen

| Endpoint | Limit | Why this number |
|----------|-------|-----------------|
| `check_username` / `check_email` | **40 / min** (shared quota) | A real user filling the form sends ~10-15 requests (thanks to the 500 ms debounce). Measured with 16 requests: the limit **does not trigger**. |
| `submit` | **5 / min** | A human does not register five times a minute. Its real purpose is protecting the CPU — see below. |

The real justification for the `submit` limit is a measurement: **`password_hash()` costs ~116 ms of CPU on this machine** (bcrypt, cost 10) — roughly **90 %** of a submission's total time (~128 ms). Being able to burn 116 ms of CPU with an unauthenticated request is a cheap denial-of-service lever. The limit is applied **before** validation; applied after, a bot sending invalid forms would keep the server busy without ever hitting it.

The counter is written to a `flock()`-protected file rather than the database: doing an `INSERT` per request would generate exactly the load you are trying to prevent.

---

## 4. The TOCTOU chain: three layers, measured

An “available” answer does **not** guarantee the record will save:

```
User A: types "ahmet"  → live check: available ✓
User B: types "ahmet"  → live check: available ✓  (A hasn't saved yet)
User A: submits        → success, "ahmet" is taken
User B: submits        → ???
```

This is the classic **TOCTOU** (time-of-check / time-of-use) gap. It is closed with three layers:

| # | Layer | Where | What it catches |
|---|-------|-------|-----------------|
| 1 | Live check | `handle_check_username` / `handle_check_email` | Nothing — **UX only**. Guarantees nothing. |
| 2 | Final check | re-query inside `handle_submit()` | The vast majority of collisions |
| 3 | `UNIQUE` index | Database | Genuine concurrency — **the last word** |

### I tried to break the chain; it held

The same submission was fired as **two concurrent requests**, 12 rounds:

| Scenario | Result | Records created |
|----------|--------|-----------------|
| Two requests from the **same session** | `200` + `422` (12/12) | **1** per round |
| Two requests from **different sessions** | `200` + `409` (12/12) | **1** per round |

A duplicate record was created in **no round at all**.

The difference is instructive: two requests carrying the same `PHPSESSID` are serialised by PHP's **session lock** — the second runs only after the first finishes, so layer 2 (the final check) catches it. A genuine race only occurs between **two users in different sessions**; there layer 2 is not enough and layer 3 steps in: the `UNIQUE` index raises `SQLSTATE 23000`, which the application catches and returns as **HTTP 409** with a human-readable message. The raw SQL error is never leaked.

> **So layer 3 is not there “just in case” — it fired 12/12 in measurement.** Code stopping at two layers would have produced duplicate records in all 12 cases.

---

## 5. Validation rules — and the reason behind each

| Field | Rule | Required | Why |
|-------|------|----------|-----|
| Full name | 2-100 chars, `\p{L}\p{M}` + space, `.`, `'`, `-` | ✔ | `\p{L}` covers letters in any language: “Ayşe”, “O'Brien”, “Jean-Luc” pass. Digits and `<script>` don't. Extra spaces are **not an error**, they are normalised. |
| E-mail | Valid format, ≤ **190** chars, unique | ✔ | 190 is the practical limit for putting a `UNIQUE` index on a utf8mb4 column (191×4 ≈ 767 bytes). If validation allowed 255, the database would silently truncate. **Schema and validation look at the same number.** |
| Username | 3-20, starts lowercase, `a-z0-9_`, unique | ✔ | A name starting with a digit (`1admin`) can be confused with a numeric ID by an endpoint expecting one. |
| Phone | TR mobile format (`05XX XXX XX XX`) | — | Spaces/dashes are free-form and stripped **before** validation; a leading `+90` is accepted. Stored in one standard format. |
| Password | 8-**72** chars, upper + lower + digit | ✔ | **72 is bcrypt's hard limit**: `password_hash()` *silently ignores* everything past it. Say nothing and users end up with “I lengthened my password but the old one still works”. Special characters are not required — NIST recommends prioritising length over stacks of composition rules. |
| Confirm password | Must match | ✔ | `hash_equals()` is unnecessary: both values are the user's own input, so there is no timing risk. |
| Birth date | Valid date + **18** years | — | Age computed with `DateInterval` (leap-year edge cases included). The limit lives in `rules.php` — change it and the client follows. |
| Message | ≤ 500 chars | — | Counter and limit count **code points**; that is what the server counts too. |
| Terms | Must be checked | ✔ | `terms=0` and a missing `terms` are **both** rejected. |

### Why the password strength meter was rewritten

Measured: the old meter called a password the **server rejects** “Strong”.

| Password | Old meter | Server | New meter |
|----------|-----------|--------|-----------|
| `abcdefghijkl!` | **“Güçlü” (Strong)** | ❌ REJECTED | “Zayıf” (Weak) |
| `ABCDEFGH1` | “Orta” (Medium) | ❌ REJECTED | “Zayıf” (Weak) |
| `Parola12` | “Güçlü” | ✅ ACCEPTED | “Güçlü” |
| `Parola123456!` | “Çok Güçlü” (Very Strong) | ✅ ACCEPTED | “Çok Güçlü” |

Users saw a nearly-green bar, submitted, and got an error. **An indicator that misleads about whether its subject will be accepted is worse than no indicator.** The fix: “how strong?” only comes **after** “is it acceptable?” — the score cannot exceed “Weak” until the mandatory rule is satisfied. (Pinning it to 0 would be wrong too: the user would see no progress at all while typing.)

---

## 6. Security layers

Every item below was found **by measurement** in this repository, and closed.

### File access and headers — `.htaccess`

| URL | Before | After |
|-----|--------|-------|
| `/system/config.php` | **200** (opened a DB connection on every call) | **403** |
| `/system/function.php` | **200** | **403** |
| `/cy_validation.sql` | **200** (schema + all data downloadable) | **403** |
| `/.gitignore` | **200** | **403** |
| `/assets/js/` | **200** (directory listing) | **403** |
| `/system/ajax.php` | 405 (GET) | 405 (GET) — must stay open |

`system/.htaccess` uses a **whitelist**: `Require all denied`, then `Require all granted` for `ajax.php` alone. With a blacklist (“block config.php”), every file added tomorrow would be **open by default**. Case in point: `system/rules.php` was added to this repository afterwards and **was born closed without a single extra line**. In security, the direction of the default matters more than the rule itself.

The second layer lives in PHP: every file starts with `if (!defined('CY_APP')) { http_response_code(403); exit; }`. On a server that doesn't read `.htaccess` (nginx), that is the only defence.

Security headers were **entirely absent** as well; `ajax.php` added `nosniff` only to its own JSON responses while the HTML page was wide open. Now: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and `Content-Security-Policy`.

> **`'unsafe-inline'` is deliberately allowed in the CSP.** The `CyValidation.init(...)` call at the end of `index.php` and Bootstrap's inline styles don't work without it; the right fix is nonces, but this repository aims to stay a single-folder, copy-pasteable example. The CSP was still not left empty: `default-src 'self'` prevents the page from loading scripts or styles from **any external host** — the easiest route for injected code to exfiltrate data.

### Session security

**Session fixation worked** — it was tried:

```
Cookie: PHPSESSID=saldirganinsectigikimlik1234
→ The server accepted this FABRICATED id, started a session,
  minted a CSRF token inside it, and the endpoints returned 200.
```

After the fix, the same request:

```
→ Set-Cookie: PHPSESSID=clq8p1s19fflml79nkaqnq94pd; path=/; HttpOnly; SameSite=Lax
  (fabricated id rejected, server issued its own)
```

* `session.use_strict_mode = 1` — ids the server did not generate are refused. **This is the actual defence.**
* `httponly` — even with an XSS hole, JavaScript cannot read the cookie.
* `samesite=Lax` — the cookie is not attached to requests triggered by other sites (the browser-level first line against CSRF; the token is the second).
* `secure` — switched on automatically under HTTPS (hard-coding `true` would have made sessions impossible on localhost).

> **An honest note on `session_regenerate_id()`:** the classic advice is “regenerate after login”, because the real risk is a session the attacker already knows **later gaining privilege**. This project has no login, so there is no “moment of privilege” to protect. The id is still regenerated when `csrf_token()` mints the first token — “an empty session and a session carrying data shouldn't share an id” is cheap. But what closes fixation here is `use_strict_mode`, not `regenerate_id`.

### CSRF: 419 → 403

CSRF rejection used to return **419** (a Laravel invention, not a standard). Measured: **the Apache in this setup does not recognise 419 and silently converts the response to 500.** So a “your session expired” error reached the client as “the server crashed”. Now it is **403**: standard, and semantically correct — the request was understood but not authorised.

### Leak checks — all clean

These were measured too, and no problem was found:

* **XSS:** `<script>alert(1)</script>` in `full_name` → **422** (the `\p{L}` pattern blocks it). Since stored data is never returned in any response, there is no surface for stored XSS either.
* **Data leakage:** No endpoint returns records at all. E-mail, phone and `password_hash` live only in the database; searched for in responses, not found.
* **Scale:** at 100,000 rows, `check_email` medians **~6-7 ms**; `EXPLAIN` reports `type=const, key=uniq_submissions_email, rows=1` — the index is used.

---

## 7. API endpoints

All on `system/ajax.php`, via **POST**, CSRF token required.

| `action` | Input | Success | Errors |
|----------|-------|---------|--------|
| `check_username` | `username` | `200` `{available, reason}` | `403` `429` |
| `check_email` | `email` | `200` `{available, reason}` | `403` `429` |
| `submit` | All form fields | `200` `{success, id}` | `422` (field errors) · `409` (race) · `403` · `429` |

These are the **only** endpoints. There is no endpoint that **reads** records: the `submissions` table is only written to, and read solely for uniqueness comparisons; no response ever returns a list of records.

### HTTP status codes and what they mean

| Code | When | Why this code |
|------|------|---------------|
| `200` | Success — **including live checks** | “That name is taken” is an **answer**, not an error. The client reads the `available` field. |
| `400` | Unknown or empty `action` | Request not understood |
| `403` | Missing/invalid CSRF token | Understood but not authorised. **Don't use 419** — this Apache turns it into 500 (measured) |
| `405` | Non-POST method | Method not supported |
| `409` | Race: the `UNIQUE` index refused | Conflict — the request was valid but the resource state changed |
| `422` | Field validation errors | Well-formed request, **unprocessable content**. All errors come back **at once** in `errors{}` |
| `429` | Rate limit exceeded | With a `Retry-After` header and a `retry_after` field |

**Why return all errors at once?** Putting the user in a “fix one error, discover the next” loop is a poor experience in a long form. Measured: a request with seven broken fields returns **all seven errors** in a single response.

---

## 8. Database schema

```sql
CREATE TABLE `submissions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(100) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,   -- the SAME number as 'max' in rules.php
  `username`      VARCHAR(20)  NOT NULL,
  `phone`         VARCHAR(20)  DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,   -- password_hash() output
  `birth_date`    DATE         DEFAULT NULL,
  `message`       VARCHAR(500) DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_submissions_email`    (`email`),      -- last link of the
  UNIQUE KEY `uniq_submissions_username` (`username`)    -- TOCTOU chain
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Sample data: 60 records — and what's in `password_hash`?

The setup file ships with 60 sample records. **Why?** A project installed with an empty table cannot demonstrate its own most important feature: the **live uniqueness check cannot be tried** — with nothing to collide with, every username and every e-mail comes back “available”, so whoever downloads the project never sees the side of the form that answers while you type. With 60 records you get the red “already taken” for `ahmet` and the green “✓ Available” for `ahmet2` on your very first try.

These records are **never listed in the UI**. The table is never read for display; it is only the data set that `email_exists()` / `username_exists()` compare against.

**What went into `password_hash`?** All 60 rows carry the **same, real bcrypt digest** — the hash of `OrnekParola123`. That is fine for three reasons:

1. **This is not a login system.** `password_verify()` is never called anywhere; the column opens no door. It exists to demonstrate one principle: *passwords are never stored in plain text.*
2. **The password is public and intentionally so** — it is written right here and in the SQL file's comments. There is no secret to leak.
3. **We did not write plain text.** Even in sample data, plain text in that column would teach the wrong pattern to someone learning by copying.

> **Do not do this in a real system:** giving many users the same hash leaks the fact that those users share a password. `password_hash()` generates a random **salt** on every call, so even the same password yields a different hash each time. The repetition here exists only because SQL cannot call `password_hash()`. The application itself (`system/ajax.php`) calls it on every save — so **real records entered through the form each get a unique hash.**

---

## 9. Installation

```bash
cd C:/xampp/htdocs
git clone https://github.com/CilginYazilim/form-validation-example.git

mysql -u root -p < form-validation-example/cy_validation.sql
```

Or **phpMyAdmin → Import → `cy_validation.sql` → Go**.

Then: **`http://localhost/form-validation-example/`**

> All you need is PHP 8.0+, MySQL 5.7+ and Apache. No Composer, no npm, no build step — jQuery and Bootstrap ship inside the repository.
>
> **Set `APP_DEBUG` to `false` in `system/config.php` before going live.**

---

## 10. File layout

```
form-validation-example/
├── index.php                   ← Form; passes rules to JS
├── cy_validation.sql            ← Database setup + 60 sample records
├── .htaccess                     ← No directory listing, .sql/.md denied, security headers
├── system/
│   ├── .htaccess                  ← Whitelist: only ajax.php is open
│   ├── config.php                  ← Session hardening, PDO, rate-limit settings
│   ├── rules.php                    ← ⭐ SINGLE SOURCE OF RULES (read by PHP and JS)
│   ├── function.php                  ← Validators, CSRF, rate limiting, data access
│   └── ajax.php                       ← check_username / check_email / submit
└── assets/
    ├── css/cilginyazilim.css           ← Shared brand design (don't touch)
    ├── css/style.css                    ← Page-specific styles
    └── js/validation.js                  ← Live validation, strength meter, counter
```

### What each function does

| Function | File | Job |
|----------|------|-----|
| `validation_rules()` | `rules.php` | Returns all rule definitions — **the single source** |
| `rule_check()` | `rules.php` | Applies the shared part: required → length → pattern |
| `rule_message()` | `rules.php` | Fills the `{min}` / `{max}` / `{age}` placeholders |
| `client_rules()` | `rules.php` | The JSON form of the rules sent to the client |
| `validate_*()` | `function.php` | Field-specific procedure (normalisation, dates, URLs) + `rule_check()` |
| `require_csrf()` | `function.php` | Validates the token, **403** if absent |
| `rate_limit()` | `function.php` | Sliding-window counter, **429** + `Retry-After` when exceeded |
| `email_exists()` / `username_exists()` | `function.php` | Uniqueness query (live check and final check share it) |
| `handle_submit()` | `ajax.php` | Validates every field, collects errors **at once**, saves, maps `23000` → **409** |
| `ruleCheck()` | `validation.js` | The exact client-side counterpart of `rule_check()` |
| `codePointLength()` | `validation.js` | Counts **code points** — not `.length` (the fix for astral drift) |
| `passwordScore()` | `validation.js` | Meter score; cannot exceed “Weak” until the mandatory rule is met |

---

## 11. Customising

**To change a limit** → `system/rules.php` only. PHP, JavaScript and the HTML `maxlength` move **together**:

```php
'full_name'  => [ 'min' => 2, 'max' => 40, … ],   // 40 instead of 100
'birth_date' => [ 'min_age' => 21, … ],            // 21 instead of 18
```

**To add a field:**
1. `rules.php` → rule definition
2. `function.php` → `validate_new_field()` (if it needs a procedural part)
3. `ajax.php` → add it to the list inside `handle_submit()`
4. `index.php` → `<input id="new_field">` + `data-error-for="new_field"`
5. `validation.js` → `validators.new_field` + `fieldOrder`
6. SQL → column

**To change the rate limits** → the `RATE_LIMIT_*` constants in `system/config.php`.

**To run behind a proxy** → `client_fingerprint()` deliberately reads only `REMOTE_ADDR`; `X-Forwarded-For` is not read, because that header can be forged by the client and would let anyone disable the limit with a single line. If you are behind a reverse proxy, change it **deliberately**.

---

## 12. Where you'd use this

* **Sign-up / registration forms** — the project's direct subject.
* **Contact and request forms** — drop the live uniqueness check and keep the rest of the layers.
* **Event / application registration** — age limit, terms acceptance and duplicate-application blocking come built in.
* **Newsletter subscription** — e-mail uniqueness plus a rate limit are exactly the two things you need.
* **An admin panel's “add user” screen** — the live username check fits it directly.
* **Teaching material** — a working example, with its measurements written down, for explaining why client-side validation is not security.

---

## License

MIT — download and use it however you like.

<div align="center">

**[Çılgın Yazılım](https://cilginyazilim.com)** &nbsp;·&nbsp; [github.com/CilginYazilim/form-validation-example](https://github.com/CilginYazilim/form-validation-example)

Copyright © Çılgın Yazılım (cilginyazilim.com)

</div>
