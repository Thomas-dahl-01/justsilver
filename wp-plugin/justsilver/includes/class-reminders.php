<?php
/**
 * Reminder mail the day before the visit (hourly WP-Cron check, sent once per booking).
 */
defined( 'ABSPATH' ) || exit;

class NS_Reminders {

	const CRON = 'ns_send_booking_reminders';

	public static function init(): void {
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON );
		}
	}

	/** Send reminders for tomorrow's visits after the configured hour. Returns number sent. */
	public static function run(): int {
		if ( 'yes' !== ns_opt( 'reminders', 'yes' ) || (int) wp_date( 'G' ) < (int) ns_opt( 'reminder_hour', 10 ) ) {
			return 0;
		}
		$tomorrow = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
		$ids      = get_posts( array(
			'post_type'      => NS_Bookings::CPT,
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_query'     => array(
				array( 'key' => '_ns_date', 'value' => $tomorrow ),
				array( 'key' => '_ns_status', 'value' => array( 'booket', 'bekraeftet' ), 'compare' => 'IN' ),
				array( 'key' => '_ns_reminded', 'compare' => 'NOT EXISTS' ),
			),
		) );
		foreach ( $ids as $id ) {
			self::send( (int) $id );
		}
		return count( $ids );
	}

	public static function send( int $id ): void {
		$b = NS_Bookings::details( $id );
		if ( ! $b['store'] || ! $b['date'] || ! is_email( $b['email'] ) ) {
			return;
		}
		$mailer = WC()->mailer();
		$cancel = NS_Bookings::url( array( 'ns_aflys' => $id, 'k' => NS_Bookings::token( $id, 'cancel' ) ) );
		$body   = '<p>' . sprintf( /* translators: %s: name */ esc_html__( 'Hi %s', 'justsilver' ), esc_html( $b['name'] ) ) . '</p>'
			. '<p>' . esc_html__( 'A friendly reminder that we are expecting you tomorrow.', 'justsilver' ) . '</p>'
			. '<p><strong>' . esc_html__( 'When:', 'justsilver' ) . '</strong> ' . esc_html( NS_Bookings::when_text( $b ) )
			. '<br><strong>' . esc_html__( 'Where:', 'justsilver' ) . '</strong> ' . esc_html( NS_Bookings::where_text( $b ) ) . '</p>'
			. NS_Bookings::items_html( $b )
			. '<p><a href="' . esc_url( $cancel ) . '">' . esc_html__( 'Can’t make it? Cancel your booking', 'justsilver' ) . '</a></p>';
		$mailer->send( $b['email'], __( 'Reminder: your visit to JustSilver tomorrow', 'justsilver' ), $mailer->wrap_message( __( 'See you tomorrow', 'justsilver' ), $body ) );
		update_post_meta( $id, '_ns_reminded', time() );
	}
}
