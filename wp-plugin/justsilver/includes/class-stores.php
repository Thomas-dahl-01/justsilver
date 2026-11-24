<?php
/**
 * Butikker (afdelinger) med åbningstider, lukkedage og booking-kapacitet.
 */
defined( 'ABSPATH' ) || exit;

class NS_Stores {

	const CPT      = 'ns_store';
	const WEEKDAYS = array( 1 => 'Mandag', 2 => 'Tirsdag', 3 => 'Onsdag', 4 => 'Torsdag', 5 => 'Fredag', 6 => 'Lørdag', 7 => 'Søndag' );

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type( self::CPT, array(
			'labels'       => array(
				'name'          => 'Butikker',
				'singular_name' => 'Butik',
				'add_new'       => 'Tilføj butik',
				'add_new_item'  => 'Tilføj butik',
				'edit_item'     => 'Rediger butik',
				'menu_name'     => 'Butikker',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=ns_booking',
			'supports'     => array( 'title' ),
			'capability_type' => 'page',
			'map_meta_cap' => true,
		) );
	}

	/* --------------------------------------------------------------- Data */

	public static function all( bool $only_active = true ): array {
		$args = array( 'post_type' => self::CPT, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' );
		if ( $only_active ) {
			$args['meta_query'] = array( array( 'key' => '_ns_active', 'value' => 'no', 'compare' => '!=' ) );
		}
		return get_posts( $args );
	}

	public static function get( int $id ): array {
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return array();
		}
		$m = fn( $k, $d = '' ) => ( '' !== ( $v = get_post_meta( $id, $k, true ) ) ) ? $v : $d;
		return array(
			'id'          => $id,
			'name'        => $post->post_title,
			'address'     => $m( '_ns_address' ),
			'postcode'    => $m( '_ns_postcode' ),
			'city'        => $m( '_ns_city' ),
			'phone'       => $m( '_ns_phone' ),
			'email'       => $m( '_ns_email' ),
			'slot'        => max( 10, (int) $m( '_ns_slot_minutes', 30 ) ),
			'capacity'    => max( 1, (int) $m( '_ns_capacity', 1 ) ),
			'notice'      => max( 0, (int) $m( '_ns_notice_hours', 2 ) ),
			'days_ahead'  => max( 1, (int) $m( '_ns_days_ahead', 30 ) ),
			'hours'       => (array) $m( '_ns_hours', array() ),
			'closed'      => array_filter( array_map( 'trim', explode( "\n", (string) $m( '_ns_closed_dates' ) ) ) ),
			'active'      => 'no' !== $m( '_ns_active', 'yes' ),
		);
	}

	/** Åbningstid for en dato: [åbner, lukker] som "H:i" eller null hvis lukket. */
	public static function hours_for( array $store, DateTimeImmutable $day ): ?array {
		if ( in_array( $day->format( 'Y-m-d' ), $store['closed'], true ) ) {
			return null;
		}
		$h = $store['hours'][ (int) $day->format( 'N' ) ] ?? null;
		if ( empty( $h['open'] ) || empty( $h['close'] ) ) {
			return null;
		}
		return array( $h['open'], $h['close'] );
	}

	/** @return DateTimeImmutable[] */
	public static function bookable_days( array $store ): array {
		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'today', $tz );
		$days  = array();
		for ( $i = 0; $i <= $store['days_ahead']; $i++ ) {
			$d = $today->modify( "+{$i} day" );
			if ( self::hours_for( $store, $d ) && self::available_slots( $store, $d ) ) {
				$days[] = $d;
			}
		}
		return $days;
	}

	/** @return array<string,int> "H:i" => ledige pladser */
	public static function available_slots( array $store, DateTimeImmutable $day ): array {
		$hours = self::hours_for( $store, $day );
		if ( ! $hours ) {
			return array();
		}
		$tz       = wp_timezone();
		$earliest = ( new DateTimeImmutable( 'now', $tz ) )->modify( '+' . $store['notice'] . ' hours' );
		$taken    = NS_Bookings::taken_counts( $store['id'], $day->format( 'Y-m-d' ) );
		$cur      = new DateTimeImmutable( $day->format( 'Y-m-d' ) . ' ' . $hours[0], $tz );
		$end      = new DateTimeImmutable( $day->format( 'Y-m-d' ) . ' ' . $hours[1], $tz );
		$step     = '+' . $store['slot'] . ' minutes';
		$slots    = array();
		while ( $cur->modify( $step ) <= $end ) {
			$t    = $cur->format( 'H:i' );
			$free = $store['capacity'] - ( $taken[ $t ] ?? 0 );
			if ( $cur >= $earliest && $free > 0 ) {
				$slots[ $t ] = $free;
			}
			$cur = $cur->modify( $step );
		}
		return $slots;
	}

	/* -------------------------------------------------------------- Admin */

	public static function meta_boxes(): void {
		add_meta_box( 'ns_store_details', 'Butiksoplysninger', array( __CLASS__, 'box' ), self::CPT, 'normal', 'high' );
	}

	public static function box( WP_Post $post ): void {
		$s = self::get( $post->ID );
		wp_nonce_field( 'ns_store_save', 'ns_store_nonce' );
		$field = function ( $name, $label, $value, $type = 'text', $extra = '' ) {
			printf( '<p><label style="display:block;font-weight:600">%s</label><input type="%s" name="%s" value="%s" class="regular-text" %s></p>',
				esc_html( $label ), esc_attr( $type ), esc_attr( $name ), esc_attr( $value ), $extra ); // phpcs:ignore WordPress.Security.EscapeOutput
		};
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';
		$field( 'ns_address', 'Adresse', $s['address'] );
		$field( 'ns_phone', 'Telefon', $s['phone'] );
		$field( 'ns_postcode', 'Postnr.', $s['postcode'] );
		$field( 'ns_city', 'By', $s['city'] );
		$field( 'ns_email', 'E-mail (modtager bookinger)', $s['email'], 'email' );
		echo '<p><label style="display:block;font-weight:600"><input type="checkbox" name="ns_active" value="yes" ' . checked( $s['active'], true, false ) . '> Aktiv (kan bookes og vælges)</label></p>';
		echo '</div><h3>Booking</h3><div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0 16px">';
		$field( 'ns_slot_minutes', 'Varighed pr. tid (min.)', $s['slot'], 'number', 'min="10" step="5" style="width:100%"' );
		$field( 'ns_capacity', 'Kunder pr. tidsrum', $s['capacity'], 'number', 'min="1" style="width:100%"' );
		$field( 'ns_notice_hours', 'Mindste varsel (timer)', $s['notice'], 'number', 'min="0" style="width:100%"' );
		$field( 'ns_days_ahead', 'Kan bookes dage frem', $s['days_ahead'], 'number', 'min="1" style="width:100%"' );
		echo '</div><h3>Åbningstider</h3><table class="widefat striped" style="max-width:520px"><thead><tr><th>Dag</th><th>Åbner</th><th>Lukker</th></tr></thead><tbody>';
		foreach ( self::WEEKDAYS as $n => $label ) {
			$h = $s['hours'][ $n ] ?? array( 'open' => '', 'close' => '' );
			printf( '<tr><td>%s</td><td><input type="time" name="ns_hours[%d][open]" value="%s"></td><td><input type="time" name="ns_hours[%d][close]" value="%s"></td></tr>',
				esc_html( $label ), $n, esc_attr( $h['open'] ?? '' ), $n, esc_attr( $h['close'] ?? '' ) );
		}
		echo '</tbody></table><p class="description">Lad felterne stå tomme på lukkedage.</p>';
		printf( '<h3>Lukkedage</h3><textarea name="ns_closed_dates" rows="4" class="large-text code" placeholder="2026-12-24&#10;2026-12-25">%s</textarea><p class="description">Én dato pr. linje (ÅÅÅÅ-MM-DD), fx helligdage.</p>',
			esc_textarea( implode( "\n", $s['closed'] ) ) );
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['ns_store_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ns_store_nonce'] ), 'ns_store_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$text = array( 'ns_address', 'ns_postcode', 'ns_city', 'ns_phone' );
		foreach ( $text as $k ) {
			update_post_meta( $post_id, '_' . $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) );
		}
		update_post_meta( $post_id, '_ns_email', sanitize_email( wp_unslash( $_POST['ns_email'] ?? '' ) ) );
		foreach ( array( 'ns_slot_minutes', 'ns_capacity', 'ns_notice_hours', 'ns_days_ahead' ) as $k ) {
			update_post_meta( $post_id, '_' . $k, absint( $_POST[ $k ] ?? 0 ) );
		}
		update_post_meta( $post_id, '_ns_active', isset( $_POST['ns_active'] ) ? 'yes' : 'no' );
		$hours = array();
		foreach ( (array) wp_unslash( $_POST['ns_hours'] ?? array() ) as $day => $h ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$open  = preg_match( '/^\d{2}:\d{2}$/', $h['open'] ?? '' ) ? $h['open'] : '';
			$close = preg_match( '/^\d{2}:\d{2}$/', $h['close'] ?? '' ) ? $h['close'] : '';
			$hours[ (int) $day ] = ( $open && $close && $open < $close ) ? array( 'open' => $open, 'close' => $close ) : array( 'open' => '', 'close' => '' );
		}
		update_post_meta( $post_id, '_ns_hours', $hours );
		$dates = array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['ns_closed_dates'] ?? '' ) ) ) ), fn( $d ) => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) );
		update_post_meta( $post_id, '_ns_closed_dates', implode( "\n", $dates ) );
		self::sync_pickup_locations();
	}

	/**
	 * Hold WooCommerce' "Lokal afhentning"-steder (checkout-blokken) i sync med butikkerne,
	 * så kunden kan vælge afhentningsbutik ved køb.
	 */
	public static function sync_pickup_locations(): void {
		if ( 'yes' !== ns_opt( 'sync_pickup', 'yes' ) ) {
			return;
		}
		$locations = array();
		foreach ( self::all() as $post ) {
			$s           = self::get( $post->ID );
			$locations[] = array(
				'name'    => $s['name'],
				'address' => array( 'address_1' => $s['address'], 'city' => $s['city'], 'state' => '', 'postcode' => $s['postcode'], 'country' => 'DK' ),
				'details' => $s['phone'] ? 'Tlf. ' . $s['phone'] : '',
				'enabled' => true,
			);
		}
		update_option( 'pickup_location_pickup_locations', $locations );
	}

	public static function columns( array $cols ): array {
		return array( 'cb' => $cols['cb'], 'title' => 'Butik', 'ns_addr' => 'Adresse', 'ns_phone' => 'Telefon', 'ns_cap' => 'Kapacitet', 'ns_active' => 'Aktiv' );
	}

	public static function column( string $col, int $id ): void {
		$s = self::get( $id );
		switch ( $col ) {
			case 'ns_addr':
				echo esc_html( trim( $s['address'] . ', ' . $s['postcode'] . ' ' . $s['city'], ', ' ) );
				break;
			case 'ns_phone':
				echo esc_html( $s['phone'] );
				break;
			case 'ns_cap':
				echo esc_html( $s['capacity'] . ' kunde(r) / ' . $s['slot'] . ' min.' );
				break;
			case 'ns_active':
				echo $s['active'] ? '✓' : '–';
				break;
		}
	}
}
