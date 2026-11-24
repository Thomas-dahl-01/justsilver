<?php
/**
 * "Sølv"-fanen på WooCommerce-produkter: vægt, finhed, type, præger m.m.
 *
 * - Prisen beregnes automatisk ud fra dagsprisen ved hver gemning (admin, CSV-import, REST, CLI).
 * - Type/finhed/præger/vægt synkroniseres til globale WooCommerce-egenskaber, så shoppens
 *   indbyggede filtre ("Filtrer efter egenskab"-blokken) virker på tværs af tusindvis af varer.
 */
defined( 'ABSPATH' ) || exit;

class NS_Product_Fields {

	const META = array( '_ns_is_silver', '_ns_weight', '_ns_purity', '_ns_kind', '_ns_mint', '_ns_year', '_ns_condition', '_ns_pricing', '_ns_premium', '_ns_view_in_store' );

	/** Globale egenskaber (slug => navn) der holdes i sync. */
	const ATTRIBUTES = array(
		'soelvtype' => 'Sølvtype',
		'finhed'    => 'Finhed',
		'praeger'   => 'Præger',
		'vaegt'     => 'Vægt',
	);

	public static function init(): void {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_admin' ) );
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'before_save' ), 20 );

		// Kolonne i produktlisten
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );

		// CSV-import/-eksport: gør sølvfelterne til rigtige kolonner
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'csv_mapping_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'csv_default_columns' ) );
		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'csv_export_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( __CLASS__, 'csv_export_columns' ) );
		foreach ( self::csv_columns() as $col => $label ) {
			add_filter( "woocommerce_product_export_product_column_{$col}", function ( $value, $product ) use ( $col ) {
				return $product->get_meta( '_' . $col );
			}, 10, 2 );
		}
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( __CLASS__, 'csv_import' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ Admin */

	public static function tab( array $tabs ): array {
		$tabs['justsilver'] = array(
			'label'    => 'Sølv',
			'target'   => 'ns_silver_data',
			'class'    => array( 'show_if_simple' ),
			'priority' => 15,
		);
		return $tabs;
	}

	public static function panel(): void {
		global $product_object;
		$p = $product_object;
		echo '<div id="ns_silver_data" class="panel woocommerce_options_panel">';
		echo '<div class="options_group">';
		woocommerce_wp_checkbox( array(
			'id'          => '_ns_is_silver',
			'label'       => 'Sølvvare',
			'description' => 'Markér for at varen prissættes og vises som sølv.',
			'value'       => $p->get_meta( '_ns_is_silver' ),
		) );
		woocommerce_wp_select( array(
			'id'      => '_ns_kind',
			'label'   => 'Type',
			'options' => array( '' => '– vælg –' ) + ns_kinds(),
			'value'   => $p->get_meta( '_ns_kind' ),
		) );
		woocommerce_wp_text_input( array(
			'id'                => '_ns_weight',
			'label'             => 'Vægt (gram)',
			'type'              => 'number',
			'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
			'desc_tip'          => true,
			'description'       => 'Bruttovægt. 1 oz = 31,10 g.',
			'value'             => $p->get_meta( '_ns_weight' ),
		) );
		woocommerce_wp_select( array(
			'id'      => '_ns_purity',
			'label'   => 'Finhed',
			'options' => ns_purities(),
			'value'   => $p->get_meta( '_ns_purity' ) ?: '0.999',
		) );
		woocommerce_wp_text_input( array( 'id' => '_ns_mint', 'label' => 'Præger / producent', 'value' => $p->get_meta( '_ns_mint' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_ns_year', 'label' => 'Årgang', 'type' => 'number', 'value' => $p->get_meta( '_ns_year' ) ) );
		woocommerce_wp_select( array(
			'id'      => '_ns_condition',
			'label'   => 'Stand',
			'options' => ns_conditions(),
			'value'   => $p->get_meta( '_ns_condition' ) ?: 'ny',
		) );
		echo '</div><div class="options_group">';
		woocommerce_wp_select( array(
			'id'          => '_ns_pricing',
			'label'       => 'Prissætning',
			'options'     => array( 'spot' => 'Dagspris + tillæg (automatisk)', 'fast' => 'Fast pris (brug Generelt-fanen)' ),
			'value'       => $p->get_meta( '_ns_pricing' ) ?: 'spot',
		) );
		woocommerce_wp_text_input( array(
			'id'                => '_ns_premium',
			'label'             => 'Tillæg over dagspris (%)',
			'type'              => 'number',
			'custom_attributes' => array( 'step' => '0.1' ),
			'placeholder'       => (string) ns_opt( 'default_premium', 12 ),
			'desc_tip'          => true,
			'description'       => 'Tom = standardtillægget fra indstillingerne.',
			'value'             => $p->get_meta( '_ns_premium' ),
		) );
		woocommerce_wp_checkbox( array(
			'id'          => '_ns_view_in_store',
			'label'       => 'Kan ses i butikken',
			'description' => 'Vis knappen "Book tid og se den i butikken".',
			'value'       => $p->get_meta( '_ns_view_in_store' ) ?: 'yes',
		) );
		$spot = NS_Silver_Price::per_kg();
		echo '<p class="form-field"><label>Dagspris</label><span>' . ( $spot ? esc_html( ns_dkk( $spot ) . ' pr. kg rent sølv' ) : 'Ikke sat – se WooCommerce → JustSilver' ) . '</span></p>';
		echo '</div></div>';
	}

	public static function save_admin( WC_Product $product ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce har allerede tjekket nonce.
		$product->update_meta_data( '_ns_is_silver', isset( $_POST['_ns_is_silver'] ) ? 'yes' : 'no' );
		$product->update_meta_data( '_ns_view_in_store', isset( $_POST['_ns_view_in_store'] ) ? 'yes' : 'no' );
		foreach ( array( '_ns_weight', '_ns_premium', '_ns_year' ) as $key ) {
			$val = isset( $_POST[ $key ] ) ? wc_format_decimal( wp_unslash( $_POST[ $key ] ) ) : '';
			$product->update_meta_data( $key, $val );
		}
		foreach ( array( '_ns_kind' => ns_kinds(), '_ns_purity' => ns_purities(), '_ns_condition' => ns_conditions(), '_ns_pricing' => array( 'spot' => 1, 'fast' => 1 ) ) as $key => $allowed ) {
			$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$product->update_meta_data( $key, array_key_exists( $val, $allowed ) ? $val : '' );
		}
		$product->update_meta_data( '_ns_mint', isset( $_POST['_ns_mint'] ) ? sanitize_text_field( wp_unslash( $_POST['_ns_mint'] ) ) : '' );
		// phpcs:enable
	}

	/** Kører ved ALLE gemninger: sæt pris og synk egenskaber. */
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
					continue; // egenskaben er ikke oprettet (aktivering ikke kørt)
				}
				register_taxonomy( $taxonomy, 'product', array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
			}
			if ( '' === $value ) {
				unset( $attributes[ $taxonomy ] );
				continue;
			}
			$term = term_exists( $value, $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term( $value, $taxonomy );
			}
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

	/* ------------------------------------------------------- Produktliste */

	public static function columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'price' === $key ) {
				$new['ns_silver'] = 'Sølv';
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
		$pricing = 'fast' === $p->get_meta( '_ns_pricing' ) ? 'fast pris' : 'dagspris';
		echo esc_html( ns_grams( $p->get_meta( '_ns_weight' ) ) . ' · ' . ns_purity_label( $p->get_meta( '_ns_purity' ) ?: 0.999 ) . ' · ' . $pricing );
	}

	/* --------------------------------------------------------------- CSV */

	public static function csv_columns(): array {
		return array(
			'ns_is_silver'     => 'Sølv: sølvvare (yes/no)',
			'ns_kind'          => 'Sølv: type',
			'ns_weight'        => 'Sølv: vægt (g)',
			'ns_purity'        => 'Sølv: finhed',
			'ns_mint'          => 'Sølv: præger',
			'ns_year'          => 'Sølv: årgang',
			'ns_condition'     => 'Sølv: stand',
			'ns_pricing'       => 'Sølv: prissætning (spot/fast)',
			'ns_premium'       => 'Sølv: tillæg %',
			'ns_view_in_store' => 'Sølv: kan ses i butik (yes/no)',
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

	public static function csv_import( WC_Product $product, array $data ): WC_Product {
		foreach ( array_keys( self::csv_columns() ) as $key ) {
			if ( ! isset( $data[ $key ] ) || '' === $data[ $key ] ) {
				continue;
			}
			$value = trim( (string) $data[ $key ] );
			if ( 'ns_purity' === $key ) {
				$value = ns_normalize_purity( $value ); // "925" -> "0.925"
			}
			if ( in_array( $key, array( 'ns_weight', 'ns_premium' ), true ) ) {
				$value = wc_format_decimal( str_replace( ',', '.', $value ) );
			}
			if ( 'ns_kind' === $key ) {
				$value = array_search( $value, ns_kinds(), true ) ?: $value;
			}
			$product->update_meta_data( '_' . $key, $value );
		}
		return $product;
	}
}
