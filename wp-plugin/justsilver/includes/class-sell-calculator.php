<?php
/**
 * "Sell your silver" calculator: weight + purity → instant offer, with a button to book a free valuation.
 * Shortcode / block: [justsilver_saelg]. Live calculation in assets/js/justsilver.js; this class renders
 * the form and a server-side result as a no-JavaScript fallback.
 */
defined( 'ABSPATH' ) || exit;

class NS_Sell_Calculator {

	public static function init(): void {
		add_shortcode( 'justsilver_saelg', array( __CLASS__, 'shortcode' ) );
	}

	public static function margin(): float {
		return (float) ns_opt( 'buyback_margin', 15 );
	}

	public static function shortcode(): string {
		NS_Frontend::enqueue();
		// phpcs:disable WordPress.Security.NonceVerification -- read-only GET calculation
		$weight = isset( $_GET['ns_s_vaegt'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_GET['ns_s_vaegt'] ) ) ) : 0;
		$purity = isset( $_GET['ns_s_finhed'] ) ? ns_normalize_purity( sanitize_text_field( wp_unslash( $_GET['ns_s_finhed'] ) ) ) : '0.925';
		// phpcs:enable
		$spot   = NS_Silver_Price::per_kg();
		$offer  = ns_calc_buyback( $weight, (float) $purity, $spot, self::margin() );
		ob_start();
		include ns_template( 'sell-calculator.php' );
		return ob_get_clean();
	}
}
