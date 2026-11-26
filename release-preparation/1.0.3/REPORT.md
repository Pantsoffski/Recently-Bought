# Recently Bought This for WooCommerce 1.0.3

**PASS — weryfikacja wydania 1.0.3, 26 listopada 2026 r.** Regresja i porównanie z oficjalnym ZIP-em 1.0.2 przeszły. Deklarację kompatybilności z WordPressem 7.2 zatwierdzono na podstawie testów najnowszego dostępnego wydania RC3. [Archiwum WordPressa](https://wordpress.org/download/releases/) · [Komunikat RC3](https://wordpress.org/news/2026/11/wordpress-7-2-release-candidate-3/)

## Stan Git, SVN i katalogu przed wydaniem

Lokalny Git i zdalny HEAD wskazywały na `d7b16265b9a82c403e0af3f691c33dfb297adeb0`. WordPress.org API i SVN potwierdziły wersję 1.0.2. Ostatnia zmiana katalogu wtyczki w SVN to `r3823411`. Siedem plików oficjalnego ZIP-a odpowiadało plikom z tego commita po normalizacji końców linii. [GitHub](https://github.com/Pantsoffski/Recently-Bought/tree/d7b16265b9a82c403e0af3f691c33dfb297adeb0) · [Tag SVN 1.0.2](https://plugins.svn.wordpress.org/recently-bought-this-for-woocommerce/tags/1.0.2/) · [Oficjalny ZIP 1.0.2](https://downloads.wordpress.org/plugin/recently-bought-this-for-woocommerce.1.0.2.zip)

Testy wykonano na danych syntetycznych w odizolowanych instancjach Dockera.

## Środowiska

| Instancja | WordPress | WooCommerce | PHP | Apache | jQuery Migrate |
| --- | --- | --- | --- | --- | --- |
| Najnowsza | 7.2 RC3 | 11.3.1 | 8.6.0 ZTS | 2.4.71 | 3.6.0 |
| Minimalna | 7.0 | 11.2.0 | 8.2.35 NTS | 2.4.69 | 3.4.1 |
| Multisite, główna i dwa podkatalogi | 7.2 RC3 | 11.3.1 | 8.6.0 ZTS | 2.4.71 | 3.6.0 |

MariaDB 12.3.3, Storefront 4.6.2, jQuery 3.7.1 i WP-CLI 2.12.0. Testy przeglądarkowe uruchomiono przez Playwright 1.65.1 z Chromium 159.0.8154.6, Firefox 159.0 i WebKit 27.2. [Wydania WooCommerce](https://developer.woocommerce.com/releases/) · [Wydania PHP](https://www.php.net/ChangeLog-8.php)

## Regresja zakupów i frontendu

| Kontrola | Wynik |
| --- | --- |
| Zamówienia, produkty i zwroty | PASS na obu środowiskach. CPT, HPOS z synchronizacją i HPOS bez niej. Statusy, płatności online i offline, zamówienia zerowe i gościnne, produkty cyfrowe, warianty, niedostępne produkty, limity, częściowe i pełne zwroty. |
| Daty, teksty i ustawienia | PASS. Progi czasowe, granice dnia, zmiany czasu letniego i zimowego, strefy, neutralne komunikaty dla brakujących, przyszłych i starych dat, wszystkie 13 ustawień, walidacja, znaki Unicode i escaping. |
| Zachowanie przeglądarkowe | PASS w trzech silnikach na 7.2 RC3 oraz w Chromium na 7.0. Wszystkie rogi i tryby dolnego odstępu, paski fixed/sticky, zmiany viewportu i orientacji, safe area, checkout, klawiatura, fokus, kontrast, reduced motion oraz powiększenie 200% i 400% w Chromium. |
| Storefront | PASS. Natywny pasek dolny, koszyk i rozwijane wyszukiwanie. Na zrzutach 320 i 390 px powiadomienie ma około 10,2 px odstępu od paska. |
| Ciasteczka i prywatność | PASS. Ręczne zamknięcie, wygaśnięcie po cyklu, odmowa zapisu, odświeżenie i HTML z cache, unikalne nazwy i ścieżki dla trzech witryn na jednym hoście. Publiczne odpowiedzi bez prywatnych markerów zamówień. |
| Brak danych i zależności | PASS na obu środowiskach. Pusty sklep nie emituje powiadomienia ani jego skryptu. Wyłączone WooCommerce wyświetla komunikat administratorowi. Frontend, REST i AJAX odpowiadają. |
| WordPress 7.2 i PHP 8.6 | PASS. jQuery Migrate 3.6.0 załadowany w sklepie, widoczna ikona menu i poprawne odpowiedzi indeksu REST, typów WordPressa i produktów Store API. [WordPress 7.2 Field Guide](https://make.wordpress.org/core/2026/11/20/wordpress-7-2-field-guide/) · [WordPress i PHP 8.6](https://make.wordpress.org/core/2026/11/23/wordpress-and-php-8-6/) |

## Mechanizm recenzji

- **PASS.** Komunikat kwalifikuje się po upływie co najmniej 7 dni od pierwszej wizyty oraz po co najmniej 3 wizytach. Sprawdzono każdy próg i oba jednocześnie. Pomiar w aktualizowanej instalacji rozpoczął się przy pierwszym wejściu w ustawienia po aktualizacji.
- **PASS.** Trzy równoległe wejścia zostały zliczone. Spośród 12 równoległych kart tylko jedna otrzymała komunikat. Stan zapisano przed renderowaniem. Dwa konta administratorów oraz witryna główna i dwa podkatalogi mają niezależny stan.
- **PASS.** Krzyżyk, „Don't show again”, przejście do recenzji i powrót, kolejne wizyty, aktualizacja oraz dezaktywacja i ponowna aktywacja. Oba przyciski zamknięcia działają bez JavaScript. Nieprawidłowy lub obcy dla witryny nonce zwraca HTTP 403. Konto bez `manage_options` nie zapisuje wizyt.
- **PASS.** Mechanizm działa na ekranie ustawień. Storefront, powiadomienia zakupowe, inne ekrany administracyjne i REST nie zawierały elementów komunikatu. Formularz korzysta z natywnych elementów WordPressa i ma tłumaczoną nazwę przycisku zamknięcia.
- **PASS.** Strona WordPress.org zwróciła HTTP 200 i wyświetliła wymóg logowania do dodania opinii. Kliknięcie i powrót sprawdzono w przeglądarce z lokalnie podstawioną odpowiedzią strony recenzji. [Strona recenzji](https://wordpress.org/support/plugin/recently-bought-this-for-woocommerce/reviews/#new-post)

Z Advanced Dashboard przejęto zasady kwalifikacji, zapis przed renderowaniem i formularz z nonce witryny. Początkowe wizyty są zapisywane jako osobne wpisy metadanych użytkownika. Jednorazowe wyświetlenie rezerwuje opcja witryny z unikatowym kluczem. Wtyczka korzysta z API WordPressa i istniejących tabel. [Commit wzorcowy](https://github.com/Pantsoffski/Woocommerce-Advanced-Dashboard/commit/46bcdfe599ebf19a450f4d4affbb50d334c24de7)

**PASS — odinstalowanie.** Stan recenzji przetrwał podmianę paczki i ponowną aktywację. Pełne odinstalowanie usunęło historię wizyt oraz znaczniki wyświetlenia na obu środowiskach i we wszystkich trzech witrynach sieci testowej, także dla usuniętego konta. Ponowna instalacja rozpoczęła naliczanie od pustego stanu. [Podręcznik odinstalowywania WordPressa](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/)

## Instalacja i tłumaczenia

**PASS.** Dokładny finalny ZIP przeszedł świeżą instalację w czystej bazie i katalogu, z 13 domyślnymi ustawieniami i pustą historią recenzji. Aktualizacja z oficjalnego ZIP-a 1.0.2 zachowała aktywację i wszystkie 13 ustawień. Osobno potwierdzono zachowanie zapisanego stanu recenzji po podmianie paczki.

**PASS.** POT i polski PO obejmują 47 komunikatów, w tym wszystkie pięć nowych tekstów. ZIP zawiera polskie tłumaczenie PHP. Przetestowano język angielski, polski i angielski fallback z własnymi czeskimi komunikatami sklepu. Publiczne API tłumaczeń WordPress.org dla 1.0.2 zwróciło pustą listę dodatkowych pakietów. [API tłumaczeń](https://api.wordpress.org/translations/plugins/1.0/?slug=recently-bought-this-for-woocommerce&version=1.0.2)

## Plugin Check

| Plugin Check 2.2.0 w trybie `update` z kontrolami runtime | Oficjalne 1.0.2 | Finalne 1.0.3 |
| --- | --- | --- |
| WooCommerce aktywne | 0 błędów, 0 ostrzeżeń | 0 błędów, 0 ostrzeżeń |
| WooCommerce wyłączone | 0 błędów, 0 ostrzeżeń | 0 błędów, 0 ostrzeżeń |

## WPCS i kompatybilność PHP

PHP_CodeSniffer 3.13.6, WPCS 3.4.1 i PHPCompatibilityWP 2.1.8.

| Kontrola | Oficjalne 1.0.2 | Finalne 1.0.3 |
| --- | --- | --- |
| WPCS z dotychczasowymi wyjątkami nazw plików i układu klas | 0 błędów, 0 ostrzeżeń | 0 błędów, 0 ostrzeżeń |
| WPCS ze wszystkimi regułami | 3 błędy, 0 ostrzeżeń | Te same 3 błędy, 0 ostrzeżeń |
| PHPCompatibilityWP `8.2-` | 0 błędów, 0 ostrzeżeń | 0 błędów, 0 ostrzeżeń |

Trzy odziedziczone zgłoszenia WPCS dotyczą nazwy `classes.php`, dwóch klas w tym pliku i nazwy pliku `*-pl_PL.l10n.php` wynikającej z formatu tłumaczeń WordPressa. To dług o niskim znaczeniu dla wydania. Porównano reguły, pliki i treść zgłoszeń. PHP lint na 8.2.35 i 8.6.0, kontrola składni JavaScript i `git diff --check` przeszły.

Przy włączonym logowaniu debugowania nie odnotowano wpisów ze ścieżek wtyczki. Narzędzia i zależności emitowały deprecjację WP-CLI na PHP 8.6 przy formatowaniu wyjścia, komunikaty WooCommerce o zbyt wczesnym ładowaniu tłumaczenia oraz ostrzeżenia aktualizatora WordPressa przy zablokowanym ruchu zewnętrznym.

## Metadane i artefakty

| Pole | Wartość |
| --- | --- |
| Version | 1.0.3 |
| Stable tag | 1.0.3 |
| Tested up to | 7.2 |
| WC tested up to | 11.3.1 |
| Minimalne wymagania | WordPress 7.0, WooCommerce 11.2, PHP 8.2 |

- [Finalny ZIP](../recently-bought-this-for-woocommerce-1.0.3.zip) — 23 482 B i osiem plików. Zawartość i zgodność z testowaną paczką zostały zweryfikowane.
- [Manifest SHA-256](manifest.json) — ZIP, jego zawartość i sześć zrzutów.
- Recenzja po polsku ([desktop](review-pl_PL-desktop.png) · [mobile](review-pl_PL-mobile.png)).
- Recenzja po angielsku ([desktop](review-en_US-desktop.png) · [mobile](review-en_US-mobile.png)).
- Powiadomienie z dolnym paskiem Storefront ([320 px](storefront-mobile-320.png) · [390 px](storefront-mobile-390.png)).
