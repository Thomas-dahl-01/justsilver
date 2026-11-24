# JustSilver

Et WordPress/WooCommerce-plugin, der gør en almindelig webshop til en **sølvbutik**:
priser efter dagens sølvkurs, automatisk kurs-opdatering, butikker med åbningstider og
**tidsbestilling**, så kunden kan se varen i hånden, før de køber.

> JustSilver er en opdigtet butik. Alle navne, adresser, telefonnumre og e-mails i projektet er fiktive.

## Teknologi

Bygget på den mest udbredte webshop-stak i Danmark:

- **WordPress** + **WooCommerce** (PHP 8.1+), kører på et almindeligt webhotel
- Sølvvarer er normale WooCommerce-varer, så shoppens eksisterende kurv, checkout, betaling
  (fx WooCommerce Payments, ViaBill, MobilePay), fragt (fx Pakkelabels/Shipmondo), mails og kundekonti genbruges
- Baggrundsjob via WooCommerce' **Action Scheduler**, kurs-opdatering via **WP-Cron**
- Kompatibel med WooCommerce' nye ordretabeller (HPOS) og checkout-blokke

## Funktioner

- **Sølv-fane på varer**: vægt, finhed (999, 925, 830 …), type, præger, årgang, stand og tillæg
- **Pris = vægt × finhed × sølvkurs × (1 + tillæg)**. Ny kurs → alle sølvvarer genberegnes i baggrunden, 100 ad gangen
- **Automatisk sølvkurs** fra metalpriceapi.com hver time; spring på over 20 % afvises som fejl
- **Filtre til tusindvis af varer**: type, finhed, præger og vægt bliver WooCommerce-egenskaber
- **Butikker** med åbningstider, lukkedage, varighed og kapacitet – også brugt som afhentningssteder i checkout
- **Tidsbestilling**: butik → dag → tid → oplysninger, beskyttet mod dobbeltbooking, mail til kunde og butik
- **"Book tid og se den i butikken"** direkte fra varesiden
- **Sølv-forside**, eget sølv-design og tegnede sølvbilleder til varer uden foto
- **CSV-import/-eksport** med sølvkolonner og **WP-CLI**-kommandoer

## Kom i gang

| | |
|---|---|
| Installation og brug | [wp-plugin/README.md](wp-plugin/README.md) |
| Selve pluginet | [wp-plugin/justsilver/](wp-plugin/justsilver/) |
| Lokal test-WordPress (Docker) | [wp-dev/](wp-dev/) |

Hurtig lokal demo (kræver Docker):

```sh
cd wp-dev
docker compose up -d
MSYS_NO_PATHCONV=1 sh setup.sh   # installerer WordPress, WooCommerce, pluginet og 317 demovarer
```

Åbn http://localhost:8080 – siderne **Sølv** og **Book tid** ligger i menuen.
