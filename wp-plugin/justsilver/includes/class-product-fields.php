<?php
/**
 * "Silver" tab on WooCommerce products: weight, purity, type, mint …
 *
 * - The price is recalculated from the spot price on every save (admin, CSV import, REST, CLI).
 * - Type/purity/mint/weight are synced to global attributes so WooCommerce' own filters work.
 */
defined( 'ABSPATH' ) || exit;

class NS_Product_Fields {

	/** Global attributes (slug => label) kept in sync. Slugs stay stable across languages. */
	public static function attributes(): array {
		return array(
			'soelvtype' => __( 'Silver type', 'justsilver' ),
			'finhed'    => __( 'Purity', 'justsilver' ),
			'praeger'   => __( 'Mint', 'justsilver' ),
			'vaegt'     => __( 'Weight', 'justsilver' ),
		);
	}

	public static function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_admin' ) );
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'before_save' ), 20 );

		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );

		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'csv_mapping_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'csv_default_columns' ) );
		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'csv_export_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( __CLASS__, 'csv_export_columns' ) );
		foreach ( array_keys( self::csv_columns() ) as $col ) {
			add_filter( "woocommerce_product_export_product_column_{$col}", function ( $value, $product ) use ( $col ) {
				return $product->get_meta( '_' . $col );
			}, 10, 2 );
		}
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( __CLASS__, 'csv_import' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ Admin */

	public static function tab( array $tabs ): array {
		$tabs['justsilver'] = array(
			'label'    => __( 'Silver', 'justsilver' ),
			'target'   => 'ns_silver_data',
			'class'    => array( 'show_if_simple' ),
			'priority' => 15,
		);
		return $tabs;
	}

	public static function panel(): void {
		global $product_object;
		$p = $product_object;
		echo '<div id="ns_silver_data" class="panel woocommerce_options_panel"><div class="options_group">';
		woocommerce_wp_checkbox( array(
			'id'          => '_ns_is_silver',
			'label'       => __( 'Silver product', 'justsilver' ),
			'description' => __( 'Tick to price and show this product as silver.', 'justsilver' ),
			'value'       => $p->get_meta( '_ns_is_silver' ),
		) );
		woocommerce_wp_select( array(
			'id'      => '_ns_kind',
			'label'   => __( 'Type', 'justsilver' ),
			'options' => array( '' => __( '– choose –', 'justsilver' ) ) + ns_kinds(),
			'value'   => $p->get_meta( '_ns_kind' ),
		) );
		woocommerce_wp_text_input( array(
			'id'                => '_ns_weight',
			'label'             => __( 'Weight (grams)', 'justsilver' ),
			'type'              => 'number',
			'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
			'desc_tip'          => true,
			'description'       => __( 'Gross weight. 1 oz = 31.10 g.', 'justsilver' ),
			'value'             => $p->get_meta( '_ns_weight' ),
		) );
		woocommerce_wp_select( array(
			'id'      => '_ns_purity',
			'label'   => __( 'Purity', 'justsilver' ),
			'options' => ns_purities(),
			'value'   => $p->get_meta( '_ns_purity' ) ?: '0.999',
		) );
		woocommerce_wp_text_input( array( 'id' => '_ns_mint', 'label' => __( 'Mint / maker', 'justsilver' ), 'value' => $p->get_meta( '_ns_mint' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_ns_year', 'label' => __( 'Year', 'justsilver' ), 'type' => 'number', 'value' => $p->get_meta( '_ns_year' ) ) );
		woocommerce_wp_select( array(
			'id'      => '_ns_condition',
			'label'   => __( 'Condition', 'justsilver' ),
			'options' => ns_conditions(),
			'value'   => $p->get_meta( '_ns_condition' ) ?: 'ny',
		) );
		echo '</div><div class="options_group">';
		woocommerce_wp_select( array(
			'id'      => '_ns_pricing',
			'label'   => __( 'Pricing', 'justsilver' ),
			'options' => array(
				'spot' => __( 'Spot price + premium (automatic)', 'justsilver' ),
				'fast' => __( 'Fixed price (use the General tab)', 'justsilver' ),
			),
			'value'   => $p->get_meta( '_ns_pricing' ) ?: 'spot',
		) );
		woocommerce_wp_text_input( array(
			'id'                => '_ns_premium',
			'label'             => __( 'Premium over spot (%)', 'justsilver' ),
			'type'              => 'number',
			'custom_attributes' => array( 'step' => '0.1' ),
			'placeholder'       => (string) ns_opt( 'default_premium', 12 ),
			'desc_tip'          => true,
			'description'       => __( 'Empty = the default premium from the settings.', 'justsilver' ),
			'value'             => $p->get_meta( '_ns_premium' ),
		) );
		woocommerce_wp_checkbox( array(
			'id'          => '_ns_view_in_store',
			'label'       => __( 'Can be seen in store', 'justsilver' ),
			'description' => __( 'Show the "Book a visit and see it in store" button.', 'justsilver' ),
			'value'       => $p->get_meta( '_ns_view_in_store' ) ?: 'yes',
		) );
		$spot = NS_Silver_Price::per_kg();
		echo '<p class="form-field"><label>' . esc_html__( 'Spot price', 'justsilver' ) . '</label><span>'
			. ( $spot
				/* translators: %s: price per kg */
				? esc_html( sprintf( __( '%s per kg fine silver', 'justsilver' ), ns_dkk( $spot ) ) )
				: esc_html__( 'Not set – see WooCommerce → JustSilver', 'justsilver' ) )
			. '</span></p></div></div>';
	}

	public static function save_admin( WC_Product $product ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce has verified the nonce.
		$product->update_meta_data( '_ns_is_silver', isset( $_POST['_ns_is_silver'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_ns_view_in_store', isset( $_POST['_ns_view_in_store'] ) ? 'yes' : 'no' );
		foreach ( array( '_ns_weight', '_ns_premium', '_ns_year' ) as $key ) {
			$product->update_meta_data( $key, isset( $_POST[ $key ] ) ? wc_format_decimal( wp_unslash( $_POST[ $key ] ) ) : '' );
		}
		foreach ( array( '_ns_kind' => ns_kinds(), '_ns_purity' => ns_purities(), '_ns_condition' => ns_conditions(), '_ns_pricing' => array( 'spot' => 1, 'fast' => 1 ) ) as $key => $allowed ) {
			$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$product->update_meta_data( $key, array_key_exists( $val, $allowed ) ? $val : '' );
		}
		$product->update_meta_data( '_ns_mint', isset( $_POST['_ns_mint'] ) ? sanitize_text_field( wp_unslash( $_POST['_ns_mint'] ) ) : '' );
		// phpcs:enable
	}

	/** Runs on EVERY save: set price and sync attributes. */
	public static function before_save( WC_Product $product ): void {
		if ( 'yes' !== $product->get_meta( '_ns_is_silver' ) || ! $product->is_type( 'simple' ) ) {
			return;
		}
		if ( 'fast' !== $product->get_meta( '_ns_pricing' ) ) {
			$price = NS_Silver_Price::compute( $product );
			if ( null !== $price ) {
				$price = wc_format_decimal( $price, 0 );
				if ( $product->get_regular_price( 'edit' ) !== $price ) {
					$product->set_regular_price( $price );
				}
				if ( '' === $product->get_sale_price( 'edit' ) || ! $product->is_on_sale( 'edit' ) ) {
					$product->set_price( $price );
				}
			}
		}
		self::sync_attributes( $product );
	}

	public static function sync_attributes( WC_Product $product ): void {
		$values = array(
			'soelvtype' => ns_kinds()[ $product->get_meta( '_ns_kind' ) ] ?? '',
			'finhed'    => $product->get_meta( '_ns_purity' ) ? ns_purity_label( $product->get_meta( '_ns_purity' ) ) : '',
			'praeger'   => (string) $product->get_meta( '_ns_mint' ),
			'vaegt'     => $product->get_meta( '_ns_weight' ) ? ns_grams( $product->get_meta( '_ns_weight' ) ) : '',
		);
		$attributes = $product->get_attributes( 'edit' );
		$position   = 10;
		foreach ( $values as $slug => $value ) {
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
					continue;
				}
				register_taxonomy( $taxonomy, 'product', array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
			}
			if ( '' === $value ) {
				unset( $attributes[ $taxonomy ] );
				continue;
			}
			$term = term_exists( $value, $taxonomy ) ?: wp_insert_term( $value, $taxonomy );
			if ( is_wp_error( $term ) ) {
				continue;
			}
			$term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			$current = $attributes[ $taxonomy ] ?? null;
			if ( $current && array( $term_id ) === array_map( 'intval', $current->get_options() ) ) {
				continue;
			}
			$attr = new WC_Product_Attribute();
			$attr->set_id( wc_attribute_taxonomy_id_by_name( $slug ) );
			$attr->set_name( $taxonomy );
			$attr->set_options( array( $term_id ) );
			$attr->set_position( $position++ );
			$attr->set_visible( true );
			$attr->set_variation( false );
			$attributes[ $taxonomy ] = $attr;
		}
		$product->set_attributes( $attributes );
	}

	/* ------------------------------------------------------- Product list */

	public static function columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'price' === $key ) {
				$new['ns_silver'] = __( 'Silver', 'justsilver' );
			}
		}
		return $new;
	}

	public static function column( string $col, int $post_id ): void {
		if ( 'ns_silver' !== $col ) {
			return;
		}
		$p = wc_get_product( $post_id );
		if ( ! ns_is_silver( $p ) ) {
			echo '–';
			return;
		}
		$pricing = 'fast' === $p->get_meta( '_ns_pricing' ) ? __( 'fixed price', 'justsilver' ) : __( 'spot price', 'justsilver' );
		echo esc_html( ns_grams( $p->get_meta( '_ns_weight' ) ) . ' · ' . ns_purity_label( $p->get_meta( '_ns_purity' ) ?: 0.999 ) . ' · ' . $pricing );
	}

	/* --------------------------------------------------------------- CSV */

	public static function csv_columns(): array {
		return array(
			'ns_is_silver'     => __( 'Silver: silver product (yes/no)', 'justsilver' ),
			'ns_kind'          => __( 'Silver: type', 'justsilver' ),
			'ns_weight'        => __( 'Silver: weight (g)', 'justsilver' ),
			'ns_purity'        => __( 'Silver: purity', 'justsilver' ),
			'ns_mint'          => __( 'Silver: mint', 'justsilver' ),
			'ns_year'          => __( 'Silver: year', 'justsilver' ),
			'ns_condition'     => __( 'Silver: condition', 'justsilver' ),
			'ns_pricing'       => __( 'Silver: pricing (spot/fast)', 'justsilver' ),
			'ns_premium'       => __( 'Silver: premium %', 'justsilver' ),
			'ns_view_in_store' => __( 'Silver: can be seen in store (yes/no)', 'justsilver' ),
		);
	}

	public static function csv_mapping_options( array $options ): array {
		return $options + self::csv_columns();
	}

	public static function csv_default_columns( array $columns ): array {
		foreach ( self::csv_columns() as $key => $label ) {
			$columns[ $label ] = $key;
			$columns[ $key ]   = $key;
		}
		return $columns;
	}

	public static function csv_export_columns( array $columns ): array {
		return $columns + self::csv_columns();
	}

	/** Pure mapping of one CSV row to meta values (unit tested). */
	public static function csv_to_meta( array $data ): array {
		$meta = array();
		foreach ( array( 'ns_is_silver', 'ns_kind', 'ns_weight', 'ns_purity', 'ns_mint', 'ns_year', 'ns_condition', 'ns_pricing', 'ns_premium', 'ns_view_in_store' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || '' === trim( (string) $data[ $key ] ) ) {
				continue;
			}
			$value = trim( (string) $data[ $key ] );
			if ( 'ns_purity' === $key ) {
				$value = ns_normalize_purity( $value );
			} elseif ( in_array( $key, array( 'ns_weight', 'ns_premium' ), true ) ) {
				$value = (string) (float) str_replace( ',', '.', $value );
			} elseif ( 'ns_kind' === $key ) {
				$lower   = mb_strtolower( $value );
				$aliases = array(
					'bar' => 'barre', 'barre' => 'barre', 'coin' => 'moent', 'mønt' => 'moent', 'moent' => 'moent',
					'granules' => 'granulat', 'granulat' => 'granulat', 'jewellery' => 'smykke', 'jewelry' => 'smykke', 'smykke' => 'smykke',
					'cutlery' => 'bestik', 'bestik' => 'bestik', 'antique' => 'antik', 'antik' => 'antik',
				);
				foreach ( ns_kinds() as $k => $label ) {
					$aliases[ mb_strtolower( $label ) ] = $k;
				}
				$value = $aliases[ $lower ] ?? $value;
			}
			$meta[ '_' . $key ] = $value;
		}
		return $meta;
	}

	public static function csv_import( WC_Product $product, array $data ): WC_Product {
		foreach ( self::csv_to_meta( $data ) as $key => $value ) {
			$product->update_meta_data( $key, $value );
		}
		return $product;
	}
}
