<?php
/**
 * Sølvets dagspris og genberegning af alle spot-baserede sølvvarer.
 *
 * Prisen gemmes i optionen "ns_silver_price" (DKK pr. kg rent sølv) med historik i "ns_price_history".
 * Genberegning sker i baggrunden i bidder via WooCommerce' Action Scheduler, så det også virker
 * med mange tusinde varer uden at siden går i stå.
 */
defined( 'ABSPATH' ) || exit;

class NS_Silver_Price {

	const BATCH = 100;

	public static function init(): void {
		add_action( 'ns_reprice_batch', array( __CLASS__, 'reprice_batch' ), 10, 1 );
	}

	/** DKK pr. kg rent sølv (0 hvis ikke sat). */
	public static function per_kg(): float {
		return (float) get_option( 'ns_silver_price', 0 );
	}

	public static function per_gram(): float {
		return self::per_kg() / 1000;
	}

	public static function updated_at(): int {
		return (int) get_option( 'ns_silver_price_updated', 0 );
	}

	/**
	 * Sæt ny dagspris. Starter genberegning af alle spot-varer hvis prisen har ændret sig.
	 *
	 * @param string $source "manuel", "api" el.lign. – vises i historikken.
	 */
	public static function set( float $per_kg, string $source = 'manuel' ): bool {
		if ( $per_kg <= 0 ) {
			return false;
		}
		$old = self::per_kg();
		update_option( 'ns_silver_price', round( $per_kg, 2 ) );
		update_option( 'ns_silver_price_updated', time() );

		$history   = (array) get_option( 'ns_price_history', array() );
		$history[] = array( 't' => time(), 'p' => round( $per_kg, 2 ), 's' => $source );
		update_option( 'ns_price_history', array_slice( $history, -200 ), false );

		if ( abs( $per_kg - $old ) >= 0.01 ) {
			self::schedule_reprice();
		}
		do_action( 'justsilver_price_updated', $per_kg, $old, $source );
		return true;
	}

	/** Beregn salgsprisen for et produkt ud fra dagsprisen. */
	public static function compute( WC_Product $product, ?float $per_kg = null ): ?float {
		$per_kg  = $per_kg ?? self::per_kg();
		$weight  = (float) $product->get_meta( '_ns_weight' );
		$purity  = (float) ( $product->get_meta( '_ns_purity' ) ?: 0.999 );
		$premium = $product->get_meta( '_ns_premium' );
		$premium = '' === $premium ? (float) ns_opt( 'default_premium', 12 ) : (float) $premium;
		if ( $per_kg <= 0 || $weight <= 0 ) {
			return null;
		}
		$metal = $weight * $purity * ( $per_kg / 1000 );
		$price = $metal * ( 1 + $premium / 100 );
		$price = (float) apply_filters( 'justsilver_computed_price', round( $price ), $product, $per_kg );
		return max( 1, $price );
	}

	public static function schedule_reprice(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			// Fallback uden Action Scheduler: kør alt med det samme.
			$page = 1;
			while ( self::reprice_batch( $page ) ) {
				$page++;
			}
			return;
		}
		as_unschedule_all_actions( 'ns_reprice_batch' );
		as_enqueue_async_action( 'ns_reprice_batch', array( 1 ), 'justsilver' );
	}

	/**
	 * Genberegn én side spot-varer og planlæg næste side.
	 *
	 * @return bool true hvis der er flere sider.
	 */
	public static function reprice_batch( $page = 1 ): bool {
		$page = max( 1, (int) $page );
		$ids  = self::spot_product_ids( $page );
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->save(); // pris sættes i NS_Product_Fields::before_save
			}
		}
		$more = count( $ids ) === self::BATCH;
		if ( $more && function_exists( 'as_enqueue_async_action' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			as_enqueue_async_action( 'ns_reprice_batch', array( $page + 1 ), 'justsilver' );
		}
		return $more;
	}

	public static function spot_product_ids( int $page = 1 ): array {
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => self::BATCH,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array( 'key' => '_ns_is_silver', 'value' => 'yes' ),
				array(
					'relation' => 'OR',
					array( 'key' => '_ns_pricing', 'value' => 'spot' ),
					array( 'key' => '_ns_pricing', 'compare' => 'NOT EXISTS' ),
				),
			),
		) );
		return array_map( 'intval', $q->posts );
	}
}
