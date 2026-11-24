<?php
/**
 * Stores with opening hours, closed days and booking capacity.
 */
defined( 'ABSPATH' ) || exit;

class NS_Stores {

	const CPT = 'ns_store';

	public static function weekdays(): array {
		return array(
			1 => __( 'Monday', 'justsilver' ),
			2 => __( 'Tuesday', 'justsilver' ),
			3 => __( 'Wednesday', 'justsilver' ),
			4 => __( 'Thursday', 'justsilver' ),
			5 => __( 'Friday', 'justsilver' ),
			6 => __( 'Saturday', 'justsilver' ),
			7 => __( 'Sunday', 'justsilver' ),
		);
	}

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type( self::CPT, array(
			'labels'          => array(
				'name'          => __( 'Stores', 'justsilver' ),
				'singular_name' => __( 'Store', 'justsilver' ),
				'add_new'       => __( 'Add store', 'justsilver' ),
				'add_new_item'  => __( 'Add store', 'justsilver' ),
				'edit_item'     => __( 'Edit store', 'justsilver' ),
				'menu_name'     => __( 'Stores', 'justsilver' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=ns_booking',
			'supports'        => array( 'title' ),
			'capability_type' => 'page',
			'map_meta_cap'    => true,
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
		$m = function ( $k, $d = '' ) use ( $id ) {
			$v = get_post_meta( $id, $k, true );
			return '' !== $v ? $v : $d;
		};
		return array(
			'id'         => $id,
			'name'       => $post->post_title,
			'address'    => $m( '_ns_address' ),
			'postcode'   => $m( '_ns_postcode' ),
			'city'       => $m( '_ns_city' ),
			'phone'      => $m( '_ns_phone' ),
			'email'      => $m( '_ns_email' ),
			'slot'       => max( 10, (int) $m( '_ns_slot_minutes', 30 ) ),
			'capacity'   => max( 1, (int) $m( '_ns_capacity', 1 ) ),
			'notice'     => max( 0, (int) $m( '_ns_notice_hours', 2 ) ),
			'days_ahead' => max( 1, (int) $m( '_ns_days_ahead', 30 ) ),
			'hours'      => (array) $m( '_ns_hours', array() ),
			'closed'     => array_filter( array_map( 'trim', explode( "\n", (string) $m( '_ns_closed_dates' ) ) ) ),
			'active'     => 'no' !== $m( '_ns_active', 'yes' ),
		);
	}

	/** Opening hours for a date: [open, close] as "H:i", or null if closed. */
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
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$days  = array();
		for ( $i = 0; $i <= $store['days_ahead']; $i++ ) {
			$d = $today->modify( "+{$i} day" );
			if ( self::hours_for( $store, $d ) && self::available_slots( $store, $d ) ) {
				$days[] = $d;
			}
		}
		return $days;
	}

	/** @return array<string,int> "H:i" => free places */
	public static function available_slots( array $store, DateTimeImmutable $day ): array {
		$hours = self::hours_for( $store, $day );
		if ( ! $hours ) {
			return array();
		}
		$tz = wp_timezone();
		return self::generate_slots(
			$day->format( 'Y-m-d' ),
			$hours[0],
			$hours[1],
			$store['slot'],
			$store['capacity'],
			NS_Bookings::taken_counts( $store['id'], $day->format( 'Y-m-d' ) ),
			( new DateTimeImmutable( 'now', $tz ) )->modify( '+' . $store['notice'] . ' hours' ),
			$tz
		);
	}

	/**
	 * Pure slot calculation (unit tested).
	 *
	 * @param array<string,int> $taken "H:i" => bookings already made
	 * @return array<string,int> "H:i" => free places
	 */
	public static function generate_slots( string $date, string $open, string $close, int $slot_minutes, int $capacity, array $taken, DateTimeImmutable $earliest, DateTimeZone $tz ): array {
		$cur   = new DateTimeImmutable( $date . ' ' . $open, $tz );
		$end   = new DateTimeImmutable( $date . ' ' . $close, $tz );
		$step  = '+' . max( 5, $slot_minutes ) . ' minutes';
		$slots = array();
		while ( $cur->modify( $step ) <= $end ) {
			$t    = $cur->format( 'H:i' );
			$free = $capacity - ( $taken[ $t ] ?? 0 );
			if ( $cur >= $earliest && $free > 0 ) {
				$slots[ $t ] = $free;
			}
			$cur = $cur->modify( $step );
		}
		return $slots;
	}

	/* -------------------------------------------------------------- Admin */

	public static function meta_boxes(): void {
		add_meta_box( 'ns_store_details', __( 'Store details', 'justsilver' ), array( __CLASS__, 'box' ), self::CPT, 'normal', 'high' );
	}

	public static function box( WP_Post $post ): void {
		$s = self::get( $post->ID );
		wp_nonce_field( 'ns_store_save', 'ns_store_nonce' );
		$field = function ( $name, $label, $value, $type = 'text', $extra = '' ) {
			printf( '<p><label style="display:block;font-weight:600">%s</label><input type="%s" name="%s" value="%s" class="regular-text" %s></p>',
				esc_html( $label ), esc_attr( $type ), esc_attr( $name ), esc_attr( $value ), $extra ); // phpcs:ignore WordPress.Security.EscapeOutput
		};
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px">';
		$field( 'ns_address', __( 'Address', 'justsilver' ), $s['address'] );
		$field( 'ns_phone', __( 'Phone', 'justsilver' ), $s['phone'] );
		$field( 'ns_postcode', __( 'Postcode', 'justsilver' ), $s['postcode'] );
		$field( 'ns_city', __( 'City', 'justsilver' ), $s['city'] );
		$field( 'ns_email', __( 'Email (receives bookings)', 'justsilver' ), $s['email'], 'email' );
		echo '<p><label style="display:block;font-weight:600"><input type="checkbox" name="ns_active" value="yes" ' . checked( $s['active'], true, false ) . '> ' . esc_html__( 'Active (can be booked and chosen)', 'justsilver' ) . '</label></p>';
		echo '</div><h3>' . esc_html__( 'Booking', 'justsilver' ) . '</h3><div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0 16px">';
		$field( 'ns_slot_minutes', __( 'Length per visit (min.)', 'justsilver' ), $s['slot'], 'number', 'min="10" step="5" style="width:100%"' );
		$field( 'ns_capacity', __( 'Customers per slot', 'justsilver' ), $s['capacity'], 'number', 'min="1" style="width:100%"' );
		$field( 'ns_notice_hours', __( 'Minimum notice (hours)', 'justsilver' ), $s['notice'], 'number', 'min="0" style="width:100%"' );
		$field( 'ns_days_ahead', __( 'Bookable days ahead', 'justsilver' ), $s['days_ahead'], 'number', 'min="1" style="width:100%"' );
		echo '</div><h3>' . esc_html__( 'Opening hours', 'justsilver' ) . '</h3><table class="widefat striped" style="max-width:520px"><thead><tr><th>' . esc_html__( 'Day', 'justsilver' ) . '</th><th>' . esc_html__( 'Opens', 'justsilver' ) . '</th><th>' . esc_html__( 'Closes', 'justsilver' ) . '</th></tr></thead><tbody>';
		foreach ( self::weekdays() as $n => $label ) {
			$h = $s['hours'][ $n ] ?? array( 'open' => '', 'close' => '' );
			printf( '<tr><td>%s</td><td><input type="time" name="ns_hours[%d][open]" value="%s"></td><td><input type="time" name="ns_hours[%d][close]" value="%s"></td></tr>',
				esc_html( $label ), (int) $n, esc_attr( $h['open'] ?? '' ), (int) $n, esc_attr( $h['close'] ?? '' ) );
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Leave both fields empty on days the store is closed.', 'justsilver' ) . '</p>';
		printf( '<h3>%s</h3><textarea name="ns_closed_dates" rows="4" class="large-text code" placeholder="2026-12-24&#10;2026-12-25">%s</textarea><p class="description">%s</p>',
			esc_html__( 'Closed days', 'justsilver' ),
			esc_textarea( implode( "\n", $s['closed'] ) ),
			esc_html__( 'One date per line (YYYY-MM-DD), e.g. public holidays.', 'justsilver' ) );
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['ns_store_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ns_store_nonce'] ), 'ns_store_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array( 'ns_address', 'ns_postcode', 'ns_city', 'ns_phone' ) as $k ) {
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

	/** Keep WooCommerce' "Local pickup" locations in sync with the stores. */
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
				/* translators: %s: phone number */
				'details' => $s['phone'] ? sprintf( __( 'Phone %s', 'justsilver' ), $s['phone'] ) : '',
				'enabled' => true,
			);
		}
		update_option( 'pickup_location_pickup_locations', $locations );
	}

	public static function columns( array $cols ): array {
		return array(
			'cb'        => $cols['cb'],
			'title'     => __( 'Store', 'justsilver' ),
			'ns_addr'   => __( 'Address', 'justsilver' ),
			'ns_phone'  => __( 'Phone', 'justsilver' ),
			'ns_cap'    => __( 'Capacity', 'justsilver' ),
			'ns_active' => __( 'Active', 'justsilver' ),
		);
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
				/* translators: 1: customers, 2: minutes */
				echo esc_html( sprintf( __( '%1$d customer(s) / %2$d min.', 'justsilver' ), $s['capacity'], $s['slot'] ) );
				break;
			case 'ns_active':
				echo $s['active'] ? '✓' : '–';
				break;
		}
	}
}
