/* JustSilver – frontend: scroll reveal, count-up numbers, interactive price chart, live sell calculator.
   Plain JavaScript, no dependencies. Everything degrades gracefully without JS. */
( function () {
	'use strict';
	var cfg = window.JustSilver || {};
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var animate = cfg.animate !== false && ! reduce;

	function fmt( n, dec ) {
		return Number( n ).toLocaleString( 'da-DK', { minimumFractionDigits: dec, maximumFractionDigits: dec } );
	}

	/* ---------- Scroll reveal ---------- */
	function reveal() {
		var els = document.querySelectorAll( '.ns-reveal' );
		if ( ! animate || ! ( 'IntersectionObserver' in window ) ) {
			els.forEach( function ( el ) { el.classList.add( 'is-in' ); } );
			return;
		}
		document.documentElement.classList.add( 'ns-js-reveal' );
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( e ) {
				if ( e.isIntersecting ) {
					e.target.classList.add( 'is-in' );
					io.unobserve( e.target );
				}
			} );
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 } );
		els.forEach( function ( el ) { io.observe( el ); } );
	}

	/* ---------- Count-up ---------- */
	function countUp() {
		document.querySelectorAll( '[data-ns-count]' ).forEach( function ( el ) {
			var target = parseFloat( el.getAttribute( 'data-ns-count' ) );
			var dec = parseInt( el.getAttribute( 'data-ns-decimals' ) || '0', 10 );
			if ( ! animate || isNaN( target ) ) { return; }
			var start = null, dur = 1400;
			el.textContent = fmt( 0, dec );
			function step( ts ) {
				if ( ! start ) { start = ts; }
				var p = Math.min( 1, ( ts - start ) / dur );
				var eased = 1 - Math.pow( 1 - p, 3 );
				el.textContent = fmt( target * eased, dec );
				if ( p < 1 ) { requestAnimationFrame( step ); }
			}
			requestAnimationFrame( step );
		} );
	}

	/* ---------- Price chart ---------- */
	function geometry( values, w, h, pad ) {
		var min = Math.min.apply( null, values ), max = Math.max.apply( null, values );
		var span = ( max - min ) || 1, n = values.length, pts = [];
		values.forEach( function ( v, i ) {
			pts.push( [ pad + ( w - 2 * pad ) * i / ( n - 1 ), pad + ( h - 2 * pad ) * ( 1 - ( v - min ) / span ) ] );
		} );
		var line = 'M' + pts.map( function ( p ) { return p[ 0 ].toFixed( 1 ) + ',' + p[ 1 ].toFixed( 1 ); } ).join( ' L' );
		var area = line + ' L' + pts[ n - 1 ][ 0 ].toFixed( 1 ) + ',' + h + ' L' + pts[ 0 ][ 0 ].toFixed( 1 ) + ',' + h + ' Z';
		return { line: line, area: area, min: min, max: max, pts: pts };
	}

	function chart( root ) {
		var all;
		try { all = JSON.parse( root.getAttribute( 'data-ns-chart' ) || '[]' ); } catch ( e ) { return; }
		if ( all.length < 2 ) { return; }
		var W = 640, H = 220, PAD = 8;
		var line = root.querySelector( '.ns-chart__line' ), area = root.querySelector( '.ns-chart__area' );
		var plot = root.querySelector( '.ns-chart__plot' ), tip = root.querySelector( '.ns-chart__tip' );
		var dot = root.querySelector( '.ns-chart__dot' ), cursor = root.querySelector( '.ns-chart__cursor' );
		var maxEl = root.querySelector( '.ns-chart__max' ), minEl = root.querySelector( '.ns-chart__min' );
		var valueEl = root.querySelector( '[data-ns-chart-value]' ), changeEl = root.querySelector( '[data-ns-chart-change]' );
		var labelEl = root.querySelector( '[data-ns-chart-label]' );
		var series = [], geo = null;

		function draw( days ) {
			var from = new Date( Date.now() - days * 864e5 ).toISOString().slice( 0, 10 );
			series = all.filter( function ( p ) { return p.d >= from; } );
			if ( series.length < 2 ) { series = all.slice( -2 ); }
			geo = geometry( series.map( function ( p ) { return p.v; } ), W, H, PAD );
			line.setAttribute( 'd', geo.line );
			area.setAttribute( 'd', geo.area );
			if ( animate ) {
				var len = line.getTotalLength ? line.getTotalLength() : 1000;
				line.style.transition = 'none';
				line.style.strokeDasharray = len;
				line.style.strokeDashoffset = len;
				line.getBoundingClientRect();
				line.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1)';
				line.style.strokeDashoffset = '0';
			}
			maxEl.textContent = fmt( geo.max, 2 );
			minEl.textContent = fmt( geo.min, 2 );
			var first = series[ 0 ].v, last = series[ series.length - 1 ].v, ch = ( last - first ) / first * 100;
			valueEl.textContent = fmt( last, 2 );
			changeEl.textContent = ( ch >= 0 ? '▲ ' : '▼ ' ) + fmt( Math.abs( ch ), 2 ) + '%';
			changeEl.className = 'ns-delta ns-delta--' + ( ch >= 0 ? 'up' : 'down' );
			if ( labelEl ) { labelEl.textContent = labelEl.textContent.replace( /\d+/, days ); }
			root.querySelectorAll( '[data-ns-range]' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', parseInt( b.getAttribute( 'data-ns-range' ), 10 ) === days );
			} );
		}

		function hover( ev ) {
			if ( ! geo ) { return; }
			var rect = plot.getBoundingClientRect();
			var x = ( ( ev.touches ? ev.touches[ 0 ].clientX : ev.clientX ) - rect.left ) / rect.width * W;
			var i = Math.round( ( x - PAD ) / ( W - 2 * PAD ) * ( series.length - 1 ) );
			i = Math.max( 0, Math.min( series.length - 1, i ) );
			var p = geo.pts[ i ], px = p[ 0 ] / W * rect.width, py = p[ 1 ] / H * rect.height;
			root.classList.add( 'is-hover' );
			dot.style.transform = 'translate(' + px + 'px,' + py + 'px)';
			cursor.setAttribute( 'x1', p[ 0 ] );
			cursor.setAttribute( 'x2', p[ 0 ] );
			var d = new Date( series[ i ].d + 'T12:00:00' );
			tip.innerHTML = '<strong>' + fmt( series[ i ].v, 2 ) + ' kr/g</strong><span>' + d.toLocaleDateString( 'da-DK', { day: 'numeric', month: 'short', year: 'numeric' } ) + '</span>';
			tip.style.transform = 'translate(' + Math.min( Math.max( px, 50 ), rect.width - 50 ) + 'px,0)';
		}

		plot.addEventListener( 'mousemove', hover );
		plot.addEventListener( 'touchmove', hover, { passive: true } );
		plot.addEventListener( 'mouseleave', function () { root.classList.remove( 'is-hover' ); } );
		root.querySelectorAll( '[data-ns-range]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { draw( parseInt( b.getAttribute( 'data-ns-range' ), 10 ) ); } );
		} );
		draw( parseInt( root.getAttribute( 'data-ns-days' ) || '90', 10 ) );
	}

	/* ---------- Sell calculator ---------- */
	function sell( root ) {
		var w = root.querySelector( '[name="ns_s_vaegt"]' ), p = root.querySelector( '[name="ns_s_finhed"]' );
		var out = root.querySelector( '[data-ns-sell-offer]' ), fine = root.querySelector( '[data-ns-sell-fine]' );
		var fineText = fine.textContent;
		var spot = parseFloat( cfg.spotPerKg || 0 ), margin = parseFloat( cfg.margin || 0 ), shown = 0;
		function animateTo( val ) {
			if ( ! animate ) { out.textContent = fmt( val, 0 ) + ' kr.'; shown = val; return; }
			var from = shown, start = null;
			function step( ts ) {
				if ( ! start ) { start = ts; }
				var k = Math.min( 1, ( ts - start ) / 400 );
				shown = from + ( val - from ) * ( 1 - Math.pow( 1 - k, 3 ) );
				out.textContent = fmt( shown, 0 ) + ' kr.';
				if ( k < 1 ) { requestAnimationFrame( step ); }
			}
			requestAnimationFrame( step );
		}
		function calc() {
			var g = parseFloat( String( w.value ).replace( ',', '.' ) ) || 0, pur = parseFloat( p.value ) || 0;
			if ( g <= 0 || ! spot ) { out.textContent = '– kr.'; shown = 0; fine.textContent = fineText; return; }
			var fineG = g * pur;
			animateTo( Math.round( fineG * spot / 1000 * ( 1 - margin / 100 ) ) );
			fine.textContent = fmt( fineG, 1 ) + ' g ' + ( ( cfg.i18n && cfg.i18n.fine ) || 'fine silver' );
			root.classList.add( 'has-value' );
		}
		w.addEventListener( 'input', calc );
		p.addEventListener( 'change', calc );
	}

	function init() {
		reveal();
		countUp();
		document.querySelectorAll( '[data-ns-chart]' ).forEach( chart );
		document.querySelectorAll( '[data-ns-sell]' ).forEach( sell );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
