<?php
/**
 * Silver price chart from the stored price history.
 * Shortcode / block: [justsilver_prisgraf dage="90"]
 *
 * The server renders a complete SVG (works without JavaScript); assets/js/justsilver.js adds
 * range buttons (30/90/365 days) and a hover tooltip using the same data.
 */
defined( 'ABSPATH' ) || exit;

class NS_Chart {

	public static function init(): void {
		add_shortcode( 'justsilver_prisgraf', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * One point per day (the last price of the day), oldest first, in kr/g. Pure, unit tested.
	 *
	 * @param array<int,array{t:int,p:float}> $history
	 * @return array<int,array{d:string,v:float}>
	 */
	public static function daily( array $history, int $days, int $now, DateTimeZone $tz ): array {
		$from   = $now - $days * 86400;
		$byday  = array();
		usort( $history, fn( $a, $b ) => $a['t'] <=> $b['t'] );
		foreach ( $history as $h ) {
			if ( $h['t'] < $from ) {
				continue;
			}
			$d           = ( new DateTimeImmutable( '@' . $h['t'] ) )->setTimezone( $tz )->format( 'Y-m-d' );
			$byday[ $d ] = round( (float) $h['p'] / 1000, 2 );
		}
		$out = array();
		foreach ( $byday as $d => $v ) {
			$out[] = array( 'd' => $d, 'v' => $v );
		}
		return $out;
	}

	/**
	 * SVG geometry for a series. Pure, unit tested.
	 *
	 * @param float[] $values
	 * @return array{line:string,area:string,min:float,max:float,points:array<int,array{0:float,1:float}>}
	 */
	public static function geometry( array $values, float $w, float $h, float $pad = 8 ): array {
		$n = count( $values );
		if ( $n < 2 ) {
			return array( 'line' => '', 'area' => '', 'min' => (float) ( $values[0] ?? 0 ), 'max' => (float) ( $values[0] ?? 0 ), 'points' => array() );
		}
		$min   = min( $values );
		$max   = max( $values );
		$span  = ( $max - $min ) ?: 1;
		$pts   = array();
		foreach ( array_values( $values ) as $i => $v ) {
			$x     = $pad + ( $w - 2 * $pad ) * $i / ( $n - 1 );
			$y     = $pad + ( $h - 2 * $pad ) * ( 1 - ( $v - $min ) / $span );
			$pts[] = array( round( $x, 1 ), round( $y, 1 ) );
		}
		$line = 'M' . implode( ' L', array_map( fn( $p ) => $p[0] . ',' . $p[1], $pts ) );
		$area = $line . ' L' . end( $pts )[0] . ',' . $h . ' L' . $pts[0][0] . ',' . $h . ' Z';
		return array( 'line' => $line, 'area' => $area, 'min' => $min, 'max' => $max, 'points' => $pts );
	}

	public static function shortcode( $atts = array() ): string {
		$atts   = shortcode_atts( array( 'dage' => 90 ), $atts );
		$days   = in_array( (int) $atts['dage'], array( 30, 90, 365 ), true ) ? (int) $atts['dage'] : 90;
		$all    = self::daily( NS_Silver_Price::history(), 365, time(), wp_timezone() );
		if ( count( $all ) < 2 ) {
			return '';
		}
		NS_Frontend::enqueue();
		$from   = wp_date( 'Y-m-d', time() - $days * 86400 );
		$series = array_values( array_filter( $all, fn( $p ) => $p['d'] >= $from ) );
		$geo    = self::geometry( array_column( $series, 'v' ), 640, 220 );
		$first  = $series[0]['v'];
		$last   = end( $series )['v'];
		$change = $first ? ( $last - $first ) / $first * 100 : 0;
		ob_start();
		include ns_template( 'price-chart.php' );
		return ob_get_clean();
	}
}
