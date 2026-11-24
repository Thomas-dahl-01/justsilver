<?php
use PHPUnit\Framework\TestCase;

final class PricingTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['ns_test_options'] = array( 'ns_silver_price' => 10500, 'ns_settings' => array( 'default_premium' => '12' ) );
		$GLOBALS['ns_test_filters'] = array();
	}

	public function test_price_formula(): void {
		// 100 g × 0.999 × 10.5 kr/g × 1.18 = 1237.7 → 1238
		$this->assertSame( 1238.0, ns_calc_price( 100, 0.999, 10500, 18 ) );
	}

	public function test_price_never_below_one_krone(): void {
		$this->assertSame( 1.0, ns_calc_price( 0.01, 0.8, 100, 0 ) );
	}

	public function test_price_needs_valid_input(): void {
		$this->assertNull( ns_calc_price( 0, 0.999, 10500, 10 ) );
		$this->assertNull( ns_calc_price( 100, 0.999, 0, 10 ) );
	}

	public function test_buyback_subtracts_margin(): void {
		// 250 g sterling: 250 × 0.925 × 10.5 × 0.85 = 2063.9 → 2064
		$this->assertSame( 2064.0, ns_calc_buyback( 250, 0.925, 10500, 15 ) );
		$this->assertSame( 0.0, ns_calc_buyback( 0, 0.925, 10500, 15 ) );
	}

	public function test_product_uses_own_premium(): void {
		$p = new WC_Product( array( '_ns_weight' => '31.10', '_ns_purity' => '0.9999', '_ns_premium' => '24' ) );
		// 31.10 × 0.9999 × 10.5 × 1.24 = 404.9 → 405
		$this->assertSame( 405.0, NS_Silver_Price::compute( $p ) );
	}

	public function test_product_falls_back_to_default_premium(): void {
		$p = new WC_Product( array( '_ns_weight' => '1000', '_ns_purity' => '0.999' ) );
		// 1000 × 0.999 × 10.5 × 1.12 = 11748.2 → 11748
		$this->assertSame( 11748.0, NS_Silver_Price::compute( $p ) );
	}

	public function test_price_follows_a_new_spot_price(): void {
		$p = new WC_Product( array( '_ns_weight' => '100', '_ns_purity' => '0.999', '_ns_premium' => '20' ) );
		$this->assertSame( 1259.0, NS_Silver_Price::compute( $p ) );
		$this->assertSame( 1439.0, NS_Silver_Price::compute( $p, 12000 ) );
	}

	public function test_price_filter_can_round_to_five(): void {
		add_filter( 'justsilver_computed_price', fn( $price ) => ceil( $price / 5 ) * 5 );
		$p = new WC_Product( array( '_ns_weight' => '100', '_ns_purity' => '0.999', '_ns_premium' => '20' ) );
		$this->assertSame( 1260.0, NS_Silver_Price::compute( $p ) );
	}

	public function test_api_rates_are_parsed_both_ways(): void {
		$this->assertSame( 330.0, NS_Price_API::parse_rates( array( 'DKKXAG' => 330 ) ) );
		$this->assertEqualsWithDelta( 330.0, NS_Price_API::parse_rates( array( 'XAG' => 1 / 330 ) ), 0.0001 );
		$this->assertInstanceOf( WP_Error::class, NS_Price_API::parse_rates( array() ) );
	}
}
