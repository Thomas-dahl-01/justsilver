<?php
/**
 * Activation: creates attributes, silver categories, pages and default settings.
 * Existing categories/pages are reused – nothing is overwritten.
 */
defined( 'ABSPATH' ) || exit;

class NS_Installer {

	public static function categories(): array {
		return array(
			__( 'Silver bars', 'justsilver' )               => __( 'Cast and minted bars from 1 g to 5 kg – the cheapest way to physical silver per gram.', 'justsilver' ),
			__( 'Silver coins', 'justsilver' )              => __( 'Bullion coins such as Maple Leaf, Britannia and Philharmoniker – several years and tubes.', 'justsilver' ),
			__( 'Granules & bulk', 'justsilver' )           => __( 'Silver granules and larger lots for the most metal for your money.', 'justsilver' ),
			__( 'Jewellery', 'justsilver' )                 => __( 'Sterling silver jewellery – rings, chains, bracelets and pendants, new and pre-owned.', 'justsilver' ),
			__( 'Cutlery & tableware', 'justsilver' )       => __( 'Danish silver cutlery in classic patterns. Buy single pieces and complete your set.', 'justsilver' ),
			__( 'Antiques & collectibles', 'justsilver' )   => __( 'Candlesticks, bowls and jugs with a history – selected and valued by our experts.', 'justsilver' ),
		);
	}

	public static function activate(): void {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return;
		}
		load_plugin_textdomain( 'justsilver', false, dirname( plugin_basename( NS_FILE ) ) . '/languages' );
		self::attributes();
		$settings = (array) get_option( 'ns_settings', array() );

		if ( empty( $settings['silver_category'] ) || ! term_exists( (int) $settings['silver_category'], 'product_cat' ) ) {
			$name   = __( 'Silver', 'justsilver' );
			$parent = term_exists( $name, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', array( 'slug' => sanitize_title( _x( 'silver', 'category slug', 'justsilver' ) ), 'description' => __( 'Bullion silver, coins, jewellery and tableware – priced from today’s silver spot price.', 'justsilver' ) ) );
			if ( ! is_wp_error( $parent ) ) {
				$settings['silver_category'] = (int) $parent['term_id'];
				$i                           = 0;
				foreach ( self::categories() as $child_name => $desc ) {
					$child = term_exists( $child_name, 'product_cat', $settings['silver_category'] ) ?: wp_insert_term( $child_name, 'product_cat', array( 'parent' => $settings['silver_category'], 'description' => $desc ) );
					if ( ! is_wp_error( $child ) ) {
						update_term_meta( (int) $child['term_id'], 'order', $i++ );
					}
				}
			}
		}

		if ( empty( $settings['booking_page'] ) || ! get_post( (int) $settings['booking_page'] ) ) {
			$settings['booking_page'] = self::page( __( 'Book a visit', 'justsilver' ), _x( 'book-a-visit', 'page slug', 'justsilver' ), 'justsilver/booking' );
		}
		if ( empty( $settings['silver_page'] ) || ! get_post( (int) $settings['silver_page'] ) ) {
			$settings['silver_page'] = self::page( __( 'Silver', 'justsilver' ), _x( 'silver-shop', 'page slug', 'justsilver' ), 'justsilver/forside' );
		}

		$settings += array(
			'default_premium'    => '12',
			'buyback_margin'     => '15',
			'api_interval'       => 'hourly',
			'sync_pickup'        => 'yes',
			'silver_design'      => 'yes',
			'reserve_items'      => 'yes',
			'reserve_grace_days' => '1',
			'reminders'          => 'yes',
			'reminder_hour'      => '10',
			'animations'         => 'yes',
			'brand_login'        => 'yes',
		);
		update_option( 'ns_settings', $settings );
		NS_Price_API::reschedule();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		foreach ( array( NS_Price_API::HOOK, NS_Reservations::CRON, NS_Reminders::CRON ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'ns_reprice_batch' );
		}
	}

	private static function attributes(): void {
		foreach ( NS_Product_Fields::attributes() as $slug => $name ) {
			if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
				wc_create_attribute( array( 'name' => $name, 'slug' => $slug, 'type' => 'select', 'order_by' => 'name', 'has_archives' => false ) );
			}
		}
		delete_transient( 'wc_attribute_taxonomies' );
	}

	/** Create a page holding one JustSilver block (reuses an existing page with the slug). */
	private static function page( string $title, string $slug, string $block ): int {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			return $existing->ID;
		}
		return (int) wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => '<!-- wp:' . $block . ' {"align":"wide"} /-->',
		) );
	}
}
