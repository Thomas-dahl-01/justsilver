<?php
/**
 * Price chart. Variables from NS_Chart::shortcode(): $all, $series, $geo, $days, $first, $last, $change.
 */
defined( 'ABSPATH' ) || exit;
$up = $change >= 0;
?>
<div class="ns ns-chart" data-ns-chart="<?php echo esc_attr( wp_json_encode( $all ) ); ?>" data-ns-days="<?php echo (int) $days; ?>">
	<div class="ns-chart__head">
		<div>
			<p class="ns-eyebrow ns-eyebrow--dark"><?php esc_html_e( 'Silver price', 'justsilver' ); ?></p>
			<p class="ns-chart__now"><strong data-ns-chart-value><?php echo esc_html( number_format( $last, 2, ',', '.' ) ); ?></strong> kr/g
				<span class="ns-delta ns-delta--<?php echo $up ? 'up' : 'down'; ?>" data-ns-chart-change><?php echo $up ? '▲' : '▼'; ?> <?php echo esc_html( number_format( abs( $change ), 2, ',', '.' ) ); ?>%</span></p>
			<p class="ns-muted ns-small" data-ns-chart-label><?php /* translators: %d: number of days */ echo esc_html( sprintf( __( 'Last %d days', 'justsilver' ), $days ) ); ?></p>
		</div>
		<div class="ns-chart__ranges" role="group" aria-label="<?php esc_attr_e( 'Period', 'justsilver' ); ?>">
			<?php foreach ( array( 30, 90, 365 ) as $d ) : ?>
				<button type="button" data-ns-range="<?php echo (int) $d; ?>" class="<?php echo $d === $days ? 'is-active' : ''; ?>"><?php echo 365 === $d ? esc_html__( '1 year', 'justsilver' ) : esc_html( $d . ' ' . __( 'days', 'justsilver' ) ); ?></button>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="ns-chart__plot">
		<svg viewBox="0 0 640 220" preserveAspectRatio="none" role="img" aria-label="<?php esc_attr_e( 'Silver price chart', 'justsilver' ); ?>">
			<defs>
				<linearGradient id="nsChartFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8fb3d1" stop-opacity=".45"/><stop offset="1" stop-color="#8fb3d1" stop-opacity="0"/></linearGradient>
				<linearGradient id="nsChartLine" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#8e97a3"/><stop offset=".5" stop-color="#3d6a8f"/><stop offset="1" stop-color="#0f1b2d"/></linearGradient>
			</defs>
			<g class="ns-chart__grid"><line x1="0" y1="55" x2="640" y2="55"/><line x1="0" y1="110" x2="640" y2="110"/><line x1="0" y1="165" x2="640" y2="165"/></g>
			<path class="ns-chart__area" d="<?php echo esc_attr( $geo['area'] ); ?>" fill="url(#nsChartFill)"/>
			<path class="ns-chart__line" d="<?php echo esc_attr( $geo['line'] ); ?>" fill="none" stroke="url(#nsChartLine)" stroke-width="2.5" vector-effect="non-scaling-stroke"/>
			<line class="ns-chart__cursor" x1="0" y1="0" x2="0" y2="220"/>
		</svg>
		<span class="ns-chart__dot"></span>
		<div class="ns-chart__tip" role="status"></div>
		<span class="ns-chart__max"><?php echo esc_html( number_format( $geo['max'], 2, ',', '.' ) ); ?></span>
		<span class="ns-chart__min"><?php echo esc_html( number_format( $geo['min'], 2, ',', '.' ) ); ?></span>
	</div>
</div>
