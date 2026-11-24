<?php
/**
 * The daily silver spot price, its history, and repricing of all spot-priced products.
 *
 * Price: option "ns_silver_price" (DKK per kg fine silver). History: "ns_price_history".
 * Repricing runs in the background in batches via WooCommerce' Action Scheduler.
 */
defined( 'ABSPATH' ) || exit;

class NS_Silver_Price {

	const BATCH = 100;

	public static function init(): void {
		add_action( 'ns_reprice_batch', array( __CLASS__, 'reprice_batch' ), 10, 1 );
	}

	public static function per_kg(): float {
		return (float) get_option( 'ns_silver_price', 0 );
	}

	public static function per_gram(): float {
		return self::per_kg() / 1000;
	}

	public static function updated_at(): int {
		return (int) get_option( 'ns_silver_price_updated', 0 );
	}

	/** @return array<int,array{t:int,p:float,s:string}> */
	public static function history(): array {
		return array_values( (array) get_option( 'ns_price_history', array() ) );
	}

	/**
	 * Change since roughly 24 hours ago (or the oldest point if newer).
	 *
	 * @return array{abs:float,pct:float}|null
	 */
	public static function change( int $seconds = DAY_IN_SECONDS ): ?array {
		$history = self::history();
		$now     = self::per_kg();
		if ( count( $history ) < 2 || $now <= 0 ) {
			return null;
		}
		$target = time() - $seconds;
		$ref    = $history[0]['p'];
		foreach ( $history as $h ) {
			if ( $h['t'] <= $target ) {
				$ref = $h['p'];
			}
		}
		if ( $ref <= 0 ) {
			return null;
		}
		return array( 'abs' => $now - $ref, 'pct' => ( $now - $ref ) / $ref * 100 );
	}

	/** Set a new spot price and reprice all spot products if it changed. */
	public static function set( float $per_kg, string $source = 'manual' ): bool {
		if ( $per_kg <= 0 ) {
			return false;
		}
		$old = self::per_kg();
		update_option( 'ns_silver_price', round( $per_kg, 2 ) );
		update_option( 'ns_silver_price_updated', time() );

		$history   = self::history();
		$history[] = array( 't' => time(), 'p' => round( $per_kg, 2 ), 's' => $source );
		update_option( 'ns_price_history', array_slice( $history, -1000 ), false );

		if ( abs( $per_kg - $old ) >= 0.01 ) {
			self::schedule_reprice();
		}
		do_action( 'justsilver_price_updated', $per_kg, $old, $source );
		return true;
	}

	/** Selling price for a product at the current (or given) spot price. */
	public static function compute( WC_Product $product, ?float $per_kg = null ): ?float {
		$premium = $product->get_meta( '_ns_premium' );
		$premium = '' === $premium ? (float) ns_opt( 'default_premium', 12 ) : (float) $premium;
		$price   = ns_calc_price(
			(float) $product->get_meta( '_ns_weight' ),
			(float) ( $product->get_meta( '_ns_purity' ) ?: 0.999 ),
			$per_kg ?? self::per_kg(),
			$premium
		);
		return null === $price ? null : (float) apply_filters( 'justsilver_computed_price', $price, $product, $per_kg ?? self::per_kg() );
	}

	public static function schedule_reprice(): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$page = 1;
			while ( self::reprice_batch( $page ) ) {
				$page++;
			}
			return;
		}
		as_unschedule_all_actions( 'ns_reprice_batch' );
		as_enqueue_async_action( 'ns_reprice_batch', array( 1 ), 'justsilver' );
	}

	/** Reprice one page of spot products; queue the next. Returns true if there are more. */
	public static function reprice_batch( $page = 1 ): bool {
		$page = max( 1, (int) $page );
		$ids  = self::spot_product_ids( $page );
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->save(); // price is set in NS_Product_Fields::before_save
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
