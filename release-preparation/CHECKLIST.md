# Recently Bought This for WooCommerce

## Kandydat 1.0.2

Weryfikacja lokalna z 25 listopada 2026 r. na syntetycznych danych. Przed publikacją właściciel sprawdzi kandydata na fizycznym telefonie.

### Audyt frontendu

- Publiczny Ordin.pl używa Storefront 4.6.2. `.storefront-handheld-footer-bar` miała `position: fixed`, wysokość 68,8 CSS px przy szerokościach 320 i 390 px oraz `z-index: 9999`. Pasek wyszukiwania i koszyka znikał przy 820 i 844 px, także w ustawieniu 844 × 390. W 1.0.1 powiadomienie miało ten sam poziom stosu i dolny margines 12 px. [Ordin.pl](https://ordin.pl/) · [Kod paska Storefront](https://github.com/woocommerce/storefront/blob/trunk/assets/css/woocommerce/woocommerce.scss)
- W publicznym jasnym motywie ciemna preferencja systemu uruchamiała grafitową paletę 1.0.1 przez `color-scheme: light dark` i `light-dark()`. W syntetycznym powiadomieniu wykorzystującym CSS strony kolory po animacji zachowywały kontrast. Podczas wygaszania samej treści 1.0.1 przy kryciu 50% kontrast nazwy na grafitowym tle wynosi około 3,17:1 według obliczenia z kolorów CSS. Stan widziany na fizycznym telefonie pozostaje do sprawdzenia.
- W Morrow Kiln powiadomienie z przybliżonym czasem zmalało z 300 × 102 do 280 × 73,4 CSS px. Miniatura zmalała z 80 do 52 px, a obszar zamknięcia wzrósł z 28 × 28 do 44 × 44 px. Powiadomienie zachowało margines 10 px nad paskiem 81 px na telefonie i 53 px na tablecie. W 1.0.1 zachodziło na te paski o 69 i 41 px.
- Po animacji tekst i ikona zamknięcia osiągnęły 15,89:1 w jasnej palecie i 13,74:1 w ciemnej. Nazwa produktu osiągnęła odpowiednio 6,71:1 i 8,42:1. Test Chromium z wymuszonym automatycznym przyciemnieniem sprawdził również piksele powiadomienia.
- Pomiar w Chromium objął 3000 węzłów katalogu oraz 600 zdarzeń przewijania w 60 klatkach. Średnia obsługa klatki wyniosła 0,15 ms, 95. percentyl 0,20 ms i maksimum 0,30 ms. W tym przebiegu wykonano 180 odczytów geometrii i zero nowych wywołań `querySelectorAll()`.

### Kontrole wydania

| Kontrola | Wynik |
| --- | --- |
| Chromium, Firefox, WebKit | PASS. Paski 50/80/120 px i 52 px na tablecie, wykrywanie po zmianach DOM, sticky, zmiana orientacji, nakładki, safe-area, 320–1440 px i wszystkie narożniki oraz tryby. |
| Pełna regresja Chromium | PASS. Storefront, koszyk i wyszukiwarka, checkout, zoom 200%/400%, fokus, klawiatura, cookies, przywracanie po `pagehide`/`pageshow`, reduced motion i wymuszony kontrast. |
| WordPress/WooCommerce/PHP | PASS. WordPress 7.0.9/WooCommerce 11.2.2/PHP 8.2.35 i WordPress 7.1.5/WooCommerce 11.3.1/PHP 8.3.36 oraz 8.4.28. `WP_DEBUG` i `WP_DEBUG_LOG` aktywne w serwerach testowych, zero wpisów dotyczących wtyczki. |
| Zakupy i tłumaczenia | PASS. CPT oraz HPOS z synchronizacją i bez niej, kwalifikacja płatności, zwroty, prywatność, en_US, pl_PL i czeskie własne teksty. Multisite sprawdzono na witrynie głównej i dwóch witrynach w podkatalogach. |
| Settings API | PASS. Uprawnienia, nonce, trzy tryby, walidacja wartości ręcznej 0–300 px i przekazanie ustawienia do frontendu. |
| ZIP | PASS. Świeża instalacja, aktualizacja z 0.4.0, 1.0.0 i 1.0.1 oraz odinstalowanie. Aktualizacje zachowują dotychczasowe opcje. Domyślne wartości nowych pól to Automatic i 0 px. |
| Kontrola statyczna | PASS. PHP lint na trzech wersjach, `node --check`, WPCS z dotychczasowymi wyjątkami nazw plików i układu klas, PHPCompatibilityWP oraz Plugin Check 2.2.0 przy aktywnym i wyłączonym WooCommerce. Plugin Check zgłosił 0 błędów i 0 ostrzeżeń. |
| Fizyczne urządzenie | NOT RUN. Kontrola właściciela przed publikacją. |

### Paczka i grafiki

[Manifest](manifest.json) zawiera sumy kontrolne ZIP-a z siedmioma plikami i ośmiu grafik katalogowych. Dwa niezależne przebiegi narzędzia budującego z Pythonem 3.13.12 i zlib 1.3.1 dały identyczny ZIP. Python z inną implementacją zlib może wytworzyć inny zapis kompresji przy tej samej zawartości.

```powershell
python release-preparation/build-zip.py release-preparation/recently-bought-this-for-woocommerce-1.0.2.zip
```

- [Nowy panel ustawień](../wordpress-org-assets/screenshot-2.png)
- [Widok mobilny nad paskiem nawigacji](../wordpress-org-assets/screenshot-4.png)
- [Plansza porównawcza 1.0.1 i 1.0.2](before-after-1.0.2.png) do oceny przez właściciela

Zrzuty desktopowe `screenshot-1.png` i `screenshot-3.png` porównano piksel po pikselu z 1.0.2. W katalogu WordPress.org aktualizacji dla tego wydania wymagają `screenshot-2.png` i `screenshot-4.png`. Przed publikacją wymagana jest oddzielna zgoda właściciela na WordPress.org i GlotPress.

## Archiwum kontroli 1.0.1

Kandydat 1.0.1 dodał pięć opcjonalnych pól tekstowych. W tym wydaniu w katalogu WordPress.org zaktualizowano `screenshot-2.png`.

## Archiwum kontroli 1.0.0

Kandydat do oceny właściciela. Macierz zweryfikowana 23 listopada 2026 r. w lokalnym Dockerze na danych syntetycznych.

## Zamknięte kontrole

- [x] Kod, nagłówek i dokumentacja mają wersję 1.0.0. Wersje JS i CSS są zgodne z wersją wtyczki. Slug, główny plik wtyczki i autorstwo zostały zachowane. Nagłówek deklaruje zależność od WooCommerce i licencję GPLv2 lub nowszą. [Nagłówek wtyczki](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/) · [Format readme](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) · [Nagłówki WooCommerce](https://developer.woocommerce.com/docs/contribution/contributing/version-support-policy/)
- [x] Minimalne wersje to WordPress 7.0, WooCommerce 11.2 i PHP 8.2. Testy instalacyjnego ZIP objęły poniższe kombinacje. [Archiwum WordPressa](https://wordpress.org/download/releases/) · [Wydania WooCommerce](https://developer.woocommerce.com/releases/)
- [x] Przetestowano CPT, HPOS z synchronizacją i HPOS bez synchronizacji, w tym nowe zamówienie zapisane wyłącznie w HPOS. Sprawdzono COD, BACS, CHEQUE, płatności online, zamówienia zerowe, częściowe i pełne zwroty.
- [x] Sprawdzono uprawnienia, nonce, walidację sześciu opcji, sanitizację i escaping. Publiczny HTML i DOM nie zawierają danych kupujących, identyfikatorów ani dokładnych dat zamówień. Aktualny publiczny tytuł katalogowy zastępuje nazwę pozycji zamówienia również dla produktów personalizowanych. Warianty używają produktu nadrzędnego.
- [x] Playwright sprawdził jQuery UI 1.13.3 i 1.14.2, Tab, Shift+Tab, Escape, fokus, checkout, ponowną inicjalizację, rotację, cztery narożniki, tryby czasu, 320 px, mobile, zoom, tryb ciemny, forced-colors, reduced motion, nawigację wstecz, odmowę cookies i kilka kart. W multisite osobno sprawdzono zakres cookies witryny głównej i dwóch witryn w podkatalogach.
- [x] Oficjalną wersję 0.4.0 zaktualizowano instalacyjnym ZIP-em bez dezaktywacji wtyczki. Zachowało się pięć opcji i domyślny tryb czasu `none`. Aktualizacja z etapu B zachowała wszystkie sześć opcji. Świeża instalacja ustawiła wszystkie wartości domyślne, a odinstalowanie na pojedynczej witrynie usunęło wszystkie sześć opcji.
- [x] POT i polski PO obejmują 31 komunikatów. ZIP zawiera polski katalog `.l10n.php`. Sprawdzono angielski i polski, polskie znaki, tekst zamknięcia, etykiety czasu, ustawienia i komunikat o zależności. Testowy pakiet w katalogu językowym WordPressa miał pierwszeństwo nad tłumaczeniem z ZIP. [Rejestr tłumaczeń w WordPressie](https://developer.wordpress.org/reference/classes/wp_textdomain_registry/set_custom_path/)
- [x] Plugin Check 2.2.0 przeszedł kontrolę statyczną i runtime przy włączonym oraz wyłączonym WooCommerce. W czterech przebiegach uzyskano zero błędów i ostrzeżeń. Przy wyłączonym WooCommerce sprawdzono także panel, frontend, REST, AJAX i WP-CLI. Log debugowania był wolny od komunikatów wtyczki podczas regresji.
- [x] PHP lint przeszedł na PHP 8.2, 8.3 i 8.4. PHPCompatibilityWP przeszedł dla `8.2-`. WPCS przeszedł z istniejącymi wyłączeniami `WordPress.Files.FileName` i `Generic.Files.OneObjectStructurePerFile` dla historycznych nazw plików i dwóch klas w `classes.php`. Składnia JavaScript i skryptów Pythona oraz `git diff --check` przeszły.
- [x] Dokumentacja opisuje działanie ustawień, kwalifikację płatności, zwroty, HPOS, zakres danych i wpływ cache. Dawny link PayPal zwrócił HTTP 403 przy sprawdzeniu i został usunięty. Konto autora `piotr-pesta` zostało zweryfikowane. [Profil autora](https://profiles.wordpress.org/piotr-pesta/)
- [x] ZIP zawiera siedem wskazanych plików pod jednym katalogiem `recently-bought-this-for-woocommerce/`. Sprawdzono poprawność archiwum, listę plików, wersję i SHA-256. Biblioteka runtime zawiera wbudowane tłumaczenie. Kod testowy, źródła tłumaczeń i materiały katalogowe pozostają w repozytorium.
- [x] Dwa banery i dwie ikony wyrenderowano z oryginalnych wektorów w rozmiarach docelowych. Cztery zrzuty wykonano w Chromium przez Playwright z działającego WooCommerce i zainstalowanego kandydata. Oceniono każdy PNG i obie plansze wizualnie. Potwierdzono wymiary, rozmiary, format, czytelność i brak metadanych EXIF, tEXt oraz ICC. [Wymagania katalogu](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [x] Manifest zawiera dokładne ścieżki `trunk/`, `tags/1.0.0/` i `assets/`, a także wymiary, MIME, rozmiary, SHA-256 i przeznaczenie grafik. Obecne materiały sprawdzono przez odczyt karty i SVN. Imiona ze starych grafik zasłonięto na planszy porównawczej. [Karta wtyczki](https://wordpress.org/plugins/recently-bought-this-for-woocommerce/) · [Katalog SVN](https://plugins.svn.wordpress.org/recently-bought-this-for-woocommerce/assets/)

| WordPress | WooCommerce | PHP | jQuery UI | Przebieg |
| --- | --- | --- | --- | --- |
| 7.0.9 | 11.2.2 | 8.2.35 | 1.13.3 | Migracja z oficjalnego 0.4.0 i regresja |
| 7.1.5 | 11.3.1 | 8.3.36 | 1.14.2 | Migracja z etapu B, regresja, Plugin Check i JIT |
| 7.1.5 | 11.3.1 | 8.4.28 | 1.14.2 | Świeża instalacja, regresja, odinstalowanie |

## Płatności online

Wtyczka nadal wymaga `date_paid` i `transaction_id` dla zamówień online. Przejrzano ścieżkę zakończenia płatności w Stripe i PayPal Standard. Obie przekazują identyfikator transakcji do mechanizmu WooCommerce, który zapisuje datę płatności. Bramki zapisujące potwierdzenie wyłącznie we własnych metadanych mogą zostać pominięte. Testy dotyczyły syntetycznych zamówień, a przegląd integracji oparto na ich kodzie źródłowym. [Stripe](https://github.com/woocommerce/woocommerce-gateway-stripe/blob/develop/includes/abstracts/abstract-wc-stripe-payment-gateway.php#L3407) · [PayPal Standard](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/gateways/paypal/includes/class-wc-gateway-paypal-ipn-handler.php) · [WooCommerce WC_Order](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-order.php)

## Paczka i materiały

Zbuduj lokalną kopię na zatwierdzonym commicie 1.0.0 poleceniem poniżej. Porównaj rozmiar i SHA-256 z [manifestem 1.0.0](https://github.com/Pantsoffski/Recently-Bought/blob/d1bb76b0d5c2afe8ebca75576225b122a05e2011/release-preparation/manifest.json).

```powershell
python release-preparation/build-zip.py release-preparation/recently-bought-this-for-woocommerce-1.0.0.zip
```

- [Plansza OLD vs NEW](old-vs-new.png)
- [Plansza nowego zestawu](new-catalog-set.png)
- [Finalne grafiki katalogowe](../wordpress-org-assets/)

## Wymagana osobna zgoda właściciela

- [ ] Zatwierdzić kandydata, grafiki, zakres wspieranych wersji i termin wydania.
- [ ] Przed publikacją porównać aktualny SVN z manifestem. Przygotować siedem plików wydania w `trunk/` i w `tags/1.0.0/`. Umieścić osiem PNG w `assets/` i zweryfikować ich kolejność według sekcji Screenshots. Opublikować dopiero po akceptacji właściciela.
- [ ] Osobno zatwierdzić publikację tłumaczeń w GlotPress.
- [ ] Po aktualizacji z 0.4.0 uzgodnić z administratorem instalacji opróżnienie cache stron i CDN. Stare publiczne HTML może zawierać imiona kupujących. Po opróżnieniu sprawdzić odpowiedź sklepu dla niezalogowanego użytkownika.
