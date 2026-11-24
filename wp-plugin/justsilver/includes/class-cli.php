<?php
/**
 * WP-CLI-kommandoer:
 *   wp justsilver price 10500        Sæt dagspris (kr/kg) og genberegn
 *   wp justsilver fetch-price        Hent kurs fra API nu
 *   wp justsilver reprice            Genberegn alle spot-varer med det samme
 *   wp justsilver seed [--stores]    Opret demosortiment (~320 sølvvarer) og evt. demo-butikker
 */
defined( 'ABSPATH' ) || exit;

class NS_CLI {

	/**
	 * Sæt dagens sølvpris.
	 *
	 * <kr_per_kg>
	 * : Pris i DKK pr. kg rent sølv.
	 */
	public function price( $args ) {
		$price = (float) str_replace( ',', '.', $args[0] );
		if ( ! NS_Silver_Price::set( $price, 'cli' ) ) {
			WP_CLI::error( 'Ugyldig pris.' );
		}
		$this->reprice();
	}

	/**
	 * Hent sølvkurs fra API.
	 *
	 * [--force]
	 * : Brug kursen selv om den afviger meget fra den nuværende.
	 *
	 * @subcommand fetch-price
	 */
	public function fetch_price( $args, $assoc ) {
		$r = NS_Price_API::run( ! empty( $assoc['force'] ) );
		$r['ok'] ? WP_CLI::success( $r['message'] ) : WP_CLI::error( $r['message'] );
	}

	/** Genberegn alle spot-baserede sølvvarer nu. */
	public function reprice() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'ns_reprice_batch' );
		}
		$page  = 1;
		$count = 0;
		do {
			$ids   = NS_Silver_Price::spot_product_ids( $page );
			$more  = NS_Silver_Price::reprice_batch( $page );
			$count += count( $ids );
			$page++;
		} while ( $more );
		WP_CLI::success( "Genberegnet {$count} varer ud fra " . ns_dkk( NS_Silver_Price::per_kg() ) . '/kg.' );
	}

	/**
	 * Opret demosortiment.
	 *
	 * [--stores]
	 * : Opret også tre opdigtede demo-butikker.
	 */
	public function seed( $args, $assoc ) {
		mt_srand( 42 );
		if ( count( NS_Silver_Price::history() ) < 30 ) {
			$this->seed_history();
		}
		$root = (int) ns_opt( 'silver_category' );
		if ( ! $root ) {
			WP_CLI::error( 'Aktivér pluginet først (sølvkategorien mangler).' );
		}
		// Child categories are found by their position (order meta), so this works in any language.
		$children = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $root, 'hide_empty' => false, 'orderby' => 'meta_value_num', 'meta_key' => 'order' ) );
		$index    = array( 'Sølvbarrer' => 0, 'Sølvmønter' => 1, 'Granulat & bulk' => 2, 'Smykker' => 3, 'Sølvtøj & bestik' => 4, 'Antik & samlerobjekter' => 5 );
		$cat      = function ( $name ) use ( $root, $children, $index ) {
			$i = $index[ $name ] ?? -1;
			return ( ! is_wp_error( $children ) && isset( $children[ $i ] ) ) ? (int) $children[ $i ]->term_id : $root;
		};
		$items = array();
		$add   = function ( $name, $category, $kind, $weight, $purity = '0.999', $mint = '', $premium = 12, $extra = array() ) use ( &$items, $cat ) {
			$items[] = array_merge( array( 'name' => $name, 'cat' => $cat( $category ), 'kind' => $kind, 'weight' => $weight, 'purity' => $purity, 'mint' => $mint, 'premium' => $premium ), $extra );
		};
		$oz = 31.10;

		foreach ( array( 'Valcambi', 'Heraeus', 'Umicore', 'PAMP', 'Argor-Heraeus' ) as $mint ) {
			foreach ( array( array( 1, 90 ), array( 5, 55 ), array( 10, 40 ), array( 20, 32 ), array( 31.10, 28 ), array( 50, 24 ), array( 100, 18 ), array( 250, 15 ), array( 500, 13 ), array( 1000, 10 ), array( 5000, 8 ) ) as [ $w, $prem ] ) {
				$label = 31.10 === $w ? '1 oz' : ns_grams( $w );
				$add( "{$mint} sølvbarre {$label}", 'Sølvbarrer', 'barre', $w, '0.999', $mint, $prem + mt_rand( -2, 3 ), array(
					'desc'     => "Investeringsbarre i 999 fint sølv fra {$mint}. Leveres i original emballage med serienummer.",
					'featured' => 'Valcambi' === $mint && in_array( $w, array( 100, 1000 ), true ),
				) );
			}
		}
		$coins = array( array( 'Maple Leaf', 'Royal Canadian Mint', '0.9999' ), array( 'Britannia', 'The Royal Mint', '0.999' ), array( 'Philharmoniker', 'Münze Österreich', '0.999' ), array( 'Kookaburra', 'Perth Mint', '0.999' ), array( 'Kangaroo', 'Perth Mint', '0.9999' ), array( 'American Eagle', 'U.S. Mint', '0.999' ), array( 'Krugerrand', 'South African Mint', '0.999' ) );
		foreach ( $coins as [ $coin, $mint, $purity ] ) {
			for ( $year = 2014; $year <= 2026; $year++ ) {
				$add( "{$coin} 1 oz sølvmønt {$year}", 'Sølvmønter', 'moent', $oz, $purity, $mint, 22 + mt_rand( 0, 8 ), array(
					'year'      => $year,
					'condition' => $year >= 2022 ? 'ny' : ( mt_rand( 0, 1 ) ? 'ny' : 'som_ny' ),
					'desc'      => "{$coin} i fint sølv, årgang {$year}. Præget af {$mint}.",
					'featured'  => 2026 === $year && in_array( $coin, array( 'Maple Leaf', 'Britannia', 'Philharmoniker' ), true ),
				) );
			}
			$add( "{$coin} tube – 25 × 1 oz", 'Sølvmønter', 'moent', $oz * 25, $purity, $mint, 18, array( 'desc' => "Original tube med 25 stk. {$coin} 1 oz." ) );
			$add( "{$coin} monsterbox – 500 × 1 oz", 'Granulat & bulk', 'moent', $oz * 500, $purity, $mint, 14, array( 'desc' => "Forseglet monsterbox fra {$mint} med 500 stk. {$coin}." ) );
		}
		foreach ( array( 100, 250, 500, 1000, 5000 ) as $w ) {
			foreach ( array( array( '0.999', 'Heraeus' ), array( '0.9999', 'Umicore' ) ) as [ $purity, $maker ] ) {
				$add( 'Sølvgranulat ' . ns_grams( $w ) . ' (' . ns_purity_label( $purity ) . ')', 'Granulat & bulk', 'granulat', $w, $purity, $maker, 9, array( 'desc' => 'Granulat i forseglet pose – populært blandt sølvsmede og til investering.' ) );
			}
		}
		$styles = array( 'klassisk', 'nordisk', 'hamret', 'blank', 'oxideret', 'flettet', 'minimalistisk' );
		foreach ( array( array( 'Ring', 3, 12 ), array( 'Panserkæde', 8, 45 ), array( 'Ankerkæde', 5, 30 ), array( 'Armbånd', 10, 40 ), array( 'Vedhæng', 2, 10 ), array( 'Øreringe', 2, 8 ), array( 'Brosje', 4, 15 ), array( 'Manchetknapper', 8, 18 ) ) as [ $kind, $lo, $hi ] ) {
			foreach ( array_slice( $styles, mt_rand( 0, 2 ), 5 ) as $style ) {
				$w = round( $lo + mt_rand( 0, 100 ) / 100 * ( $hi - $lo ), 1 );
				$add( "{$kind} i sterlingsølv – {$style}", 'Smykker', 'smykke', $w, '0.925', 'Dansk sølvsmed', mt_rand( 150, 400 ), array( 'condition' => array( 'ny', 'ny', 'som_ny', 'brugt' )[ mt_rand( 0, 3 ) ], 'desc' => "{$kind} i 925 sterlingsølv, {$style} design." ) );
			}
		}
		foreach ( array( 'Dobbeltriflet', 'Musling', 'Ambassade', 'Rita', 'Saksisk', 'Karina', 'Elite', 'Hans Christian' ) as $pattern ) {
			foreach ( array( array( 'Middagsske', 70 ), array( 'Middagsgaffel', 68 ), array( 'Middagskniv', 85 ), array( 'Frokostgaffel', 50 ), array( 'Dessertske', 40 ), array( 'Teske', 22 ), array( 'Kagegaffel', 20 ), array( 'Sauceske', 60 ), array( 'Kompotske', 75 ), array( 'Serveringsske', 95 ) ) as [ $piece, $w ] ) {
				$purity = mt_rand( 0, 2 ) ? '0.830' : '0.925';
				$add( "{$pattern} " . mb_strtolower( $piece ), 'Sølvtøj & bestik', 'bestik', $w + mt_rand( -5, 5 ), $purity, 'Dansk sølvsmed', mt_rand( 60, 140 ), array( 'condition' => array( 'brugt', 'som_ny', 'vintage' )[ mt_rand( 0, 2 ) ], 'desc' => "{$piece} i mønstret {$pattern}. Stemplet og kontrolleret." ) );
			}
		}
		foreach ( array( array( 'Lysestage', 180, 600 ), array( 'Skål', 150, 700 ), array( 'Kaffekande', 400, 900 ), array( 'Flødekande', 120, 300 ), array( 'Sukkerskål', 140, 350 ), array( 'Bakke', 450, 1500 ), array( 'Pokal', 200, 800 ), array( 'Vase', 180, 500 ), array( 'Æggebæger', 30, 60 ) ) as [ $kind, $lo, $hi ] ) {
			for ( $i = 0; $i < 3; $i++ ) {
				$year = mt_rand( 1890, 1965 );
				$add( "{$kind} i sølv, ca. {$year}", 'Antik & samlerobjekter', 'antik', mt_rand( $lo, $hi ), array( '0.830', '0.925', '0.800' )[ mt_rand( 0, 2 ) ], 'Dansk sølvsmed', mt_rand( 80, 250 ), array( 'condition' => 'vintage', 'year' => $year, 'desc' => "{$kind} fra omkring {$year}. Unikt stykke – kan ses i butikken efter aftale." ) );
			}
		}

		wp_defer_term_counting( true );
		$progress = WP_CLI\Utils\make_progress_bar( 'Opretter sølvvarer', count( $items ) );
		$created  = 0;
		foreach ( $items as $n => $it ) {
			$sku = sprintf( 'JS-%05d', $n + 1 );
			if ( wc_get_product_id_by_sku( $sku ) ) {
				$progress->tick();
				continue;
			}
			$p = new WC_Product_Simple();
			$p->set_name( $it['name'] );
			$p->set_sku( $sku );
			$p->set_status( 'publish' );
			$p->set_category_ids( array( $it['cat'] ) );
			$p->set_description( $it['desc'] ?? '' );
			$p->set_short_description( $it['desc'] ?? '' );
			$p->set_manage_stock( true );
			$p->set_stock_quantity( array( 0, 1, 2, 3, 5, 8, 12, 25, 40, 100 )[ mt_rand( 0, 9 ) ] );
			$p->set_featured( ! empty( $it['featured'] ) );
			$p->set_weight( (string) round( $it['weight'] / 1000, 3 ) ); // kg til fragtberegning
			$p->update_meta_data( '_ns_is_silver', 'yes' );
			$p->update_meta_data( '_ns_kind', $it['kind'] );
			$p->update_meta_data( '_ns_weight', (string) $it['weight'] );
			$p->update_meta_data( '_ns_purity', $it['purity'] );
			$p->update_meta_data( '_ns_mint', $it['mint'] );
			$p->update_meta_data( '_ns_premium', (string) $it['premium'] );
			$p->update_meta_data( '_ns_pricing', 'spot' );
			$p->update_meta_data( '_ns_condition', $it['condition'] ?? 'ny' );
			$p->update_meta_data( '_ns_view_in_store', 'yes' );
			if ( ! empty( $it['year'] ) ) {
				$p->update_meta_data( '_ns_year', (string) $it['year'] );
			}
			$p->save();
			$created++;
			$progress->tick();
		}
		$progress->finish();
		wp_defer_term_counting( false );

		if ( ! empty( $assoc['stores'] ) ) {
			$this->demo_stores();
		}
		WP_CLI::success( "Oprettet {$created} sølvvarer." );
	}

	/**
	 * Generate a year of realistic-looking demo price history (random walk with drift), ending at today's price.
	 *
	 * [--end=<kr_per_kg>]
	 * : Price to end at. Default 10500.
	 */
	public function history( $args = array(), $assoc = array() ) {
		$this->seed_history( (float) ( $assoc['end'] ?? 10500 ) );
		WP_CLI::success( 'Oprettet 365 dages demo-prishistorik.' );
	}

	private function seed_history( float $end = 10500 ): void {
		mt_srand( 7 );
		$points = array();
		$price  = $end;
		for ( $d = 0; $d <= 365; $d++ ) {
			$points[] = round( $price, 2 );
			$shock    = ( mt_rand( -1000, 1000 ) / 1000 ) * 0.012;     // daily move up to ±1.2 %
			$wave     = sin( $d / 38 ) * 0.0012;                         // slow swings
			$price    = $price / ( 1 + 0.0005 + $shock + $wave );        // walk backwards with a slight upward drift
		}
		$points  = array_reverse( $points );
		$history = array();
		$start   = strtotime( 'today 17:00' ) - 365 * DAY_IN_SECONDS;
		foreach ( $points as $i => $p ) {
			$history[] = array( 't' => $start + $i * DAY_IN_SECONDS, 'p' => $p, 's' => 'demo' );
		}
		update_option( 'ns_price_history', $history, false );
		update_option( 'ns_silver_price', $end );
		update_option( 'ns_silver_price_updated', time() );
	}

	/**
	 * Create fictional demo bookings for the next days (for the calendar and dashboard).
	 *
	 * [--days=<n>]
	 * : Number of days ahead. Default 7.
	 *
	 * @subcommand demo-bookings
	 */
	public function demo_bookings( $args = array(), $assoc = array() ) {
		mt_srand( 11 );
		$names    = array( 'Anna Holm', 'Mads Krogh', 'Sofie Lund', 'Peter Vind', 'Ida Mørk', 'Jonas Brix', 'Freja Dal', 'Emil Strand', 'Clara Bøg', 'Oskar Fjord', 'Maja Lys', 'Viktor Eng' );
		$purposes = array_keys( NS_Bookings::purposes() );
		$products = NS_Silver_Price::spot_product_ids( 1 );
		$created  = 0;
		foreach ( NS_Stores::all() as $post ) {
			$store = NS_Stores::get( $post->ID );
			for ( $i = 0; $i < (int) ( $assoc['days'] ?? 7 ); $i++ ) {
				$day   = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( "+{$i} day" );
				$slots = array_keys( NS_Stores::available_slots( $store, $day ) );
				shuffle( $slots );
				foreach ( array_slice( $slots, 0, mt_rand( 1, 4 ) ) as $time ) {
					$name = $names[ mt_rand( 0, count( $names ) - 1 ) ];
					$id   = wp_insert_post( array(
						'post_type'   => NS_Bookings::CPT,
						'post_status' => 'publish',
						'post_title'  => sprintf( '%s – %s %s', $name, $day->format( 'd.m.Y' ), $time ),
						'meta_input'  => array(
							'_ns_store'    => $store['id'],
							'_ns_date'     => $day->format( 'Y-m-d' ),
							'_ns_time'     => $time,
							'_ns_purpose'  => $purposes[ mt_rand( 0, count( $purposes ) - 1 ) ],
							'_ns_name'     => $name,
							'_ns_email'    => strtolower( strtok( $name, ' ' ) ) . '@example.test',
							'_ns_phone'    => '12 34 ' . mt_rand( 10, 99 ) . ' ' . mt_rand( 10, 99 ),
							'_ns_products' => mt_rand( 0, 1 ) ? array( $products[ mt_rand( 0, count( $products ) - 1 ) ] ) : array(),
							'_ns_status'   => mt_rand( 0, 3 ) ? 'booket' : 'bekraeftet',
						),
					) );
					if ( $id ) {
						$created++;
					}
				}
			}
		}
		WP_CLI::success( "Oprettet {$created} opdigtede demo-bookinger." );
	}

	private function demo_stores(): void {
		$demo = array(
			array( 'JustSilver København', 'Sølvgade 12', '1620', 'København V', '12 34 56 71', 'kbh@justsilver.example' ),
			array( 'JustSilver Aarhus', 'Barrevej 3', '8000', 'Aarhus C', '12 34 56 72', 'aarhus@justsilver.example' ),
			array( 'JustSilver Odense', 'Møntstræde 7', '5000', 'Odense C', '12 34 56 73', 'odense@justsilver.example' ),
		);
		foreach ( $demo as [ $name, $addr, $zip, $city, $phone, $email ] ) {
			if ( get_posts( array( 'post_type' => NS_Stores::CPT, 'title' => $name, 'fields' => 'ids', 'post_status' => 'any' ) ) ) {
				continue;
			}
			$hours = array();
			for ( $d = 1; $d <= 7; $d++ ) {
				$hours[ $d ] = $d <= 5 ? array( 'open' => '10:00', 'close' => '17:30' ) : ( 6 === $d ? array( 'open' => '10:00', 'close' => '14:00' ) : array( 'open' => '', 'close' => '' ) );
			}
			wp_insert_post( array(
				'post_type'   => NS_Stores::CPT,
				'post_status' => 'publish',
				'post_title'  => $name,
				'meta_input'  => array(
					'_ns_address' => $addr, '_ns_postcode' => $zip, '_ns_city' => $city, '_ns_phone' => $phone, '_ns_email' => $email,
					'_ns_slot_minutes' => 30, '_ns_capacity' => 2, '_ns_notice_hours' => 2, '_ns_days_ahead' => 30,
					'_ns_hours' => $hours, '_ns_active' => 'yes',
				),
			) );
		}
		NS_Stores::sync_pickup_locations();
		WP_CLI::log( 'Oprettet tre opdigtede JustSilver-butikker (Sølv-bookinger → Butikker).' );
	}
}
