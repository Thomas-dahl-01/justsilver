<?php
/**
 * Appointment booking: store → day → free time → details.
 * Shortcode / block: [justsilver_booking]
 */
defined( 'ABSPATH' ) || exit;

class NS_Bookings {

	const CPT = 'ns_booking';

	public static function purposes(): array {
		return array(
			'koeb'        => __( 'See and buy silver', 'justsilver' ),
			'saelg'       => __( 'Sell silver', 'justsilver' ),
			'vurdering'   => __( 'Free valuation', 'justsilver' ),
			'raadgivning' => __( 'Investment advice', 'justsilver' ),
		);
	}

	public static function statuses(): array {
		return array(
			'booket'      => __( 'Booked', 'justsilver' ),
			'bekraeftet'  => __( 'Confirmed', 'justsilver' ),
			'gennemfoert' => __( 'Completed', 'justsilver' ),
			'udeblevet'   => __( 'No-show', 'justsilver' ),
			'aflyst'      => __( 'Cancelled', 'justsilver' ),
		);
	}

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_shortcode( 'justsilver_booking', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_cancel' ) );

		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', fn( $c ) => $c + array( 'ns_when' => 'ns_when' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_query' ) );
		add_filter( 'months_dropdown_results', fn( $m, $type ) => self::CPT === $type ? array() : $m, 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_admin' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type( self::CPT, array(
			'labels'          => array(
				'name'          => __( 'Bookings', 'justsilver' ),
				'singular_name' => __( 'Booking', 'justsilver' ),
				'menu_name'     => __( 'Silver bookings', 'justsilver' ),
				'all_items'     => __( 'All bookings', 'justsilver' ),
				'add_new'       => __( 'Add booking', 'justsilver' ),
				'add_new_item'  => __( 'Add booking', 'justsilver' ),
				'edit_item'     => __( 'Booking', 'justsilver' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'menu_icon'       => 'dashicons-calendar-alt',
			'menu_position'   => 56,
			'supports'        => array( 'title' ),
			'capability_type' => 'page',
			'map_meta_cap'    => true,
		) );
	}

	/** Number of active bookings per time slot for a store/date. */
	public static function taken_counts( int $store_id, string $date ): array {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT t.meta_value FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_ns_store' AND s.meta_value = %d
			 JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_ns_date' AND d.meta_value = %s
			 JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_ns_time'
			 LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_ns_status'
			 WHERE p.post_type = %s AND p.post_status = 'publish' AND COALESCE(st.meta_value, '') <> 'aflyst'",
			$store_id, $date, self::CPT
		) );
		return array_count_values( $rows );
	}

	public static function token( int $id, string $purpose = 'view' ): string {
		return substr( wp_hash( 'ns' . $purpose . $id ), 0, 16 );
	}

	/* ---------------------------------------------------------- Frontend */

	private static function req( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification -- navigation only; values validated below.
		$v = $_POST[ $key ] ?? $_GET[ $key ] ?? '';
		return is_string( $v ) ? sanitize_text_field( wp_unslash( $v ) ) : '';
	}

	private static function product_ids(): array {
		// phpcs:ignore WordPress.Security.NonceVerification
		$raw = $_POST['ns_vare'] ?? $_GET['ns_vare'] ?? array();
		$ids = array_filter( array_map( 'absint', (array) ( is_string( $raw ) ? explode( ',', $raw ) : $raw ) ) );
		return array_values( array_filter( array_slice( $ids, 0, 10 ), fn( $id ) => 'product' === get_post_type( $id ) ) );
	}

	/** Read and validate the choices in the URL. */
	public static function state(): array {
		$stores = NS_Stores::all();
		$store  = null;
		$sid    = absint( self::req( 'ns_butik' ) );
		if ( $sid ) {
			foreach ( $stores as $p ) {
				if ( $p->ID === $sid ) {
					$store = NS_Stores::get( $sid );
				}
			}
		} elseif ( 1 === count( $stores ) ) {
			$store = NS_Stores::get( $stores[0]->ID );
		}
		$days = $store ? NS_Stores::bookable_days( $store ) : array();
		$day  = null;
		$d    = self::req( 'ns_dato' );
		foreach ( $days as $candidate ) {
			if ( $candidate->format( 'Y-m-d' ) === $d ) {
				$day = $candidate;
			}
		}
		$slots = ( $store && $day ) ? NS_Stores::available_slots( $store, $day ) : array();
		$time  = self::req( 'ns_tid' );
		$purp  = self::req( 'ns_formaal' );
		return array(
			'stores'   => $stores,
			'store'    => $store,
			'days'     => $days,
			'day'      => $day,
			'slots'    => $slots,
			'time'     => isset( $slots[ $time ] ) ? $time : '',
			'products' => self::product_ids(),
			'purpose'  => isset( self::purposes()[ $purp ] ) ? $purp : 'koeb',
		);
	}

	public static function url( array $args = array() ): string {
		$page = (int) ns_opt( 'booking_page' );
		$base = $page ? get_permalink( $page ) : home_url( '/' );
		return add_query_arg( array_filter( $args, fn( $v ) => '' !== $v && null !== $v && array() !== $v ), $base );
	}

	public static function handle_post(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ns_booking_submit'] ) ) {
			return;
		}
		if ( ! isset( $_POST['ns_booking_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ns_booking_nonce'] ), 'ns_booking' ) ) {
			wc_add_notice( __( 'The page had expired – please try again.', 'justsilver' ), 'error' );
			return;
		}
		if ( ! empty( $_POST['ns_website'] ) ) { // honeypot against spam bots
			return;
		}
		$s      = self::state();
		$name   = sanitize_text_field( wp_unslash( $_POST['ns_name'] ?? '' ) );
		$email  = sanitize_email( wp_unslash( $_POST['ns_email'] ?? '' ) );
		$phone  = sanitize_text_field( wp_unslash( $_POST['ns_phone'] ?? '' ) );
		$note   = sanitize_textarea_field( wp_unslash( $_POST['ns_note'] ?? '' ) );
		$errors = array();
		if ( ! $s['store'] || ! $s['day'] || ! $s['time'] ) {
			$errors[] = __( 'Sorry, that time is no longer available. Please choose another.', 'justsilver' );
		}
		if ( strlen( $name ) < 2 ) {
			$errors[] = __( 'Please enter your name.', 'justsilver' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'justsilver' );
		}
		if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 8 ) {
			$errors[] = __( 'Please enter a valid phone number.', 'justsilver' );
		}
		if ( $errors ) {
			foreach ( $errors as $e ) {
				wc_add_notice( $e, 'error' );
			}
			return;
		}

		global $wpdb;
		$lock = 'ns_booking_' . $s['store']['id'];
		$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) );
		try {
			// Check again under the lock – two customers may click at the same time.
			$free = NS_Stores::available_slots( $s['store'], $s['day'] )[ $s['time'] ] ?? 0;
			if ( $free < 1 ) {
				wc_add_notice( __( 'Someone just took that time. Please choose a new one.', 'justsilver' ), 'error' );
				wp_safe_redirect( self::url( array( 'ns_butik' => $s['store']['id'], 'ns_dato' => $s['day']->format( 'Y-m-d' ), 'ns_vare' => implode( ',', $s['products'] ) ) ) );
				exit;
			}
			$id = wp_insert_post( array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s – %s %s', $name, $s['day']->format( 'd.m.Y' ), $s['time'] ),
				'meta_input'  => array(
					'_ns_store'    => $s['store']['id'],
					'_ns_date'     => $s['day']->format( 'Y-m-d' ),
					'_ns_time'     => $s['time'],
					'_ns_purpose'  => $s['purpose'],
					'_ns_name'     => $name,
					'_ns_email'    => $email,
					'_ns_phone'    => $phone,
					'_ns_note'     => $note,
					'_ns_products' => $s['products'],
					'_ns_status'   => 'booket',
				),
			), true );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		if ( is_wp_error( $id ) ) {
			wc_add_notice( __( 'The booking could not be saved. Please call us.', 'justsilver' ), 'error' );
			return;
		}
		do_action( 'justsilver_booking_created', $id ); // reservations hook in here
		self::send_emails( $id );
		wp_safe_redirect( self::url( array( 'ns_bekraeftet' => $id, 'k' => self::token( $id ) ) ) );
		exit;
	}

	/** Customer cancels from the link in the confirmation mail. */
	public static function handle_cancel(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ns_cancel_id'] ) ) {
			return;
		}
		$id = absint( $_POST['ns_cancel_id'] );
		$k  = sanitize_text_field( wp_unslash( $_POST['k'] ?? '' ) );
		if ( ! isset( $_POST['ns_cancel_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ns_cancel_nonce'] ), 'ns_cancel_' . $id ) || ! hash_equals( self::token( $id, 'cancel' ), $k ) ) {
			return;
		}
		self::set_status( $id, 'aflyst' );
		wc_add_notice( __( 'Your booking has been cancelled.', 'justsilver' ), 'success' );
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function set_status( int $id, string $status ): void {
		$old = get_post_meta( $id, '_ns_status', true );
		if ( $old === $status || ! isset( self::statuses()[ $status ] ) ) {
			return;
		}
		update_post_meta( $id, '_ns_status', $status );
		do_action( 'justsilver_booking_status_changed', $id, $status, $old );
	}

	public static function details( int $id ): array {
		$m     = fn( $k ) => get_post_meta( $id, $k, true );
		$store = NS_Stores::get( (int) $m( '_ns_store' ) );
		$date  = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $m( '_ns_date' ) . ' ' . ( $m( '_ns_time' ) ?: '00:00' ), wp_timezone() ) ?: null;
		return array(
			'id'       => $id,
			'store'    => $store,
			'date'     => $date,
			'time'     => $m( '_ns_time' ),
			'purpose'  => self::purposes()[ $m( '_ns_purpose' ) ] ?? '',
			'name'     => $m( '_ns_name' ),
			'email'    => $m( '_ns_email' ),
			'phone'    => $m( '_ns_phone' ),
			'note'     => $m( '_ns_note' ),
			'products' => array_filter( array_map( 'wc_get_product', (array) $m( '_ns_products' ) ) ),
			'reserved' => (array) ( $m( '_ns_reserved' ) ?: array() ),
			'status'   => $m( '_ns_status' ) ?: 'booket',
		);
	}

	public static function when_text( array $b ): string {
		/* translators: 1: date, 2: time */
		return sprintf( __( '%1$s at %2$s', 'justsilver' ), wp_date( 'l j. F Y', $b['date']->getTimestamp() ), $b['time'] );
	}

	public static function where_text( array $b ): string {
		return $b['store']['name'] . ', ' . $b['store']['address'] . ', ' . $b['store']['postcode'] . ' ' . $b['store']['city'];
	}

	/** Write the .ics file to a temp path and return it. */
	public static function ics_file( array $b ): string {
		$ics  = NS_ICS::build( array(
			'uid'         => 'booking-' . $b['id'] . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'start'       => $b['date'],
			'minutes'     => $b['store']['slot'],
			/* translators: %s: store name */
			'summary'     => sprintf( __( 'Visit at %s', 'justsilver' ), $b['store']['name'] ),
			'location'    => self::where_text( $b ),
			'description' => $b['purpose'] . ( $b['products'] ? ' – ' . implode( ', ', array_map( fn( $p ) => $p->get_name(), $b['products'] ) ) : '' ),
			'organizer'   => $b['store']['email'] ?: '',
		) );
		$path = trailingslashit( get_temp_dir() ) . 'justsilver-booking-' . $b['id'] . '.ics';
		file_put_contents( $path, $ics ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $path;
	}

	public static function items_html( array $b ): string {
		$items = '';
		foreach ( $b['products'] as $p ) {
			$reserved = in_array( $p->get_id(), $b['reserved'], true ) ? ' <em>(' . esc_html__( 'reserved for you', 'justsilver' ) . ')</em>' : '';
			$items   .= '<li>' . esc_html( $p->get_name() ) . ' – ' . esc_html( ns_dkk( $p->get_price() ) ) . $reserved . '</li>';
		}
		return $items ? '<p><strong>' . esc_html__( 'We will have these items ready for you:', 'justsilver' ) . '</strong></p><ul>' . $items . '</ul>' : '';
	}

	public static function send_emails( int $id ): void {
		$b = self::details( $id );
		if ( ! $b['store'] || ! $b['date'] ) {
			return;
		}
		$mailer = WC()->mailer();
		$ics    = self::ics_file( $b );
		$cancel = self::url( array( 'ns_aflys' => $id, 'k' => self::token( $id, 'cancel' ) ) );

		$customer = '<p>' . sprintf( /* translators: %s: name */ esc_html__( 'Hi %s', 'justsilver' ), esc_html( $b['name'] ) ) . '</p>'
			. '<p>' . esc_html__( 'Thank you for your booking. We look forward to seeing you.', 'justsilver' ) . '</p>'
			. '<p><strong>' . esc_html__( 'When:', 'justsilver' ) . '</strong> ' . esc_html( self::when_text( $b ) )
			. '<br><strong>' . esc_html__( 'Where:', 'justsilver' ) . '</strong> ' . esc_html( self::where_text( $b ) )
			. '<br><strong>' . esc_html__( 'Purpose:', 'justsilver' ) . '</strong> ' . esc_html( $b['purpose'] ) . '</p>'
			. self::items_html( $b )
			. '<p>' . esc_html__( 'The attached calendar file adds the visit to your calendar. Remember valid photo ID when buying or selling.', 'justsilver' ) . '</p>'
			. '<p><a href="' . esc_url( $cancel ) . '">' . esc_html__( 'Can’t make it? Cancel your booking', 'justsilver' ) . '</a></p>';
		$mailer->send( $b['email'], __( 'Your visit to JustSilver is booked', 'justsilver' ), $mailer->wrap_message( __( 'Your visit is booked', 'justsilver' ), $customer ), "Content-Type: text/html\r\n", array( $ics ) );

		$to    = $b['store']['email'] ?: ns_opt( 'notify_email', get_option( 'admin_email' ) );
		$staff = '<p><strong>' . esc_html( $b['name'] ) . '</strong> ' . esc_html__( 'has booked a visit.', 'justsilver' ) . '</p>'
			. '<p>' . esc_html( self::when_text( $b ) ) . ' · ' . esc_html( $b['store']['name'] )
			. '<br>' . esc_html__( 'Purpose:', 'justsilver' ) . ' ' . esc_html( $b['purpose'] )
			. '<br>' . esc_html__( 'Phone:', 'justsilver' ) . ' ' . esc_html( $b['phone'] )
			. '<br>' . esc_html__( 'Email:', 'justsilver' ) . ' ' . esc_html( $b['email'] ) . '</p>'
			. ( $b['note'] ? '<p>' . esc_html__( 'Note:', 'justsilver' ) . ' ' . nl2br( esc_html( $b['note'] ) ) . '</p>' : '' )
			. self::items_html( $b )
			. '<p><a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '">' . esc_html__( 'Open the booking', 'justsilver' ) . '</a></p>';
		/* translators: 1: customer name, 2: date and time */
		$mailer->send( $to, sprintf( __( 'New booking: %1$s – %2$s', 'justsilver' ), $b['name'], self::when_text( $b ) ), $mailer->wrap_message( __( 'New silver booking', 'justsilver' ), $staff ), "Content-Type: text/html\r\n", array( $ics ) );
		wp_delete_file( $ics );
	}

	public static function shortcode(): string {
		NS_Frontend::enqueue();
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['ns_bekraeftet'], $_GET['k'] ) ) {
			$id = absint( $_GET['ns_bekraeftet'] );
			if ( self::CPT === get_post_type( $id ) && hash_equals( self::token( $id ), sanitize_text_field( wp_unslash( $_GET['k'] ) ) ) ) {
				$b = self::details( $id );
				ob_start();
				include ns_template( 'booking-done.php' );
				return ob_get_clean();
			}
		}
		if ( isset( $_GET['ns_aflys'], $_GET['k'] ) ) {
			$id = absint( $_GET['ns_aflys'] );
			$k  = sanitize_text_field( wp_unslash( $_GET['k'] ) );
			if ( self::CPT === get_post_type( $id ) && hash_equals( self::token( $id, 'cancel' ), $k ) ) {
				$b = self::details( $id );
				ob_start();
				include ns_template( 'booking-cancel.php' );
				return ob_get_clean();
			}
		}
		// phpcs:enable
		$s = self::state();
		ob_start();
		NS_Frontend::print_notices();
		include ns_template( 'booking-form.php' );
		return ob_get_clean();
	}

	/* -------------------------------------------------------------- Admin */

	public static function columns( array $cols ): array {
		return array(
			'cb'          => $cols['cb'],
			'ns_when'     => __( 'Time', 'justsilver' ),
			'title'       => __( 'Customer', 'justsilver' ),
			'ns_store'    => __( 'Store', 'justsilver' ),
			'ns_purpose'  => __( 'Purpose', 'justsilver' ),
			'ns_contact'  => __( 'Contact', 'justsilver' ),
			'ns_products' => __( 'Items', 'justsilver' ),
			'ns_status'   => __( 'Status', 'justsilver' ),
		);
	}

	public static function column( string $col, int $id ): void {
		$b = self::details( $id );
		switch ( $col ) {
			case 'ns_when':
				echo $b['date'] ? esc_html( wp_date( 'D j. M Y', $b['date']->getTimestamp() ) . ' · ' . $b['time'] ) : '';
				break;
			case 'ns_store':
				echo esc_html( $b['store']['name'] ?? '' );
				break;
			case 'ns_purpose':
				echo esc_html( $b['purpose'] );
				break;
			case 'ns_contact':
				echo esc_html( $b['phone'] ) . '<br><a href="mailto:' . esc_attr( $b['email'] ) . '">' . esc_html( $b['email'] ) . '</a>';
				break;
			case 'ns_products':
				$names = array_map( fn( $p ) => $p->get_name() . ( in_array( $p->get_id(), $b['reserved'], true ) ? ' 🔒' : '' ), $b['products'] );
				echo esc_html( implode( ', ', $names ) ?: '–' );
				break;
			case 'ns_status':
				$class = 'aflyst' === $b['status'] ? 'cancelled' : ( 'gennemfoert' === $b['status'] ? 'completed' : 'processing' );
				echo '<mark class="order-status status-' . esc_attr( $class ) . '"><span>' . esc_html( self::statuses()[ $b['status'] ] ?? $b['status'] ) . '</span></mark>';
				break;
		}
	}

	public static function filters( string $post_type ): void {
		if ( self::CPT !== $post_type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$cur_store  = absint( $_GET['ns_store'] ?? 0 );
		$cur_status = sanitize_key( $_GET['ns_status'] ?? '' );
		$cur_when   = sanitize_key( $_GET['ns_when'] ?? 'kommende' );
		// phpcs:enable
		echo '<select name="ns_when">';
		foreach ( array( 'kommende' => __( 'Upcoming', 'justsilver' ), 'idag' => __( 'Today', 'justsilver' ), 'alle' => __( 'All dates', 'justsilver' ) ) as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur_when, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><select name="ns_store"><option value="">' . esc_html__( 'All stores', 'justsilver' ) . '</option>';
		foreach ( NS_Stores::all( false ) as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '"' . selected( $cur_store, $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
		}
		echo '</select><select name="ns_status"><option value="">' . esc_html__( 'All statuses', 'justsilver' ) . '</option>';
		foreach ( self::statuses() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur_status, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public static function admin_query( WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || self::CPT !== $q->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$meta  = array( 'relation' => 'AND', 'ns_d' => array( 'key' => '_ns_date', 'compare' => 'EXISTS' ), 'ns_t' => array( 'key' => '_ns_time', 'compare' => 'EXISTS' ) );
		$when  = sanitize_key( $_GET['ns_when'] ?? 'kommende' );
		$today = wp_date( 'Y-m-d' );
		if ( 'kommende' === $when ) {
			$meta['ns_d'] = array( 'key' => '_ns_date', 'value' => $today, 'compare' => '>=' );
		} elseif ( 'idag' === $when ) {
			$meta['ns_d'] = array( 'key' => '_ns_date', 'value' => $today );
		}
		if ( ! empty( $_GET['ns_store'] ) ) {
			$meta[] = array( 'key' => '_ns_store', 'value' => absint( $_GET['ns_store'] ) );
		}
		if ( ! empty( $_GET['ns_status'] ) ) {
			$meta[] = array( 'key' => '_ns_status', 'value' => sanitize_key( $_GET['ns_status'] ) );
		}
		$q->set( 'meta_query', $meta );
		$order = ( 'ns_when' === $q->get( 'orderby' ) && 'desc' === strtolower( (string) $q->get( 'order' ) ) ) ? 'DESC' : ( 'alle' === $when ? 'DESC' : 'ASC' );
		$q->set( 'orderby', array( 'ns_d' => $order, 'ns_t' => $order ) );
		// phpcs:enable
	}

	public static function meta_boxes(): void {
		add_meta_box( 'ns_booking', __( 'Booking', 'justsilver' ), array( __CLASS__, 'box' ), self::CPT, 'normal', 'high' );
	}

	public static function box( WP_Post $post ): void {
		$b = self::details( $post->ID );
		wp_nonce_field( 'ns_booking_admin', 'ns_booking_admin_nonce' );
		echo '<table class="form-table"><tbody><tr><th>' . esc_html__( 'Store', 'justsilver' ) . '</th><td><select name="ns_store">';
		foreach ( NS_Stores::all( false ) as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '"' . selected( $b['store']['id'] ?? 0, $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
		}
		echo '</select></td></tr>';
		printf( '<tr><th>%s</th><td><input type="date" name="ns_date" value="%s"> <input type="time" name="ns_time" value="%s"></td></tr>', esc_html__( 'Date / time', 'justsilver' ), esc_attr( $b['date'] ? $b['date']->format( 'Y-m-d' ) : '' ), esc_attr( $b['time'] ) );
		echo '<tr><th>' . esc_html__( 'Status', 'justsilver' ) . '</th><td><select name="ns_status">';
		foreach ( self::statuses() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $b['status'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Cancelled or no-show releases reserved items back to stock.', 'justsilver' ) . '</p></td></tr>';
		echo '<tr><th>' . esc_html__( 'Purpose', 'justsilver' ) . '</th><td><select name="ns_purpose">';
		$cur = get_post_meta( $post->ID, '_ns_purpose', true );
		foreach ( self::purposes() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		foreach ( array( 'name' => __( 'Name', 'justsilver' ), 'email' => __( 'Email', 'justsilver' ), 'phone' => __( 'Phone', 'justsilver' ) ) as $k => $label ) {
			printf( '<tr><th>%s</th><td><input class="regular-text" name="ns_%s" value="%s"></td></tr>', esc_html( $label ), esc_attr( $k ), esc_attr( $b[ $k ] ) );
		}
		printf( '<tr><th>%s</th><td><textarea class="large-text" rows="3" name="ns_note">%s</textarea></td></tr>', esc_html__( 'Note', 'justsilver' ), esc_textarea( $b['note'] ) );
		echo '<tr><th>' . esc_html__( 'Items the customer wants to see', 'justsilver' ) . '</th><td>';
		foreach ( $b['products'] as $p ) {
			$lock = in_array( $p->get_id(), $b['reserved'], true ) ? ' – 🔒 ' . esc_html__( 'reserved', 'justsilver' ) : '';
			echo '<a href="' . esc_url( get_edit_post_link( $p->get_id() ) ) . '">' . esc_html( $p->get_name() ) . '</a> (' . esc_html( $p->get_sku() ) . ', ' . esc_html__( 'stock', 'justsilver' ) . ': ' . esc_html( (string) $p->get_stock_quantity() ) . ')' . $lock . '<br>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo $b['products'] ? '' : '–';
		echo '</td></tr></tbody></table>';
	}

	public static function save_admin( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['ns_booking_admin_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ns_booking_admin_nonce'] ), 'ns_booking_admin' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_ns_store', absint( $_POST['ns_store'] ?? 0 ) );
		$date = sanitize_text_field( wp_unslash( $_POST['ns_date'] ?? '' ) );
		$time = sanitize_text_field( wp_unslash( $_POST['ns_time'] ?? '' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			update_post_meta( $post_id, '_ns_date', $date );
		}
		if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			update_post_meta( $post_id, '_ns_time', $time );
		}
		$purpose = sanitize_key( $_POST['ns_purpose'] ?? '' );
		if ( isset( self::purposes()[ $purpose ] ) ) {
			update_post_meta( $post_id, '_ns_purpose', $purpose );
		}
		update_post_meta( $post_id, '_ns_name', sanitize_text_field( wp_unslash( $_POST['ns_name'] ?? '' ) ) );
		update_post_meta( $post_id, '_ns_email', sanitize_email( wp_unslash( $_POST['ns_email'] ?? '' ) ) );
		update_post_meta( $post_id, '_ns_phone', sanitize_text_field( wp_unslash( $_POST['ns_phone'] ?? '' ) ) );
		update_post_meta( $post_id, '_ns_note', sanitize_textarea_field( wp_unslash( $_POST['ns_note'] ?? '' ) ) );
		self::set_status( $post_id, sanitize_key( $_POST['ns_status'] ?? '' ) );
	}
}
