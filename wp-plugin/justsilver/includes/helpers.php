<?php
defined( 'ABSPATH' ) || exit;

/** Hent en plugin-indstilling. */
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
		'barre'    => 'Barre',
		'moent'    => 'Mønt',
		'granulat' => 'Granulat',
		'smykke'   => 'Smykke',
		'bestik'   => 'Bestik & sølvtøj',
		'antik'    => 'Antik & samlerobjekt',
	);
}

function ns_purities(): array {
	return array(
		'0.9999' => '999,9',
		'0.999'  => '999 (fint sølv)',
		'0.958'  => '958 (Britannia)',
		'0.925'  => '925 (sterling)',
		'0.900'  => '900',
		'0.835'  => '835',
		'0.830'  => '830 (dansk sølv)',
		'0.800'  => '800',
	);
}

function ns_conditions(): array {
	return array(
		'ny'      => 'Ny',
		'som_ny'  => 'Som ny',
		'brugt'   => 'Brugt',
		'vintage' => 'Vintage',
	);
}

/** Er produktet markeret som sølvvare? */
function ns_is_silver( $product ): bool {
	$product = wc_get_product( $product );
	return $product && 'yes' === $product->get_meta( '_ns_is_silver' );
}

/** Find skabelon – temaet kan overskrive den i {tema}/justsilver/{navn}. */
function ns_template( string $name ): string {
	$theme = locate_template( 'justsilver/' . $name );
	return $theme ?: NS_DIR . 'templates/' . $name;
}

/** Normalisér finhed til en nøgle i ns_purities() (fx "830", "0.83" -> "0.830"). */
function ns_normalize_purity( $value ): string {
	$f = (float) str_replace( ',', '.', (string) $value );
	if ( $f > 1 ) {
		$f = $f / 1000;
	}
	foreach ( array_keys( ns_purities() ) as $key ) {
		if ( abs( (float) $key - $f ) < 0.00005 ) {
			return (string) $key;
		}
	}
	return rtrim( rtrim( number_format( $f, 4, '.', '' ), '0' ), '.' );
}
