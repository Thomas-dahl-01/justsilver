<?php
/**
 * Small, mostly pure helper functions (unit tested in tests/HelpersTest.php).
 */
defined( 'ABSPATH' ) || exit;

/** Read a plugin setting. */
function ns_opt( string $key, $default = '' ) {
	$all = get_option( 'ns_settings', array() );
	return ( isset( $all[ $key ] ) && '' !== $all[ $key ] ) ? $all[ $key ] : $default;
}

/** 1234.5 -> "1.235 kr." */
function ns_dkk( $amount, int $decimals = 0 ): string {
	return number_format( (float) $amount, $decimals, ',', '.' ) . ' kr.';
}

/** 1000 -> "1 kg", 31.1 -> "31,1 g" */
function ns_grams( $grams ): string {
	$g = (float) $grams;
	if ( $g >= 1000 && fmod( $g, 1000 ) == 0 ) {
		return number_format( $g / 1000, 0, ',', '.' ) . ' kg';
	}
	return rtrim( rtrim( number_format( $g, 2, ',', '.' ), '0' ), ',' ) . ' g';
}

/** 0.999 -> "999", 0.9999 -> "999,9" */
function ns_purity_label( $purity ): string {
	return rtrim( rtrim( number_format( (float) $purity * 1000, 1, ',', '' ), '0' ), ',' );
}

function ns_kinds(): array {
	return array(
		'barre'    => __( 'Bar', 'justsilver' ),
		'moent'    => __( 'Coin', 'justsilver' ),
		'granulat' => __( 'Granules', 'justsilver' ),
		'smykke'   => __( 'Jewellery', 'justsilver' ),
		'bestik'   => __( 'Cutlery & tableware', 'justsilver' ),
		'antik'    => __( 'Antique & collectible', 'justsilver' ),
	);
}

function ns_purities(): array {
	return array(
		'0.9999' => '999,9',
		'0.999'  => __( '999 (fine silver)', 'justsilver' ),
		'0.958'  => __( '958 (Britannia)', 'justsilver' ),
		'0.925'  => __( '925 (sterling)', 'justsilver' ),
		'0.900'  => '900',
		'0.835'  => '835',
		'0.830'  => __( '830 (Danish silver)', 'justsilver' ),
		'0.800'  => '800',
	);
}

function ns_conditions(): array {
	return array(
		'ny'      => __( 'New', 'justsilver' ),
		'som_ny'  => __( 'As new', 'justsilver' ),
		'brugt'   => __( 'Used', 'justsilver' ),
		'vintage' => __( 'Vintage', 'justsilver' ),
	);
}

/** Normalise purity to a key of ns_purities() ("830", "0.83" -> "0.830"). */
function ns_normalize_purity( $value ): string {
	$f = (float) str_replace( ',', '.', (string) $value );
	if ( $f > 1 ) {
		$f = $f / 1000;
	}
	foreach ( array( '0.9999', '0.999', '0.958', '0.925', '0.900', '0.835', '0.830', '0.800' ) as $key ) {
		if ( abs( (float) $key - $f ) < 0.00005 ) {
			return $key;
		}
	}
	return rtrim( rtrim( number_format( $f, 4, '.', '' ), '0' ), '.' );
}

/**
 * Price formula: weight × purity × spot × (1 + premium %). Rounded to whole kroner.
 * Returns null if it cannot be calculated.
 */
function ns_calc_price( float $weight_g, float $purity, float $spot_per_kg, float $premium_pct ): ?float {
	if ( $weight_g <= 0 || $purity <= 0 || $spot_per_kg <= 0 ) {
		return null;
	}
	$metal = $weight_g * $purity * ( $spot_per_kg / 1000 );
	return max( 1.0, round( $metal * ( 1 + $premium_pct / 100 ) ) );
}

/** What a customer gets when selling silver: fine silver × spot × (1 − margin %). */
function ns_calc_buyback( float $weight_g, float $purity, float $spot_per_kg, float $margin_pct ): float {
	if ( $weight_g <= 0 || $purity <= 0 || $spot_per_kg <= 0 ) {
		return 0.0;
	}
	return max( 0.0, round( $weight_g * $purity * ( $spot_per_kg / 1000 ) * ( 1 - $margin_pct / 100 ) ) );
}

/** Is the product marked as a silver product? */
function ns_is_silver( $product ): bool {
	$product = wc_get_product( $product );
	return $product && 'yes' === $product->get_meta( '_ns_is_silver' );
}

/** Find a template – themes may override it in {theme}/justsilver/{name}. */
function ns_template( string $name ): string {
	$theme = locate_template( 'justsilver/' . $name );
	return $theme ?: NS_DIR . 'templates/' . $name;
}
