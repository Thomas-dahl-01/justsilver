<?php
/**
 * Aktivering: opretter egenskaber, sølvkategorier, sider og standardindstillinger.
 * Eksisterende kategorier/sider genbruges – intet overskrives.
 */
defined( 'ABSPATH' ) || exit;

class NS_Installer {

	const CATEGORIES = array(
		'Sølvbarrer'             => 'Støbte og prægede barrer fra 1 g til 5 kg – den billigste vej til fysisk sølv pr. gram.',
		'Sølvmønter'             => 'Investeringsmønter som Maple Leaf, Britannia og Philharmoniker – flere årgange og tuber.',
		'Granulat & bulk'        => 'Sølvgranulat og større partier til dig, der vil have mest muligt metal for pengene.',
		'Smykker'                => 'Sterlingsølv-smykker – ringe, kæder, armbånd og vedhæng, nye og brugte.',
		'Sølvtøj & bestik'       => 'Dansk sølvbestik i klassiske mønstre. Køb enkeltdele og supplér dit stel.',
		'Antik & samlerobjekter' => 'Lysestager, skåle og kander med historie – udvalgt og vurderet af vores eksperter.',
	);

	public static function activate(): void {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return;
		}
		self::attributes();
		$settings = (array) get_option( 'ns_settings', array() );

		// Sølv-kategori + underkategorier
		if ( empty( $settings['silver_category'] ) || ! term_exists( (int) $settings['silver_category'], 'product_cat' ) ) {
			$parent = term_exists( 'Sølv', 'product_cat' ) ?: wp_insert_term( 'Sølv', 'product_cat', array( 'slug' => 'soelv', 'description' => 'Investeringssølv, mønter, smykker og sølvtøj – prissat efter dagens sølvkurs.' ) );
			if ( ! is_wp_error( $parent ) ) {
				$settings['silver_category'] = (int) $parent['term_id'];
				$i = 0;
				foreach ( self::CATEGORIES as $name => $desc ) {
					$child = term_exists( $name, 'product_cat', $settings['silver_category'] ) ?: wp_insert_term( $name, 'product_cat', array( 'parent' => $settings['silver_category'], 'description' => $desc ) );
					if ( ! is_wp_error( $child ) ) {
						update_term_meta( (int) $child['term_id'], 'order', $i++ );
					}
				}
			}
		}

		// Sider
		if ( empty( $settings['booking_page'] ) || ! get_post( (int) $settings['booking_page'] ) ) {
			$settings['booking_page'] = self::page( 'Book tid', 'book-tid', '[justsilver_booking]' );
		}
		if ( empty( $settings['silver_page'] ) || ! get_post( (int) $settings['silver_page'] ) ) {
			$settings['silver_page'] = self::page( 'Sølv', 'soelv-forside', '[justsilver_forside]' );
		}

		$settings += array(
			'default_premium' => '12',
			'api_interval'    => 'hourly',
			'sync_pickup'     => 'yes',
			'silver_design'   => 'yes',
		);
		update_option( 'ns_settings', $settings );
		NS_Price_API::reschedule();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( NS_Price_API::HOOK );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'ns_reprice_batch' );
		}
	}

	private static function attributes(): void {
		foreach ( NS_Product_Fields::ATTRIBUTES as $slug => $name ) {
			if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
				wc_create_attribute( array( 'name' => $name, 'slug' => $slug, 'type' => 'select', 'order_by' => 'name', 'has_archives' => false ) );
			}
		}
		delete_transient( 'wc_attribute_taxonomies' );
	}

	private static function page( string $title, string $slug, string $content ): int {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			return $existing->ID;
		}
		return (int) wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => '<!-- wp:shortcode -->' . $content . '<!-- /wp:shortcode -->',
		) );
	}
}
