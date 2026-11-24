<?php
/**
 * Tidsbestilling: kunden vælger butik → dag → ledigt tidspunkt → oplysninger.
 * Shortcode: [justsilver_booking]
 */
defined( 'ABSPATH' ) || exit;

class NS_Bookings {

	const CPT = 'ns_booking';

	public static function purposes(): array {
		return array(
			'koeb'        => 'Se og købe sølv',
			'saelg'       => 'Sælge sølv',
			'vurdering'   => 'Gratis vurdering',
			'raadgivning' => 'Rådgivning om investering',
		);
	}

	public static function statuses(): array {
		return array(
			'booket'      => 'Booket',
			'bekraeftet'  => 'Bekræftet',
			'gennemfoert' => 'Gennemført',
			'udeblevet'   => 'Udeblevet',
			'aflyst'      => 'Aflyst',
		);
	}

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_shortcode( 'justsilver_booking', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );

		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', fn( $c ) => $c + array( 'ns_when' => 'ns_when' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_query' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_admin' ), 10, 2 );
	}

	public static function register(): void {
		register_post_type( self::CPT, array(
			'labels'          => array(
				'name'          => 'Bookinger',
				'singular_name' => 'Booking',
				'menu_name'     => 'Sølv-bookinger',
				'all_items'     => 'Alle bookinger',
				'add_new'       => 'Opret booking',
				'add_new_item'  => 'Opret booking',
				'edit_item'     => 'Booking',
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

	/** Antal aktive bookinger pr. tidspunkt for en butik/dato. */
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

	/* ---------------------------------------------------------- Frontend */

	private static function req( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification -- kun navigation (GET) / værdier valideres nedenfor.
		$v = $_POST[ $key ] ?? $_GET[ $key ] ?? '';
		return is_string( $v ) ? sanitize_text_field( wp_unslash( $v ) ) : '';
	}

	private static function product_ids(): array {
		// phpcs:ignore WordPress.Security.NonceVerification
		$raw = $_POST['ns_vare'] ?? $_GET['ns_vare'] ?? array();
		$ids = array_filter( array_map( 'absint', (array) ( is_string( $raw ) ? explode( ',', $raw ) : $raw ) ) );
		return array_values( array_filter( array_slice( $ids, 0, 10 ), fn( $id ) => 'product' === get_post_type( $id ) ) );
	}

	/** Læs og valider valgene i URL'en. */
	private static function state(): array {
		$tz     = wp_timezone();
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
		$time  = isset( $slots[ $time ] ) ? $time : '';
		$purp  = self::req( 'ns_formaal' );
		return array(
			'stores'   => $stores,
			'store'    => $store,
			'days'     => $days,
			'day'      => $day,
			'slots'    => $slots,
			'time'     => $time,
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
			wc_add_notice( 'Siden var udløbet – prøv venligst igen.', 'error' );
			return;
		}
		if ( ! empty( $_POST['ns_website'] ) ) { // honeypot mod spam-robotter
			return;
		}
		$s      = self::state();
		$name   = sanitize_text_field( wp_unslash( $_POST['ns_name'] ?? '' ) );
		$email  = sanitize_email( wp_unslash( $_POST['ns_email'] ?? '' ) );
		$phone  = sanitize_text_field( wp_unslash( $_POST['ns_phone'] ?? '' ) );
		$note   = sanitize_textarea_field( wp_unslash( $_POST['ns_note'] ?? '' ) );
		$errors = array();
		if ( ! $s['store'] || ! $s['day'] || ! $s['time'] ) {
			$errors[] = 'Tiden er desværre ikke længere ledig. Vælg venligst en anden.';
		}
		if ( strlen( $name ) < 2 ) {
			$errors[] = 'Skriv venligst dit navn.';
		}
		if ( ! is_email( $email ) ) {
			$errors[] = 'Skriv venligst en gyldig e-mail.';
		}
		if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 8 ) {
			$errors[] = 'Skriv venligst et gyldigt telefonnummer.';
		}
		if ( $errors ) {
			foreach ( $errors as $e ) {
				wc_add_notice( $e, 'error' );
			}
			return; // formularen vises igen med udfyldte felter
		}

		global $wpdb;
		$lock = 'ns_booking_' . $s['store']['id'];
		$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) );
		try {
			// Tjek igen under lås – to kunder kan klikke på samme tid.
			$free = NS_Stores::available_slots( $s['store'], $s['day'] )[ $s['time'] ] ?? 0;
			if ( $free < 1 ) {
				wc_add_notice( 'Tiden er lige blevet taget af en anden. Vælg venligst en ny tid.', 'error' );
				wp_safe_redirect( self::url( array( 'ns_butik' => $s['store']['id'], 'ns_dato' => $s['day']->format( 'Y-m-d' ), 'ns_vare' => implode( ',', $s['products'] ) ) ) );
				exit;
			}
			$id = wp_insert_post( array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s – %s kl. %s', $name, $s['day']->format( 'd.m.Y' ), $s['time'] ),
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
			wc_add_notice( 'Bookingen kunne ikke gemmes. Ring venligst til os.', 'error' );
			return;
		}
		self::send_emails( $id );
		do_action( 'justsilver_booking_created', $id );
		wp_safe_redirect( self::url( array( 'ns_bekraeftet' => $id, 'k' => substr( wp_hash( 'ns' . $id ), 0, 12 ) ) ) );
		exit;
	}

	public static function details( int $id ): array {
		$m     = fn( $k ) => get_post_meta( $id, $k, true );
		$store = NS_Stores::get( (int) $m( '_ns_store' ) );
		$date  = DateTimeImmutable::createFromFormat( 'Y-m-d', (string) $m( '_ns_date' ), wp_timezone() );
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
			'status'   => $m( '_ns_status' ) ?: 'booket',
		);
	}

	public static function send_emails( int $id ): void {
		$b = self::details( $id );
		if ( ! $b['store'] || ! $b['date'] ) {
			return;
		}
		$when   = wp_date( 'l \d. j. F Y', $b['date']->getTimestamp() ) . ' kl. ' . $b['time'];
		$where  = esc_html( $b['store']['name'] . ', ' . $b['store']['address'] . ', ' . $b['store']['postcode'] . ' ' . $b['store']['city'] );
		$items  = '';
		foreach ( $b['products'] as $p ) {
			$items .= '<li>' . esc_html( $p->get_name() ) . ' – ' . esc_html( ns_dkk( $p->get_price() ) ) . '</li>';
		}
		$items  = $items ? '<p><strong>Vi lægger disse varer frem til dig:</strong></p><ul>' . $items . '</ul>' : '';
		$mailer = WC()->mailer();

		$customer = '<p>Hej ' . esc_html( $b['name'] ) . '</p>'
			. '<p>Tak for din booking. Vi glæder os til at se dig.</p>'
			. '<p><strong>Hvornår:</strong> ' . esc_html( $when ) . '<br><strong>Hvor:</strong> ' . $where . '<br><strong>Formål:</strong> ' . esc_html( $b['purpose'] ) . '</p>'
			. $items
			. '<p>Husk gyldigt billed-ID ved køb og salg. Er du forhindret, så ring venligst på ' . esc_html( $b['store']['phone'] ?: ns_opt( 'phone' ) ) . '.</p>';
		$mailer->send( $b['email'], 'Din tid hos JustSilver er booket', $mailer->wrap_message( 'Din tid er booket', $customer ) );

		$to = $b['store']['email'] ?: ns_opt( 'notify_email', get_option( 'admin_email' ) );
		$staff = '<p><strong>' . esc_html( $b['name'] ) . '</strong> har booket en tid.</p>'
			. '<p>' . esc_html( $when ) . ' · ' . esc_html( $b['store']['name'] ) . '<br>Formål: ' . esc_html( $b['purpose'] ) . '<br>Tlf.: ' . esc_html( $b['phone'] ) . '<br>E-mail: ' . esc_html( $b['email'] ) . '</p>'
			. ( $b['note'] ? '<p>Bemærkning: ' . nl2br( esc_html( $b['note'] ) ) . '</p>' : '' )
			. $items
			. '<p><a href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ) . '">Åbn bookingen</a></p>';
		$mailer->send( $to, 'Ny booking: ' . $b['name'] . ' – ' . $when, $mailer->wrap_message( 'Ny sølv-booking', $staff ) );
	}

	public static function shortcode(): string {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['ns_bekraeftet'], $_GET['k'] ) ) {
			$id = absint( $_GET['ns_bekraeftet'] );
			if ( hash_equals( substr( wp_hash( 'ns' . $id ), 0, 12 ), sanitize_text_field( wp_unslash( $_GET['k'] ) ) ) && self::CPT === get_post_type( $id ) ) {
				$b = self::details( $id );
				ob_start();
				include ns_template( 'booking-done.php' );
				return ob_get_clean();
			}
		}
		// phpcs:enable
		$s = self::state();
		NS_Frontend::enqueue();
		ob_start();
		if ( function_exists( 'wc_print_notices' ) ) {
			wc_print_notices();
		}
		include ns_template( 'booking-form.php' );
		return ob_get_clean();
	}

	/* -------------------------------------------------------------- Admin */

	public static function columns( array $cols ): array {
		return array(
			'cb'          => $cols['cb'],
			'ns_when'     => 'Tidspunkt',
			'title'       => 'Kunde',
			'ns_store'    => 'Butik',
			'ns_purpose'  => 'Formål',
			'ns_contact'  => 'Kontakt',
			'ns_products' => 'Varer',
			'ns_status'   => 'Status',
		);
	}

	public static function column( string $col, int $id ): void {
		$b = self::details( $id );
		switch ( $col ) {
			case 'ns_when':
				echo $b['date'] ? esc_html( wp_date( 'D j. M Y', $b['date']->getTimestamp() ) . ' kl. ' . $b['time'] ) : '';
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
				echo esc_html( implode( ', ', array_map( fn( $p ) => $p->get_name(), $b['products'] ) ) ?: '–' );
				break;
			case 'ns_status':
				echo '<mark class="order-status status-' . esc_attr( 'aflyst' === $b['status'] ? 'cancelled' : ( 'gennemfoert' === $b['status'] ? 'completed' : 'processing' ) ) . '"><span>' . esc_html( self::statuses()[ $b['status'] ] ?? $b['status'] ) . '</span></mark>';
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
		echo '<select name="ns_when"><option value="kommende"' . selected( $cur_when, 'kommende', false ) . '>Kommende</option><option value="idag"' . selected( $cur_when, 'idag', false ) . '>I dag</option><option value="alle"' . selected( $cur_when, 'alle', false ) . '>Alle datoer</option></select>';
		echo '<select name="ns_store"><option value="">Alle butikker</option>';
		foreach ( NS_Stores::all( false ) as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '"' . selected( $cur_store, $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
		}
		echo '</select><select name="ns_status"><option value="">Alle statusser</option>';
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
		$order = ( 'desc' === strtolower( (string) $q->get( 'order' ) ) && 'ns_when' === $q->get( 'orderby' ) ) ? 'DESC' : ( 'kommende' === $when || 'idag' === $when ? 'ASC' : 'DESC' );
		$q->set( 'orderby', array( 'ns_d' => $order, 'ns_t' => $order ) );
		// phpcs:enable
	}

	public static function meta_boxes(): void {
		add_meta_box( 'ns_booking', 'Booking', array( __CLASS__, 'box' ), self::CPT, 'normal', 'high' );
	}

	public static function box( WP_Post $post ): void {
		$b = self::details( $post->ID );
		wp_nonce_field( 'ns_booking_admin', 'ns_booking_admin_nonce' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>Butik</th><td><select name="ns_store">';
		foreach ( NS_Stores::all( false ) as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '"' . selected( $b['store']['id'] ?? 0, $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
		}
		echo '</select></td></tr>';
		printf( '<tr><th>Dato / tid</th><td><input type="date" name="ns_date" value="%s"> <input type="time" name="ns_time" value="%s"></td></tr>', esc_attr( $b['date'] ? $b['date']->format( 'Y-m-d' ) : '' ), esc_attr( $b['time'] ) );
		echo '<tr><th>Status</th><td><select name="ns_status">';
		foreach ( self::statuses() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $b['status'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>Formål</th><td><select name="ns_purpose">';
		$cur = get_post_meta( $post->ID, '_ns_purpose', true );
		foreach ( self::purposes() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		foreach ( array( 'name' => 'Navn', 'email' => 'E-mail', 'phone' => 'Telefon' ) as $k => $label ) {
			printf( '<tr><th>%s</th><td><input class="regular-text" name="ns_%s" value="%s"></td></tr>', esc_html( $label ), esc_attr( $k ), esc_attr( $b[ $k ] ) );
		}
		printf( '<tr><th>Bemærkning</th><td><textarea class="large-text" rows="3" name="ns_note">%s</textarea></td></tr>', esc_textarea( $b['note'] ) );
		echo '<tr><th>Varer kunden vil se</th><td>';
		foreach ( $b['products'] as $p ) {
			echo '<a href="' . esc_url( get_edit_post_link( $p->get_id() ) ) . '">' . esc_html( $p->get_name() ) . '</a> (' . esc_html( $p->get_sku() ) . ', lager: ' . esc_html( (string) $p->get_stock_quantity() ) . ')<br>';
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
		$status = sanitize_key( $_POST['ns_status'] ?? '' );
		if ( isset( self::statuses()[ $status ] ) ) {
			update_post_meta( $post_id, '_ns_status', $status );
		}
		$purpose = sanitize_key( $_POST['ns_purpose'] ?? '' );
		if ( isset( self::purposes()[ $purpose ] ) ) {
			update_post_meta( $post_id, '_ns_purpose', $purpose );
		}
		update_post_meta( $post_id, '_ns_name', sanitize_text_field( wp_unslash( $_POST['ns_name'] ?? '' ) ) );
		update_post_meta( $post_id, '_ns_email', sanitize_email( wp_unslash( $_POST['ns_email'] ?? '' ) ) );
		update_post_meta( $post_id, '_ns_phone', sanitize_text_field( wp_unslash( $_POST['ns_phone'] ?? '' ) ) );
		update_post_meta( $post_id, '_ns_note', sanitize_textarea_field( wp_unslash( $_POST['ns_note'] ?? '' ) ) );
	}
}
