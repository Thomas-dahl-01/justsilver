# JustSilver – WooCommerce-plugin

Gør en almindelig WordPress/WooCommerce-shop til en sølvbutik.
Sølvvarerne er helt almindelige WooCommerce-varer, så de bruger shoppens **eksisterende kurv, checkout,
betaling, fragt, mails og kundekonti** – pluginet tilføjer det, der er særligt for sølv.

## Installation

1. Byg zip: `powershell -File wp-plugin/build-zip.ps1` → `justsilver.zip`
2. WordPress-admin → Plugins → Tilføj ny → Upload plugin → vælg zip → Aktivér. *(Test først på en staging-kopi.)*
3. Aktivering opretter kategorien **Sølv** med underkategorier, siderne **Sølv** og **Book tid** (som blokke)
   og egenskaberne Sølvtype/Finhed/Præger/Vægt. Eksisterende kategorier og sider genbruges – intet overskrives.
4. **WooCommerce → JustSilver**: sæt dagspris (eller API-nøgle), tillæg, opkøbsmargin, reservation og påmindelser.
5. **Sølv-bookinger → Butikker**: opret butikkerne med åbningstider og lukkedage.
6. Læg siderne **Sølv** og **Book tid** i menuen.

## Blokke og shortcodes

Alle funktioner findes både som blok (søg efter "sølv" i blokeditoren) og som shortcode:

| Blok | Shortcode | Viser |
|---|---|---|
| Sølv-forside | `[justsilver_forside]` | Hero med live-pris, kategorier, prisgraf, populære varer, sælg-beregner, prisalarm og butikker |
| Tidsbestilling | `[justsilver_booking]` | Butik → dag → tid → oplysninger, med reservation af varer |
| Graf over sølvprisen | `[justsilver_prisgraf dage="90"]` | Interaktiv graf (30/90/365 dage) |
| Prisalarm | `[justsilver_prisalarm]` | "Giv mig besked, når sølv koster X kr/g" |
| Sælg dit sølv | `[justsilver_saelg]` | Øjeblikkeligt tilbud ud fra vægt og finhed |
| Sølvpris-ticker | `[justsilver_pris enhed="g"]` | Dagens pris med ændring siden i går |

## Sådan virker det

| Funktion | Detaljer |
|---|---|
| **Pris** | Vægt × finhed × kurs × (1 + tillæg). Sættes ved hver gemning (admin, CSV, REST, CLI). Ny kurs genberegner alle spot-varer i baggrunden (Action Scheduler, 100 ad gangen). Varer kan også have fast pris. |
| **Kurs** | Manuelt eller fra metalpriceapi.com (hver time / 2× dagligt / dagligt). Spring over 20 % afvises. Historikken gemmes og bruges i grafen. |
| **Reservation** | Ved booking tages 1 stk. af hver valgt vare af lageret. Aflyst/udeblevet → tilbage på lager. Gennemført → tæller som solgt. Dagligt job frigiver besøg, der ikke blev gennemført (efter X dage). |
| **Mails** | Kunde og butik får bekræftelse med kalenderfil (.ics). Påmindelse dagen før (fra kl. X). Aflysningslink i mailene. Prisalarmer sendes én gang og slettes. |
| **Filtre** | Type, finhed, præger og vægt synkroniseres til globale egenskaber, så WooCommerce' "Filtrer efter egenskab" virker på tusindvis af varer. |
| **Afhentning** | Butikkerne synkroniseres til WooCommerce' "Lokal afhentning" i checkout. |
| **Design** | Sølv-design på sølvkategorier og -varer (resten af shoppen påvirkes ikke). Animationer kan slås fra og respekterer "reducer bevægelse". |
| **Sprog** | Engelsk kildetekst med dansk oversættelse i `languages/`. Nye sprog laves ud fra `justsilver.pot`. |

## Masseimport af varer

WooCommerce → Varer → **Importér**. Brug [`varer-eksempel.csv`](varer-eksempel.csv) som skabelon.

| Kolonne | Eksempel | Bemærkning |
|---|---|---|
| `ns_is_silver` | `yes` | Skal være `yes` for at varen prissættes som sølv |
| `ns_kind` | `barre` | barre, moent, granulat, smykke, bestik, antik (eller "Bar", "Mønt" …) |
| `ns_weight` | `100` | gram |
| `ns_purity` | `999` | 999, 925, 830 … (eller 0.999) |
| `ns_mint` | `Valcambi` | |
| `ns_premium` | `18` | % over dagspris; tom = standardtillæg |
| `ns_pricing` | `spot` | `spot` = dagspris, `fast` = brug "Normal pris" |
| `ns_year`, `ns_condition`, `ns_view_in_store` | `2026`, `ny`, `yes` | |

## Udviklere

- **Lokal test:** `cd wp-dev; docker compose up -d; MSYS_NO_PATHCONV=1 sh setup.sh` → http://localhost:8080 (`admin` / `admin`, kun lokalt)
- **Unit tests:** `cd wp-plugin; composer install; vendor/bin/phpunit`
- **Integrationstest** (mod den lokale WordPress): `cd wp-dev; docker compose run --rm -T -v "$PWD/smoke-test.php":/tmp/smoke-test.php cli eval-file /tmp/smoke-test.php`
- **Skærmbilleder:** `python wp-dev/screenshots.py` (kræver Playwright)
- **Oversættelse:** `wp i18n make-pot` → `python wp-plugin/tools/make-da-po.py` → `wp i18n make-mo` og `wp i18n make-json`
- **WP-CLI:** `wp justsilver price 10500`, `fetch-price`, `reprice`, `history`, `seed --stores`
- **Skabeloner** kan overskrives i temaet: `{tema}/justsilver/landing.php`, `booking-form.php`, `price-chart.php` …
- **Hooks:**
  - `justsilver_fetch_spot_price` – returnér DKK pr. troy ounce for at bruge en anden kurs-udbyder
  - `justsilver_computed_price` – justér den beregnede pris (fx afrunding til 5 kr.)
  - `justsilver_price_updated`, `justsilver_booking_created`, `justsilver_booking_status_changed` – actions
