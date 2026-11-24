<?php
use PHPUnit\Framework\TestCase;

/**
 * WordPress 6.7+ warns ("_load_textdomain_just_in_time was called incorrectly") when a plugin
 * translates text before the init action. Our init() methods run on plugins_loaded, so they
 * must only register hooks – never call __() and friends.
 */
final class BootTest extends TestCase {

	public function test_product_fields_init_does_not_translate(): void {
		$GLOBALS['ns_test_translations'] = 0;
		NS_Product_Fields::init();
		$this->assertSame( 0, $GLOBALS['ns_test_translations'], 'NS_Product_Fields::init() translated text before init' );
	}

	public function test_simple_init_methods_do_not_translate(): void {
		foreach ( array( 'NS_Silver_Price', 'NS_Chart', 'NS_Admin' ) as $class ) {
			$GLOBALS['ns_test_translations'] = 0;
			$class::init();
			$this->assertSame( 0, $GLOBALS['ns_test_translations'], "$class::init() translated text before init" );
		}
	}

	public function test_csv_keys_match_translated_columns(): void {
		$this->assertSame( NS_Product_Fields::CSV_KEYS, array_keys( NS_Product_Fields::csv_columns() ) );
	}
}
