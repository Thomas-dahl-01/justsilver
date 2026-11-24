<?php
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase {

	public function test_money_format_is_danish(): void {
		$this->assertSame( '1.235 kr.', ns_dkk( 1234.5 ) );
		$this->assertSame( '12.345,60 kr.', ns_dkk( 12345.6, 2 ) );
	}

	public function test_weights(): void {
		$this->assertSame( '1 kg', ns_grams( 1000 ) );
		$this->assertSame( '5 kg', ns_grams( 5000 ) );
		$this->assertSame( '31,1 g', ns_grams( 31.10 ) );
		$this->assertSame( '1.250 g', ns_grams( 1250 ) );
	}

	public function test_purity_labels(): void {
		$this->assertSame( '999', ns_purity_label( 0.999 ) );
		$this->assertSame( '999,9', ns_purity_label( 0.9999 ) );
		$this->assertSame( '830', ns_purity_label( 0.83 ) );
	}

	/** @dataProvider purities */
	public function test_purity_is_normalised( string $in, string $out ): void {
		$this->assertSame( $out, ns_normalize_purity( $in ) );
	}

	public static function purities(): array {
		return array(
			array( '830', '0.830' ),
			array( '0.83', '0.830' ),
			array( '999.9', '0.9999' ),
			array( '999,9', '0.9999' ),
			array( '925', '0.925' ),
			array( '0.5', '0.5' ),
		);
	}

	public function test_csv_row_maps_to_meta(): void {
		$meta = NS_Product_Fields::csv_to_meta( array(
			'ns_is_silver' => 'yes',
			'ns_kind'      => 'Mønt',
			'ns_weight'    => '31,10',
			'ns_purity'    => '999.9',
			'ns_premium'   => '',
			'ns_mint'      => ' Perth Mint ',
		) );
		$this->assertSame( array(
			'_ns_is_silver' => 'yes',
			'_ns_kind'      => 'moent',
			'_ns_weight'    => '31.1',
			'_ns_purity'    => '0.9999',
			'_ns_mint'      => 'Perth Mint',
		), $meta );
	}

	public function test_csv_kind_accepts_english(): void {
		$this->assertSame( 'barre', NS_Product_Fields::csv_to_meta( array( 'ns_kind' => 'Bar' ) )['_ns_kind'] );
		$this->assertSame( 'smykke', NS_Product_Fields::csv_to_meta( array( 'ns_kind' => 'jewelry' ) )['_ns_kind'] );
	}

	public function test_price_alerts_fire_at_or_below_target(): void {
		$alerts = array( array( 'id' => 1, 'target' => 10.0 ), array( 'id' => 2, 'target' => 9.5 ), array( 'id' => 3, 'target' => 11 ) );
		$this->assertSame( array( 1, 3 ), array_column( NS_Alerts::due( $alerts, 10.0 ), 'id' ) );
		$this->assertSame( array(), NS_Alerts::due( $alerts, 0 ) );
	}
}
