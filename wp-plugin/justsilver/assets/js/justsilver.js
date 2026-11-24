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

	/* =====================================================================
	   Interactive effects. All of them are skipped when motion is reduced,
	   and the pointer effects only run on devices with a real mouse.
	   ===================================================================== */
	var finePointer = window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;

	/* Run fn at most once per animation frame. */
	function rafThrottle( fn ) {
		var queued = false, lastArgs;
		return function () {
			lastArgs = arguments;
			if ( queued ) { return; }
			queued = true;
			requestAnimationFrame( function () { queued = false; fn.apply( null, lastArgs ); } );
		};
	}

	/* ---------- Hero: mouse parallax, spotlight and scroll depth ---------- */
	function heroParallax( hero ) {
		var layers = hero.querySelectorAll( '[data-ns-depth]' );
		var move = rafThrottle( function ( e ) {
			var r = hero.getBoundingClientRect();
			var x = ( e.clientX - r.left ) / r.width, y = ( e.clientY - r.top ) / r.height;
			hero.style.setProperty( '--mx', ( x * 100 ).toFixed( 1 ) + '%' );
			hero.style.setProperty( '--my', ( y * 100 ).toFixed( 1 ) + '%' );
			layers.forEach( function ( l ) {
				var d = parseFloat( l.getAttribute( 'data-ns-depth' ) );
				l.style.translate = ( ( x - 0.5 ) * d * 40 ).toFixed( 1 ) + 'px ' + ( ( y - 0.5 ) * d * 30 ).toFixed( 1 ) + 'px';
			} );
		} );
		if ( finePointer ) {
			hero.addEventListener( 'pointermove', move );
			hero.addEventListener( 'pointerleave', function () {
				layers.forEach( function ( l ) { l.style.translate = '0 0'; } );
			} );
		}
		var art = hero.querySelector( '.ns-hero__art' );
		window.addEventListener( 'scroll', rafThrottle( function () {
			var r = hero.getBoundingClientRect();
			if ( r.bottom < 0 || r.top > innerHeight ) { return; }
			if ( art ) { art.style.transform = 'translateY(' + ( -r.top * 0.12 ).toFixed( 1 ) + 'px)'; }
		} ), { passive: true } );
	}

	/* ---------- Hero: floating silver dust (canvas) ---------- */
	function silverDust( hero ) {
		var canvas = document.createElement( 'canvas' );
		canvas.className = 'ns-dust';
		canvas.setAttribute( 'aria-hidden', 'true' );
		hero.insertBefore( canvas, hero.firstChild );
		var ctx = canvas.getContext( '2d' ), dpr = Math.min( 2, window.devicePixelRatio || 1 );
		var W = 0, H = 0, parts = [], mouse = { x: -999, y: -999 }, running = false, visible = true;
		function resize() {
			W = hero.clientWidth; H = hero.clientHeight;
			canvas.width = W * dpr; canvas.height = H * dpr;
			ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
			var n = Math.round( Math.min( 90, W * H / 9000 ) );
			parts = [];
			for ( var i = 0; i < n; i++ ) {
				parts.push( { x: Math.random() * W, y: Math.random() * H, r: Math.random() * 1.6 + 0.4, vy: -( Math.random() * 0.25 + 0.05 ), vx: ( Math.random() - 0.5 ) * 0.15, a: Math.random() * 0.6 + 0.2, t: Math.random() * 6.28 } );
			}
		}
		function frame() {
			if ( ! running ) { return; }
			ctx.clearRect( 0, 0, W, H );
			for ( var i = 0; i < parts.length; i++ ) {
				var p = parts[ i ], dx = p.x - mouse.x, dy = p.y - mouse.y, d2 = dx * dx + dy * dy;
				if ( d2 < 12000 ) { var f = ( 12000 - d2 ) / 12000 * 0.9; p.x += dx / Math.sqrt( d2 + 1 ) * f; p.y += dy / Math.sqrt( d2 + 1 ) * f; }
				p.t += 0.03; p.x += p.vx; p.y += p.vy;
				if ( p.y < -5 ) { p.y = H + 5; p.x = Math.random() * W; }
				if ( p.x < -5 ) { p.x = W + 5; } else if ( p.x > W + 5 ) { p.x = -5; }
				var tw = p.a * ( 0.6 + 0.4 * Math.sin( p.t ) );
				ctx.beginPath();
				ctx.arc( p.x, p.y, p.r, 0, 6.283 );
				ctx.fillStyle = 'rgba(226,232,240,' + tw.toFixed( 3 ) + ')';
				ctx.shadowColor = 'rgba(255,255,255,.8)';
				ctx.shadowBlur = p.r * 4;
				ctx.fill();
			}
			requestAnimationFrame( frame );
		}
		function setRunning() {
			var should = visible && ! document.hidden;
			if ( should && ! running ) { running = true; requestAnimationFrame( frame ); } else if ( ! should ) { running = false; }
		}
		resize();
		window.addEventListener( 'resize', rafThrottle( resize ) );
		if ( finePointer ) {
			hero.addEventListener( 'pointermove', function ( e ) { var r = hero.getBoundingClientRect(); mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; } );
			hero.addEventListener( 'pointerleave', function () { mouse.x = mouse.y = -999; } );
		}
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( en ) { visible = en[ 0 ].isIntersecting; setRunning(); } ).observe( hero );
		}
		document.addEventListener( 'visibilitychange', setRunning );
		setRunning();
	}

	/* ---------- Headline: words rise in one by one ---------- */
	function splitWords( el ) {
		var i = 0;
		Array.prototype.slice.call( el.childNodes ).forEach( function ( node ) {
			if ( node.nodeType === 3 && node.textContent.trim() ) {
				var frag = document.createDocumentFragment();
				node.textContent.split( /(\s+)/ ).forEach( function ( part ) {
					if ( ! part ) { return; }
					if ( /^\s+$/.test( part ) ) { frag.appendChild( document.createTextNode( part ) ); return; }
					var s = document.createElement( 'span' );
					s.className = 'ns-word';
					s.style.setProperty( '--i', i++ );
					s.textContent = part;
					frag.appendChild( s );
				} );
				node.parentNode.replaceChild( frag, node );
			} else if ( node.nodeType === 1 && node.tagName !== 'BR' ) {
				node.classList.add( 'ns-word' );
				node.style.setProperty( '--i', i++ );
			}
		} );
		el.classList.add( 'ns-split' );
	}

	/* ---------- Cards: 3D tilt with a light glare ---------- */
	function tilt( el, max ) {
		var move = rafThrottle( function ( e ) {
			var r = el.getBoundingClientRect();
			var x = ( e.clientX - r.left ) / r.width, y = ( e.clientY - r.top ) / r.height;
			el.style.transform = 'perspective(800px) rotateX(' + ( ( 0.5 - y ) * max ).toFixed( 2 ) + 'deg) rotateY(' + ( ( x - 0.5 ) * max ).toFixed( 2 ) + 'deg) translateY(-4px)';
			el.style.setProperty( '--gx', ( x * 100 ).toFixed( 1 ) + '%' );
			el.style.setProperty( '--gy', ( y * 100 ).toFixed( 1 ) + '%' );
		} );
		el.classList.add( 'ns-tilt' );
		el.addEventListener( 'pointermove', move );
		el.addEventListener( 'pointerleave', function () { el.style.transform = ''; } );
	}

	/* ---------- Buttons: magnetic pull + click ripple ---------- */
	function magnetic( btn ) {
		var move = rafThrottle( function ( e ) {
			var r = btn.getBoundingClientRect();
			var x = Math.max( -8, Math.min( 8, ( e.clientX - r.left - r.width / 2 ) * 0.2 ) );
			var y = Math.max( -5, Math.min( 5, ( e.clientY - r.top - r.height / 2 ) * 0.3 ) );
			btn.style.translate = x.toFixed( 1 ) + 'px ' + y.toFixed( 1 ) + 'px';
		} );
		btn.addEventListener( 'pointermove', move );
		btn.addEventListener( 'pointerleave', function () { btn.style.translate = ''; } );
	}

	function ripple( e ) {
		var btn = e.target.closest( '.ns-btn, .ns-choice, .ns-day, .ns-slot, .ns-chart__ranges button' );
		if ( ! btn ) { return; }
		var r = btn.getBoundingClientRect(), size = Math.max( r.width, r.height ) * 2;
		var s = document.createElement( 'span' );
		s.className = 'ns-ripple';
		s.style.width = s.style.height = size + 'px';
		s.style.left = ( e.clientX - r.left - size / 2 ) + 'px';
		s.style.top = ( e.clientY - r.top - size / 2 ) + 'px';
		btn.appendChild( s );
		setTimeout( function () { s.remove(); }, 650 );
	}

	/* ---------- Live silver price with rolling digits ---------- */
	function odometer( el, text ) {
		if ( ! el._odo ) {
			el._odo = true;
			el.classList.add( 'ns-odo' );
			el.setAttribute( 'aria-live', 'polite' );
		}
		var old = el.querySelectorAll( '.ns-odo__c' );
		var html = '';
		for ( var i = 0; i < text.length; i++ ) {
			var ch = text[ i ];
			if ( /\d/.test( ch ) ) {
				html += '<span class="ns-odo__c ns-odo__d" data-d="' + ch + '"><span class="ns-odo__s"><i>0</i><i>1</i><i>2</i><i>3</i><i>4</i><i>5</i><i>6</i><i>7</i><i>8</i><i>9</i></span></span>';
			} else {
				html += '<span class="ns-odo__c">' + ch + '</span>';
			}
		}
		var sameShape = old.length === text.length;
		if ( ! sameShape ) {
			el.innerHTML = html;
			void el.offsetWidth; // digits start at 0 and roll up to the value
		}
		el.querySelectorAll( '.ns-odo__d' ).forEach( function ( d, idx ) {
			var target = sameShape ? text.replace( /[^\d]/g, '' )[ idx ] : d.getAttribute( 'data-d' );
			d.setAttribute( 'data-d', target );
			d.firstChild.style.transitionDelay = ( idx * 70 ) + 'ms';
			d.firstChild.style.transform = 'translateY(' + ( -target * 10 ) + '%)';
		} );
		el.setAttribute( 'aria-label', text );
	}

	function livePrice() {
		var els = document.querySelectorAll( '[data-ns-live]' );
		if ( ! els.length || ! cfg.priceUrl ) { return; }
		var current = parseFloat( cfg.spotPerKg || 0 );
		els.forEach( function ( el ) { odometer( el, fmt( current / 1000, 2 ) ); } );
		function poll() {
			if ( document.hidden ) { return; }
			fetch( cfg.priceUrl, { credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( d ) {
				var next = parseFloat( d.per_kg || 0 );
				if ( ! next || Math.abs( next - current ) < 0.005 ) { return; }
				var dir = next > current ? 'up' : 'down';
				current = next;
				cfg.spotPerKg = next;
				els.forEach( function ( el ) {
					odometer( el, fmt( next / 1000, 2 ) );
					el.classList.remove( 'ns-flash--up', 'ns-flash--down' );
					void el.offsetWidth;
					el.classList.add( 'ns-flash--' + dir );
				} );
			} ).catch( function () {} );
		}
		setInterval( poll, ( cfg.pollSeconds || 60 ) * 1000 );
		document.addEventListener( 'visibilitychange', poll );
	}

	/* ---------- Add to cart: a silver coin flies into the basket ---------- */
	function flyToCart( from ) {
		var cart = findCart();
		if ( ! from || ! cart || ! from.getBoundingClientRect ) { return; }
		var a = from.getBoundingClientRect(), b = cart.getBoundingClientRect();
		var coin = document.createElement( 'span' );
		coin.className = 'ns-fly-coin';
		coin.setAttribute( 'aria-hidden', 'true' );
		coin.style.left = ( a.left + a.width / 2 - 14 ) + 'px';
		coin.style.top = ( a.top + a.height / 2 - 14 ) + 'px';
		document.body.appendChild( coin );
		var dx = b.left + b.width / 2 - ( a.left + a.width / 2 ), dy = b.top + b.height / 2 - ( a.top + a.height / 2 );
		var anim = coin.animate( [
			{ transform: 'translate(0,0) scale(1) rotateY(0)', opacity: 1 },
			{ transform: 'translate(' + dx * 0.5 + 'px,' + ( Math.min( dy, 0 ) - 120 ) + 'px) scale(1.25) rotateY(540deg)', opacity: 1, offset: 0.5 },
			{ transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.35) rotateY(1080deg)', opacity: 0.4 }
		], { duration: 900, easing: 'cubic-bezier(.5,0,.3,1)' } );
		anim.onfinish = function () {
			coin.remove();
			// WooCommerce replaces the cart element (cart fragments) while the coin flies – find it again.
			var target = findCart() || cart;
			target.classList.remove( 'ns-bump' );
			void target.offsetWidth;
			target.classList.add( 'ns-bump' );
		};
	}

	function findCart() {
		var found = null;
		[ '.wc-block-mini-cart__button', '.site-header-cart .cart-contents', '.site-header-cart', 'a.cart-contents', '.header-cart' ].some( function ( sel ) {
			var c = document.querySelector( sel );
			if ( c && c.getBoundingClientRect().width ) { found = c; }
			return !! found;
		} );
		return found;
	}

	function cartEffects() {
		var lastClicked = null;
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.add_to_cart_button, .single_add_to_cart_button, .wc-block-components-product-button__button' );
			if ( b ) { lastClicked = b; }
		}, true );
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'added_to_cart', function ( ev, frag, hash, $btn ) {
				flyToCart( ( $btn && $btn[ 0 ] ) || lastClicked );
			} );
		}
		document.body.addEventListener( 'wc-blocks_added_to_cart', function () { flyToCart( lastClicked ); } );
		// Single product pages submit a normal form: show the coin, then let the page continue.
		var form = document.querySelector( 'form.cart' );
		if ( form && ! form.closest( '.ns-no-fly' ) ) {
			form.addEventListener( 'submit', function () { flyToCart( form.querySelector( '.single_add_to_cart_button' ) ); } );
		}
	}

	/* ---------- Scroll progress bar on the silver front page ---------- */
	function scrollProgress( landing ) {
		var bar = document.createElement( 'div' );
		bar.className = 'ns-progress-bar';
		bar.setAttribute( 'aria-hidden', 'true' );
		document.body.appendChild( bar );
		var update = rafThrottle( function () {
			var r = landing.getBoundingClientRect();
			var p = Math.min( 1, Math.max( 0, -r.top / ( r.height - innerHeight ) ) );
			bar.style.transform = 'scaleX(' + p.toFixed( 4 ) + ')';
			bar.style.opacity = r.top < 0 && r.bottom > 0 ? 1 : 0;
		} );
		window.addEventListener( 'scroll', update, { passive: true } );
		update();
	}

	function effects() {
		livePrice(); // also useful without motion: the price stays current
		if ( ! animate ) { return; }
		document.querySelectorAll( '.ns-hero' ).forEach( function ( h ) {
			heroParallax( h );
			silverDust( h );
			var h1 = h.querySelector( 'h1' );
			if ( h1 ) { splitWords( h1 ); }
		} );
		document.querySelectorAll( '.ns-landing' ).forEach( scrollProgress );
		if ( finePointer ) {
			document.querySelectorAll( '.ns-cat, .ns-store, .ns-card, .ns-landing ul.products li.product, body.ns-silver ul.products li.product' ).forEach( function ( el ) {
				tilt( el, el.classList.contains( 'ns-card' ) ? 4 : 8 );
			} );
			document.querySelectorAll( '.ns-btn' ).forEach( magnetic );
		}
		document.addEventListener( 'pointerdown', ripple );
		cartEffects();
	}

	function init() {
		reveal();
		countUp();
		document.querySelectorAll( '[data-ns-chart]' ).forEach( chart );
		document.querySelectorAll( '[data-ns-sell]' ).forEach( sell );
		effects();
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
