# JustSilver – WooCommerce-plugin

Gør en almindelig WordPress/WooCommerce-shop til en sølvbutik.
Sølvvarerne er helt almindelige WooCommerce-varer, så de bruger shoppens **eksisterende kurv, checkout,
betaling, fragt, mails og kundekonti** – pluginet tilføjer det, der er særligt for sølv.

## Hvad pluginet tilføjer

| Funktion | Hvor |
|---|---|
| **"Sølv"-fane på varer**: vægt, finhed, type, præger, årgang, stand, tillæg | Varer → Rediger vare |
| **Priser efter dagens sølvkurs**: pris = vægt × finhed × kurs × (1 + tillæg). Ny kurs → alle sølvvarer genberegnes i baggrunden (Action Scheduler, 100 ad gangen) | WooCommerce → JustSilver |
| **Automatisk sølvkurs** fra metalpriceapi.com hver time / 2× dagligt / dagligt. Kurser der springer > 20 % afvises (fejlsikring) | WooCommerce → JustSilver |
| **Filtre til mange varer**: type, finhed, præger og vægt synkroniseres til globale egenskaber, så WooCommerce' "Filtrer efter egenskab"-blokke virker | Automatisk |
| **Butikker** med adresse, åbningstider, lukkedage, varighed og kapacitet | Sølv-bookinger → Butikker |
| **Tidsbestilling** (butik → dag → tid → oplysninger), "Book tid og se den i butikken" på varesiden, låsning mod dobbeltbooking, mail til kunde og butik | Side med `[justsilver_booking]` |
| **Afhentning i butik**: butikkerne synkroniseres til WooCommerce' "Lokal afhentning" i checkout | Automatisk |
| **Sølv-forside** med hero, dagspris, kategorier, populære varer og butikker | Side med `[justsilver_forside]` |
| **Sølv-design**: kølige sølvtoner på sølvkategorier og -varer (resten af shoppen påvirkes ikke) | Automatisk |
| **Tegnede sølvbilleder** (barre/mønt/granulat/smykke/bestik/antik) for varer uden foto | Automatisk |
| **CSV-import/-eksport** med sølvfelter som kolonner | Varer → Importér |
| Pris-ticker `[justsilver_pris]` (fx i menuen) | Shortcode |

## Installation

1. Byg zip: `powershell -File wp-plugin/build-zip.ps1` → `justsilver.zip`
2. WordPress-admin → Plugins → Tilføj ny → Upload plugin → vælg zip → Aktivér.
   *(Test først på en staging-kopi af sitet.)*
3. Aktivering opretter: kategorien **Sølv** med underkategorier (genbruger en eksisterende "Sølv"),
   siderne **Sølv** og **Book tid**, og egenskaberne Sølvtype/Finhed/Præger/Vægt. Intet eksisterende overskrives.
4. **WooCommerce → JustSilver**: sæt dagspris (eller API-nøgle), standardtillæg og hvem der modtager bookinger.
5. **Sølv-bookinger → Butikker**: opret jeres rigtige afdelinger med åbningstider og lukkedage.
6. Læg siderne **Sølv** og **Book tid** i menuen.

## Masseimport af varer

WooCommerce → Varer → **Importér**. Brug [`varer-eksempel.csv`](varer-eksempel.csv) som skabelon.
Sølvkolonnerne genkendes automatisk:

| Kolonne | Eksempel | Bemærkning |
|---|---|---|
| `ns_is_silver` | `yes` | Skal være `yes` for at varen prissættes som sølv |
| `ns_kind` | `barre` | barre, moent, granulat, smykke, bestik, antik (eller "Barre", "Mønt"…) |
| `ns_weight` | `100` | gram |
| `ns_purity` | `999` | 999, 925, 830 … (eller 0.999) |
| `ns_mint` | `Valcambi` | |
| `ns_premium` | `18` | % over dagspris; tom = standardtillæg |
| `ns_pricing` | `spot` | `spot` = dagspris, `fast` = brug "Normal pris"-kolonnen |
| `ns_year`, `ns_condition`, `ns_view_in_store` | `2026`, `ny`, `yes` | |

## Udviklere

- Lokal test: `cd wp-dev; docker compose up -d` → http://localhost:8080 (bruger `admin` / `admin` – kun lokalt).
  Første gang: se kommandoerne i `wp-dev/setup.sh`.
- WP-CLI: `wp justsilver price 10500`, `wp justsilver fetch-price`, `wp justsilver reprice`, `wp justsilver seed --stores`
- Skabeloner kan overskrives i temaet: `{tema}/justsilver/landing.php`, `booking-form.php`, `booking-done.php`
- Hooks:
  - `justsilver_fetch_spot_price` – returnér DKK pr. troy ounce for at bruge en anden kurs-udbyder
  - `justsilver_computed_price` – justér den beregnede pris (fx afrunding til 5 kr.)
  - `justsilver_price_updated`, `justsilver_booking_created` – actions
- Kræver WordPress 6.5+, PHP 8.1+, WooCommerce 8.5+. Kompatibel med HPOS og checkout-blokke.
