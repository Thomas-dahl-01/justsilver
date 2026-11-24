<?php
/**
 * Automatisk hentning af sølvkursen (XAG/DKK) via WP-Cron.
 *
 * Indbygget udbyder: metalpriceapi.com (kræver gratis/betalt API-nøgle).
 * Andre udbydere kan kobles på med filteret "justsilver_fetch_spot_price"
 * som skal returnere DKK pr. troy ounce (float) eller null.
 */
defined( 'ABSPATH' ) || exit;

class NS_Price_API {

	const HOOK       = 'ns_fetch_silver_price';
	const OZ_PER_KG  = 32.1507466;
	const MAX_JUMP   = 0.20; // afvis kurser der ændrer sig mere end 20 % på én gang (formentlig fejl)

	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'update_option_ns_settings', array( __CLASS__, 'reschedule' ) );
	}

	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		$interval = ns_opt( 'api_interval', 'hourly' );
		if ( 'off' !== $interval && ( ns_opt( 'api_key' ) || has_filter( 'justsilver_fetch_spot_price' ) ) ) {
			wp_schedule_event( time() + 60, $interval, self::HOOK );
		}
	}

	/**
	 * Hent og gem kursen.
	 *
	 * @return array{ok:bool,message:string,price?:float}
	 */
	public static function run( bool $force = false ): array {
		$per_oz = apply_filters( 'justsilver_fetch_spot_price', null );
		if ( null === $per_oz ) {
			$per_oz = self::fetch_metalpriceapi();
		}
		if ( is_wp_error( $per_oz ) ) {
			return self::log( false, $per_oz->get_error_message() );
		}
		$per_kg = (float) $per_oz * self::OZ_PER_KG;
		if ( $per_kg <= 0 ) {
			return self::log( false, 'Ugyldig kurs modtaget.' );
		}
		$old = NS_Silver_Price::per_kg();
		if ( ! $force && $old > 0 && abs( $per_kg - $old ) / $old > self::MAX_JUMP ) {
			return self::log( false, sprintf( 'Kursen %s kr/kg afveg mere end 20 %% fra %s kr/kg og blev ikke brugt. Kontrollér og sæt den manuelt.', number_format( $per_kg, 0, ',', '.' ), number_format( $old, 0, ',', '.' ) ) );
		}
		NS_Silver_Price::set( $per_kg, 'api' );
		return self::log( true, 'Sølvkurs opdateret: ' . ns_dkk( $per_kg ) . ' pr. kg', $per_kg );
	}

	/** @return float|WP_Error DKK pr. troy ounce */
	public static function fetch_metalpriceapi() {
		$key = ns_opt( 'api_key' );
		if ( ! $key ) {
			return new WP_Error( 'ns_no_key', 'Der er ingen API-nøgle sat op.' );
		}
		$url = add_query_arg( array( 'api_key' => $key, 'base' => 'DKK', 'currencies' => 'XAG' ), 'https://api.metalpriceapi.com/v1/latest' );
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $res ) || empty( $body['success'] ) ) {
			$msg = $body['error']['info'] ?? $body['error']['message'] ?? ( 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
			return new WP_Error( 'ns_api', 'Pris-API fejlede: ' . $msg );
		}
		$rates = $body['rates'] ?? array();
		if ( ! empty( $rates['DKKXAG'] ) ) {
			return (float) $rates['DKKXAG'];      // DKK for 1 oz sølv
		}
		if ( ! empty( $rates['XAG'] ) ) {
			return 1 / (float) $rates['XAG'];      // oz sølv for 1 DKK -> invertér
		}
		return new WP_Error( 'ns_api', 'Pris-API svarede uden XAG-kurs.' );
	}

	private static function log( bool $ok, string $message, float $price = 0 ): array {
		update_option( 'ns_api_last', array( 't' => time(), 'ok' => $ok, 'msg' => $message ), false );
		if ( ! $ok && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $message, array( 'source' => 'justsilver' ) );
		}
		return array( 'ok' => $ok, 'message' => $message, 'price' => $price );
	}
}
