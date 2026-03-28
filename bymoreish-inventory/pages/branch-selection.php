<?php
/**
 * Branch Selection Page – Bymoreish Inventory
 *
 * Landing/splash page. Users pick their branch before logging in.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_url = BYMOREISH_PLUGIN_URL;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Select Branch – Bymoreish Inventory</title>

	<!-- Tailwind CSS CDN -->
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		tailwind.config = {
			darkMode: 'class',
			theme: {
				extend: {
					colors: {
						gold:  '#EECE55',
						green: '#4CB050',
					},
					animation: {
						'float-slow':   'floatSlow 8s ease-in-out infinite',
						'float-medium': 'floatSlow 5s ease-in-out infinite',
						'pulse-glow':   'pulseGlow 3s ease-in-out infinite',
						'fade-up':      'fadeUp 0.8s ease forwards',
					},
					keyframes: {
						floatSlow: {
							'0%, 100%': { transform: 'translateY(0px) rotate(0deg)' },
							'50%':      { transform: 'translateY(-20px) rotate(8deg)' },
						},
						pulseGlow: {
							'0%, 100%': { opacity: '0.6', transform: 'scale(1)' },
							'50%':      { opacity: '1',   transform: 'scale(1.05)' },
						},
						fadeUp: {
							'0%':   { opacity: '0', transform: 'translateY(24px)' },
							'100%': { opacity: '1', transform: 'translateY(0)' },
						},
					},
				},
			},
		};
	</script>

	<!-- Iconify CDN (Solar icons) -->
	<script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>

	<!-- Plugin CSS -->
	<link rel="stylesheet" href="<?php echo esc_url( $plugin_url ); ?>assets/css/style.css">

	<style>
		/* ---------- Letter animation ---------- */
		.text-animate .letter {
			display: inline-block;
			opacity: 0;
			transform: translateY(30px) scale(0.85);
			animation: letterDrop 0.5s cubic-bezier(.22,1,.36,1) forwards;
		}
		@keyframes letterDrop {
			to { opacity: 1; transform: translateY(0) scale(1); }
		}

		/* ---------- Glassmorphism card ---------- */
		.glass-card {
			background: rgba(255,255,255,0.06);
			backdrop-filter: blur(18px);
			-webkit-backdrop-filter: blur(18px);
			border: 1px solid rgba(255,255,255,0.12);
			box-shadow: 0 8px 40px rgba(0,0,0,0.35);
		}

		/* ---------- Active card hover glow ---------- */
		.branch-card-active {
			cursor: pointer;
			transition: border-color 0.3s, box-shadow 0.3s, transform 0.3s;
		}
		.branch-card-active:hover {
			border-color: #EECE55 !important;
			box-shadow: 0 0 30px rgba(238,206,85,0.35), 0 8px 40px rgba(0,0,0,0.45) !important;
			transform: translateY(-4px);
		}

		/* ---------- Pill beam animation ---------- */
		.pill-beam {
			position: relative;
			overflow: hidden;
		}
		.pill-beam::after {
			content: '';
			position: absolute;
			top: 0; left: -100%;
			width: 60%;
			height: 100%;
			background: linear-gradient(90deg, transparent, rgba(238,206,85,0.3), transparent);
			animation: beamSlide 3s ease-in-out infinite;
		}
		@keyframes beamSlide {
			0%   { left: -100%; }
			50%  { left: 130%; }
			100% { left: 130%; }
		}

		/* ---------- Food particles ---------- */
		.food-particle {
			position: absolute;
			pointer-events: none;
			user-select: none;
			font-size: clamp(1.5rem, 3vw, 2.5rem);
			opacity: 0.12;
			filter: blur(0.5px);
		}

		/* ---------- Gradient background ---------- */
		body {
			background: radial-gradient(ellipse at 20% 30%, rgba(76,176,80,0.12) 0%, transparent 60%),
			            radial-gradient(ellipse at 80% 70%, rgba(238,206,85,0.10) 0%, transparent 60%),
			            #0a0a0f;
		}

		/* ---------- Coming-soon modal overlay ---------- */
		#cs-modal {
			display: none;
		}
		#cs-modal.open {
			display: flex;
		}
	</style>
</head>
<body class="dark min-h-screen text-white antialiased overflow-x-hidden">

	<!-- ====== Decorative food particles ====== -->
	<div aria-hidden="true" id="particles-container" class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
		<!-- Injected by JS -->
	</div>

	<!-- ====== Main content ====== -->
	<main class="relative z-10 min-h-screen flex flex-col items-center justify-center px-4 py-16">

		<!-- Brand header -->
		<div class="flex flex-col items-center gap-4 mb-12" style="animation: fadeUp 0.6s ease forwards; opacity:0;">
			<div class="w-16 h-16 rounded-2xl flex items-center justify-center shadow-xl"
			     style="background: linear-gradient(135deg,#EECE55,#d4b043);">
				<iconify-icon icon="solar:chef-hat-linear" style="font-size:2rem;color:#0a0a0f;"></iconify-icon>
			</div>
			<span class="text-sm font-semibold tracking-[0.3em] uppercase"
			      style="color:#EECE55; letter-spacing:0.3em;">Inventory System</span>
		</div>

		<!-- Animated heading -->
		<h1 class="text-animate text-4xl sm:text-5xl lg:text-6xl font-extrabold text-center leading-tight mb-4"
		    aria-label="Welcome to Bymoreish">
			Welcome to Bymoreish
		</h1>

		<!-- Tagline -->
		<p class="text-lg text-gray-400 text-center max-w-md mb-16"
		   style="animation: fadeUp 1s ease 0.9s forwards; opacity:0;">
			Select your branch to access the inventory management system
		</p>

		<!-- Branch cards grid -->
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 w-full max-w-4xl"
		     style="animation: fadeUp 1s ease 1.1s forwards; opacity:0;">

			<!-- ── Card 1: Bymoreish Behind Marlima (ACTIVE) ── -->
			<a href="<?php echo esc_url( home_url( '/bymoreish/login' ) ); ?>"
			   class="glass-card branch-card-active rounded-2xl p-8 flex flex-col items-center text-center gap-5 no-underline"
			   aria-label="Bymoreish Behind Marlima – Click to log in">

				<div class="w-14 h-14 rounded-xl flex items-center justify-center"
				     style="background: rgba(238,206,85,0.15); border:1px solid rgba(238,206,85,0.3);">
					<iconify-icon icon="solar:map-point-linear" style="font-size:1.75rem;color:#EECE55;"></iconify-icon>
				</div>

				<div>
					<p class="text-xs font-semibold tracking-widest uppercase mb-2" style="color:#4CB050;">● Active</p>
					<h2 class="text-lg font-bold text-white leading-snug">Bymoreish<br>Behind Marlima</h2>
					<p class="text-sm text-gray-400 mt-2">Main branch &amp; flagship location</p>
				</div>

				<span class="pill-beam mt-auto inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold"
				      style="background:linear-gradient(90deg,rgba(238,206,85,0.2),rgba(238,206,85,0.1));
				             border:1px solid rgba(238,206,85,0.35);color:#EECE55;">
					<iconify-icon icon="solar:arrow-right-linear" style="font-size:1rem;"></iconify-icon>
					Enter Branch
				</span>
			</a>

			<!-- ── Card 2: Bymoreish Hilltop (COMING SOON) ── -->
			<button type="button"
			        onclick="document.getElementById('cs-modal').classList.add('open')"
			        class="glass-card rounded-2xl p-8 flex flex-col items-center text-center gap-5
			               opacity-50 cursor-default transition-opacity hover:opacity-60"
			        aria-label="Bymoreish Hilltop – Coming Soon">

				<div class="w-14 h-14 rounded-xl flex items-center justify-center"
				     style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);">
					<iconify-icon icon="solar:map-point-linear" style="font-size:1.75rem;color:#94a3b8;"></iconify-icon>
				</div>

				<div>
					<span class="inline-block px-3 py-0.5 rounded-full text-xs font-semibold mb-2 uppercase tracking-wider"
					      style="background:rgba(148,163,184,0.15);color:#94a3b8;border:1px solid rgba(148,163,184,0.2);">
						Coming Soon
					</span>
					<h2 class="text-lg font-bold text-gray-300 leading-snug">Bymoreish<br>Hilltop</h2>
					<p class="text-sm text-gray-500 mt-2">Hilltop location opening soon</p>
				</div>

				<span class="mt-auto inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium text-gray-500"
				      style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);">
					<iconify-icon icon="solar:clock-circle-linear" style="font-size:1rem;"></iconify-icon>
					Opening Soon
				</span>
			</button>

			<!-- ── Card 3: Bymoreish Food Truck (COMING SOON) ── -->
			<button type="button"
			        onclick="document.getElementById('cs-modal').classList.add('open')"
			        class="glass-card rounded-2xl p-8 flex flex-col items-center text-center gap-5
			               opacity-50 cursor-default transition-opacity hover:opacity-60"
			        aria-label="Bymoreish Food Truck – Coming Soon">

				<div class="w-14 h-14 rounded-xl flex items-center justify-center"
				     style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);">
					<iconify-icon icon="solar:delivery-linear" style="font-size:1.75rem;color:#94a3b8;"></iconify-icon>
				</div>

				<div>
					<span class="inline-block px-3 py-0.5 rounded-full text-xs font-semibold mb-2 uppercase tracking-wider"
					      style="background:rgba(148,163,184,0.15);color:#94a3b8;border:1px solid rgba(148,163,184,0.2);">
						Coming Soon
					</span>
					<h2 class="text-lg font-bold text-gray-300 leading-snug">Bymoreish<br>Food Truck</h2>
					<p class="text-sm text-gray-500 mt-2">Mobile dining experience on the way</p>
				</div>

				<span class="mt-auto inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium text-gray-500"
				      style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);">
					<iconify-icon icon="solar:clock-circle-linear" style="font-size:1rem;"></iconify-icon>
					Opening Soon
				</span>
			</button>
		</div>
	</main>

	<!-- ====== Footer ====== -->
	<footer class="relative z-10 text-center py-6 text-xs text-gray-600">
		© <?php echo esc_html( gmdate( 'Y' ) ); ?> Bymoreish. All rights reserved.
	</footer>

	<!-- ====== Coming-soon modal ====== -->
	<div id="cs-modal"
	     class="fixed inset-0 z-50 items-center justify-center p-4"
	     style="background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);">
		<div class="glass-card rounded-2xl p-8 max-w-sm w-full text-center">
			<div class="w-14 h-14 rounded-xl mx-auto mb-4 flex items-center justify-center"
			     style="background:rgba(238,206,85,0.12);border:1px solid rgba(238,206,85,0.25);">
				<iconify-icon icon="solar:clock-circle-linear" style="font-size:2rem;color:#EECE55;"></iconify-icon>
			</div>
			<h3 class="text-xl font-bold text-white mb-2">Coming Soon</h3>
			<p class="text-gray-400 text-sm mb-6">
				This branch is under preparation and will be available shortly. Stay tuned!
			</p>
			<button onclick="document.getElementById('cs-modal').classList.remove('open')"
			        class="px-6 py-2.5 rounded-full text-sm font-semibold transition-all duration-200"
			        style="background:linear-gradient(135deg,#EECE55,#d4b043);color:#0a0a0f;">
				Got it
			</button>
		</div>
	</div>

	<!-- Plugin JS -->
	<script src="<?php echo esc_url( $plugin_url ); ?>assets/js/main.js" defer></script>

	<script>
	/* ============================================================
	   Letter-by-letter animation
	   ============================================================ */
	function initLetterAnimation(selector) {
		document.querySelectorAll(selector).forEach(function(el) {
			const text  = el.textContent;
			const words = text.split(' ');
			el.innerHTML = '';
			let globalIndex = 0;

			words.forEach(function(word, wi) {
				const wordSpan = document.createElement('span');
				wordSpan.style.display = 'inline-block';
				wordSpan.style.whiteSpace = 'nowrap';

				Array.from(word).forEach(function(char) {
					const span = document.createElement('span');
					span.classList.add('letter');
					span.textContent = char;
					span.style.animationDelay = (0.05 + globalIndex * 0.055) + 's';
					wordSpan.appendChild(span);
					globalIndex++;
				});

				el.appendChild(wordSpan);

				// Space between words (except last)
				if (wi < words.length - 1) {
					const space = document.createTextNode('\u00A0');
					el.appendChild(space);
				}
			});
		});
	}

	/* ============================================================
	   Food particles
	   ============================================================ */
	(function spawnParticles() {
		const EMOJIS = ['🍔','🍕','🥗','🍜','🥩','🍗','🌮','🥘','🫕','🍱','🥚','🫙','🧆','🥞','🍳'];
		const container = document.getElementById('particles-container');
		const count = 18;

		for (let i = 0; i < count; i++) {
			const el = document.createElement('div');
			el.classList.add('food-particle');
			el.textContent = EMOJIS[i % EMOJIS.length];

			const x   = Math.random() * 100;
			const y   = Math.random() * 100;
			const dur = 6 + Math.random() * 8;
			const del = Math.random() * 5;

			el.style.left   = x + '%';
			el.style.top    = y + '%';
			el.style.animationDuration  = dur + 's';
			el.style.animationDelay     = '-' + del + 's';
			el.style.animation = `floatSlow ${dur}s ${del}s ease-in-out infinite`;

			container.appendChild(el);
		}
	})();

	/* ============================================================
	   Init
	   ============================================================ */
	document.addEventListener('DOMContentLoaded', function() {
		initLetterAnimation('.text-animate');
	});

	/* Allow closing coming-soon modal by clicking backdrop */
	document.getElementById('cs-modal').addEventListener('click', function(e) {
		if (e.target === this) {
			this.classList.remove('open');
		}
	});
	</script>
</body>
</html>
