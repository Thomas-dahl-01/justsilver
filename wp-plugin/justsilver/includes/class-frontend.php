<?php
/**
 * Visning i shoppen: sølv-design, varesidens booking-knap, tegnede sølvbilleder,
 * shortcodes [justsilver_forside] og [justsilver_pris].
 */
defined( 'ABSPATH' ) || exit;

class NS_Frontend {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_shortcode( 'justsilver_forside', array( __CLASS__, 'landing' ) );
		add_shortcode( 'justsilver_pris', array( __CLASS__, 'price_shortcode' ) );

		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'product_summary' ), 25 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'book_button' ), 35 );
		add_action( 'woocommerce_after_shop_loop_item', array( __CLASS__, 'loop_book_button' ), 15 );
		add_filter( 'woocommerce_product_get_image', array( __CLASS__, 'placeholder' ), 10, 5 );
		add_filter( 'woocommerce_single_product_image_thumbnail_html', array( __CLASS__, 'single_placeholder' ), 10, 2 );
		add_filter( 'woocommerce_display_product_attributes', array( __CLASS__, 'attributes' ), 10, 2 );
	}

	public static function enqueue(): void {
		wp_enqueue_style( 'justsilver', NS_URL . 'assets/css/justsilver.css', array(), NS_VERSION );
	}

	public static function is_silver_context(): bool {
		$cat = (int) ns_opt( 'silver_category' );
		if ( function_exists( 'is_product' ) && is_product() ) {
			return ns_is_silver( get_queried_object_id() );
		}
		if ( $cat && function_exists( 'is_product_category' ) && is_product_category() ) {
			$term = get_queried_object();
			return $term && ( (int) $term->term_id === $cat || term_is_ancestor_of( $cat, $term, 'product_cat' ) );
		}
		return false;
	}

	public static function maybe_enqueue(): void {
		global $post;
		$has_code = $post instanceof WP_Post && ( has_shortcode( $post->post_content, 'justsilver_forside' ) || has_shortcode( $post->post_content, 'justsilver_booking' ) || has_shortcode( $post->post_content, 'justsilver_pris' ) );
		if ( $has_code || self::is_silver_context() || ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) ) {
			self::enqueue();
		}
	}

	public static function body_class( array $classes ): array {
		if ( 'yes' === ns_opt( 'silver_design', 'yes' ) && self::is_silver_context() ) {
			$classes[] = 'ns-silver';
		}
		return $classes;
	}

	public static function silver_url(): string {
		$page = (int) ns_opt( 'silver_page' );
		if ( $page ) {
			return get_permalink( $page );
		}
		$cat = (int) ns_opt( 'silver_category' );
		$url = $cat ? get_term_link( $cat, 'product_cat' ) : '';
		return ( $url && ! is_wp_error( $url ) ) ? $url : wc_get_page_permalink( 'shop' );
	}

	/* -------------------------------------------------------- Vareside */

	public static function product_summary(): void {
		global $product;
		if ( ! ns_is_silver( $product ) ) {
			return;
		}
		$w = (float) $product->get_meta( '_ns_weight' );
		$p = (float) ( $product->get_meta( '_ns_purity' ) ?: 0.999 );
		echo '<div class="ns ns-facts">';
		echo '<span>' . esc_html( ns_grams( $w ) ) . '</span><span>Finhed ' . esc_html( ns_purity_label( $p ) ) . '</span><span>' . esc_html( ns_grams( round( $w * $p, 2 ) ) ) . ' rent sølv</span>';
		echo '</div>';
		if ( 'fast' !== $product->get_meta( '_ns_pricing' ) ) {
			echo '<p class="ns-muted ns-small">Prisen følger sølvets dagspris' . ( NS_Silver_Price::updated_at() ? ' (opdateret ' . esc_html( wp_date( 'j. M H:i', NS_Silver_Price::updated_at() ) ) . ')' : '' ) . '.</p>';
		}
	}

	public static function book_button(): void {
		global $product;
		if ( ! ns_is_silver( $product ) || 'no' === $product->get_meta( '_ns_view_in_store' ) || ! ns_opt( 'booking_page' ) ) {
			return;
		}
		$url = NS_Bookings::url( array( 'ns_vare' => $product->get_id() ) );
		echo '<p class="ns"><a class="ns-btn ns-btn--ghost" href="' . esc_url( $url ) . '">Book tid og se den i butikken</a></p>';
	}

	public static function loop_book_button(): void {
		global $product;
		if ( ! ns_is_silver( $product ) || $product->is_in_stock() || ! ns_opt( 'booking_page' ) ) {
			return;
		}
		echo '<a class="button ns-loop-book" href="' . esc_url( NS_Bookings::url( array( 'ns_vare' => $product->get_id() ) ) ) . '">Book tid</a>';
	}

	public static function attributes( array $attrs, WC_Product $product ): array {
		if ( ns_is_silver( $product ) ) {
			if ( $c = $product->get_meta( '_ns_condition' ) ) {
				$attrs['ns_condition'] = array( 'label' => 'Stand', 'value' => esc_html( ns_conditions()[ $c ] ?? $c ) );
			}
			if ( $y = $product->get_meta( '_ns_year' ) ) {
				$attrs['ns_year'] = array( 'label' => 'Årgang', 'value' => esc_html( $y ) );
			}
		}
		return $attrs;
	}

	/** Tegnet sølvbillede når varen ikke har et foto. */
	public static function placeholder( $html, $product, $size = 'woocommerce_thumbnail', $attr = array(), $placeholder = true ) {
		if ( ! $product instanceof WC_Product || $product->get_image_id() || ! ns_is_silver( $product ) ) {
			return $html;
		}
		return '<span class="ns-art">' . self::svg( $product ) . '</span>';
	}

	/** Samme tegning i varesidens billedgalleri. */
	public static function single_placeholder( $html, $attachment_id ) {
		global $product;
		if ( $attachment_id || ! $product instanceof WC_Product || $product->get_image_id() || ! ns_is_silver( $product ) ) {
			return $html;
		}
		return '<div class="woocommerce-product-gallery__image--placeholder ns-art ns-art--large">' . self::svg( $product ) . '</div>';
	}

	public static function svg( WC_Product $p ): string {
		static $n = 0;
		$id     = 'nsg' . $p->get_id() . '_' . ( ++$n );
		$kind   = $p->get_meta( '_ns_kind' );
		$w      = esc_html( ns_grams( (float) $p->get_meta( '_ns_weight' ) ) );
		$purity = esc_html( ns_purity_label( $p->get_meta( '_ns_purity' ) ?: 0.999 ) );
		$year   = esc_html( (string) $p->get_meta( '_ns_year' ) );
		$g      = "url(#{$id})";
		$stroke = 'stroke="#7d8692"';
		switch ( $kind ) {
			case 'barre':
				$shape = "<path d='M40 112 58 44h84l18 68z' fill='{$g}' {$stroke}/><path d='M58 44h84l-8 12H66z' fill='#fff' opacity='.55'/>"
					. "<text x='100' y='90' text-anchor='middle' font-family='Georgia,serif' font-size='15' fill='#3b4452'>{$w}</text>"
					. "<text x='100' y='104' text-anchor='middle' font-family='Georgia,serif' font-size='8' letter-spacing='2' fill='#5a6370'>FINE SILVER {$purity}</text>";
				break;
			case 'moent':
				$sub   = $year ?: 'AG ' . $purity;
				$shape = "<circle cx='100' cy='75' r='52' fill='{$g}' {$stroke}/><circle cx='100' cy='75' r='43' fill='none' stroke='#8a93a0' stroke-dasharray='2 3'/>"
					. "<text x='100' y='72' text-anchor='middle' font-family='Georgia,serif' font-size='14' fill='#3b4452'>{$w}</text>"
					. "<text x='100' y='88' text-anchor='middle' font-family='Georgia,serif' font-size='8' letter-spacing='1.5' fill='#5a6370'>{$sub}</text>";
				break;
			case 'granulat':
				$shape = '';
				foreach ( array( array( 62, 98, 9 ), array( 80, 102, 8 ), array( 98, 96, 10 ), array( 116, 101, 8 ), array( 134, 97, 9 ), array( 70, 84, 9 ), array( 88, 86, 10 ), array( 106, 83, 8 ), array( 124, 85, 9 ), array( 79, 70, 8 ), array( 97, 72, 9 ), array( 115, 69, 8 ) ) as $c ) {
					$shape .= "<circle cx='{$c[0]}' cy='{$c[1]}' r='{$c[2]}' fill='{$g}' {$stroke}/>";
				}
				break;
			case 'smykke':
				$shape = "<ellipse cx='100' cy='80' rx='44' ry='36' fill='none' stroke='{$g}' stroke-width='12'/><ellipse cx='100' cy='80' rx='44' ry='36' fill='none' stroke='#7d8692' stroke-width='.8'/><circle cx='100' cy='44' r='9' fill='#eaf1f8' {$stroke}/>";
				break;
			case 'bestik':
				$shape = "<ellipse cx='78' cy='48' rx='14' ry='20' fill='{$g}' {$stroke}/><rect x='75' y='66' width='6' height='64' rx='3' fill='{$g}' {$stroke}/>"
					. "<path d='M112 28v28a8 8 0 0 0 16 0V28M120 28v30' fill='none' stroke='#8f98a5' stroke-width='3'/><rect x='117' y='62' width='6' height='68' rx='3' fill='{$g}' {$stroke}/>";
				break;
			default:
				$shape = "<path d='M70 30h60c0 34-14 50-30 54-16-4-30-20-30-54z' fill='{$g}' {$stroke}/><rect x='96' y='84' width='8' height='26' fill='{$g}' {$stroke}/><ellipse cx='100' cy='118' rx='28' ry='8' fill='{$g}' {$stroke}/>";
		}
		return "<svg viewBox='0 0 200 150' role='img' aria-label='" . esc_attr( $p->get_name() ) . "'><defs><linearGradient id='{$id}' x1='0' y1='0' x2='1' y2='1'>"
			. "<stop offset='0' stop-color='#fbfcfd'/><stop offset='.35' stop-color='#c4cad3'/><stop offset='.6' stop-color='#eef1f4'/><stop offset='1' stop-color='#8f98a5'/></linearGradient></defs>{$shape}</svg>";
	}

	/* ------------------------------------------------------- Shortcodes */

	public static function price_shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'enhed' => 'g' ), $atts );
		$kg   = NS_Silver_Price::per_kg();
		if ( ! $kg ) {
			return '';
		}
		$text = 'kg' === $atts['enhed'] ? ns_dkk( $kg ) . ' pr. kg' : number_format( $kg / 1000, 2, ',', '.' ) . ' kr/g';
		return '<span class="ns-ticker"><span class="ns-ticker__dot"></span>Sølv ' . esc_html( $text ) . '</span>';
	}

	public static function landing(): string {
		self::enqueue();
		ob_start();
		include ns_template( 'landing.php' );
		return ob_get_clean();
	}
}
