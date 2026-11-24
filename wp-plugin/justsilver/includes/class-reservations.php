<?php
/**
 * Reserve the items a customer wants to see, so nobody buys them online before the visit.
 *
 * - On booking: 1 of each item (with stock management) is taken out of stock.
 * - Cancelled / no-show / trashed: stock is put back.
 * - Completed: the reservation counts as sold (stock stays reduced).
 * - Daily: visits that passed without being completed are released after a grace period.
 */
defined( 'ABSPATH' ) || exit;

class NS_Reservations {

	const CRON = 'ns_release_expired_reservations';

	public static function init(): void {
		add_action( 'justsilver_booking_created', array( __CLASS__, 'reserve' ) );
		add_action( 'justsilver_booking_status_changed', array( __CLASS__, 'on_status' ), 10, 2 );
		add_action( 'wp_trash_post', array( __CLASS__, 'on_trash' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_trash' ) );
		add_action( self::CRON, array( __CLASS__, 'release_expired' ) );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:00' ), 'daily', self::CRON );
		}
	}

	public static function enabled(): bool {
		return 'yes' === ns_opt( 'reserve_items', 'yes' );
	}

	public static function reserve( int $booking_id ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$reserved = array();
		foreach ( (array) get_post_meta( $booking_id, '_ns_products', true ) as $pid ) {
			$p = wc_get_product( $pid );
			if ( $p && $p->managing_stock() && $p->get_stock_quantity() > 0 ) {
				wc_update_product_stock( $p, 1, 'decrease' );
				$reserved[] = (int) $pid;
			}
		}
		if ( $reserved ) {
			update_post_meta( $booking_id, '_ns_reserved', $reserved );
		}
	}

	public static function release( int $booking_id ): int {
		$reserved = (array) get_post_meta( $booking_id, '_ns_reserved', true );
		$count    = 0;
		foreach ( array_filter( $reserved ) as $pid ) {
			$p = wc_get_product( $pid );
			if ( $p && $p->managing_stock() ) {
				wc_update_product_stock( $p, 1, 'increase' );
				$count++;
			}
		}
		delete_post_meta( $booking_id, '_ns_reserved' );
		return $count;
	}

	public static function on_status( int $booking_id, string $status ): void {
		if ( in_array( $status, array( 'aflyst', 'udeblevet' ), true ) ) {
			self::release( $booking_id );
		} elseif ( 'gennemfoert' === $status ) {
			delete_post_meta( $booking_id, '_ns_reserved' ); // sold in store – keep stock reduced
		}
	}

	public static function on_trash( $post_id ): void {
		if ( NS_Bookings::CPT === get_post_type( $post_id ) && get_post_meta( $post_id, '_ns_reserved', true ) ) {
			self::release( (int) $post_id );
		}
	}

	/** Release reservations for visits more than N days ago that were never marked completed. */
	public static function release_expired(): int {
		$grace  = max( 0, (int) ns_opt( 'reserve_grace_days', 1 ) );
		$before = wp_date( 'Y-m-d', strtotime( "-{$grace} days" ) );
		$ids    = get_posts( array(
			'post_type'      => NS_Bookings::CPT,
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'meta_query'     => array(
				array( 'key' => '_ns_reserved', 'compare' => 'EXISTS' ),
				array( 'key' => '_ns_date', 'value' => $before, 'compare' => '<' ),
				array( 'key' => '_ns_status', 'value' => array( 'booket', 'bekraeftet' ), 'compare' => 'IN' ),
			),
		) );
		foreach ( $ids as $id ) {
			self::release( (int) $id );
		}
		return count( $ids );
	}
}
