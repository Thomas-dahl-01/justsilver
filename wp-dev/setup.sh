#!/usr/bin/env sh
# Første opsætning af lokal test-WordPress (kør fra wp-dev/ efter `docker compose up -d`).
# I Git Bash på Windows: kør med MSYS_NO_PATHCONV=1 sh setup.sh
set -e
W="docker compose run --rm -T cli"
$W core install --url=http://localhost:8080 --title="JustSilver (test)" --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
$W language core install da_DK --activate
$W option update timezone_string Europe/Copenhagen
$W rewrite structure '/%postname%/'
$W plugin install woocommerce --activate
$W language plugin install woocommerce da_DK
$W option update woocommerce_currency DKK
$W option update woocommerce_default_country DK
$W option update woocommerce_price_thousand_sep .
$W option update woocommerce_price_decimal_sep ,
$W option update woocommerce_price_num_decimals 0
$W theme install storefront --activate
$W plugin activate justsilver
$W justsilver seed --stores
# Fuld bredde på Sølv- og Book tid-siderne i Storefront
for key in silver_page booking_page; do
  id=$($W option pluck ns_settings $key | tr -d "")
  $W post meta update "$id" _wp_page_template template-fullwidth.php
done
echo "Klar: http://localhost:8080  (admin / admin)"
