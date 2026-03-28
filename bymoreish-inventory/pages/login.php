<?php
/**
 * Login Page – Bymoreish Inventory
 *
 * Custom session-based login. NOT the WordPress login form.
 * Handles ?action=logout to destroy the session.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Note: logout is handled by the router (class-router.php) before this
// file is ever included, so there is no logout block here.

// Already authenticated → send home
if ( bymoreish_is_authenticated() ) {
	wp_redirect( home_url( '/bymoreish/home' ) );
	exit;
}

$plugin_url = BYMOREISH_PLUGIN_URL;
$ajax_url   = admin_url( 'admin-ajax.php' );
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login – Bymoreish Inventory</title>

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
				},
			},
		};
	</script>

	<!-- Iconify CDN (Solar icons) -->
	<script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>

	<!-- Plugin CSS -->
	<link rel="stylesheet" href="<?php echo esc_url( $plugin_url ); ?>assets/css/style.css">

	<style>
		/* ---------- Background canvas ---------- */
		#login-canvas {
			position: fixed;
			inset: 0;
			z-index: 0;
		}

		/* ---------- Body gradient ---------- */
		body {
			background: #0a0a0f;
		}

		/* ---------- Glass card ---------- */
		.glass-card {
			background: rgba(255,255,255,0.07);
			backdrop-filter: blur(24px);
			-webkit-backdrop-filter: blur(24px);
			border: 1px solid rgba(255,255,255,0.13);
			box-shadow: 0 12px 50px rgba(0,0,0,0.5);
		}

		/* ---------- Input field styling ---------- */
		.bym-input {
			background: rgba(255,255,255,0.06);
			border: 1px solid rgba(255,255,255,0.12);
			color: #f1f5f9;
			transition: border-color 0.25s, box-shadow 0.25s;
			outline: none;
		}
		.bym-input::placeholder {
			color: rgba(148,163,184,0.55);
		}
		.bym-input:focus {
			border-color: rgba(238,206,85,0.5);
			box-shadow: 0 0 0 3px rgba(238,206,85,0.1);
		}

		/* ---------- Pill login button with beam ---------- */
		.pill-btn {
			position: relative;
			overflow: hidden;
			cursor: pointer;
			transition: transform 0.2s, box-shadow 0.2s;
			background: linear-gradient(135deg, #EECE55, #d4b043);
			color: #0a0a0f;
		}
		.pill-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 25px rgba(238,206,85,0.4);
		}
		.pill-btn:active {
			transform: translateY(0);
		}
		.pill-btn::after {
			content: '';
			position: absolute;
			top: 0; left: -100%;
			width: 60%;
			height: 100%;
			background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
			animation: beamSlide 2.8s ease-in-out infinite;
		}
		@keyframes beamSlide {
			0%   { left: -100%; }
			55%  { left: 130%; }
			100% { left: 130%; }
		}

		/* ---------- Three-dot loader ---------- */
		.dot-loader {
			display: inline-flex;
			gap: 5px;
			align-items: center;
		}
		.dot-loader span {
			width: 7px; height: 7px;
			border-radius: 50%;
			background: #0a0a0f;
			animation: dotBounce 1.1s ease-in-out infinite;
		}
		.dot-loader span:nth-child(2) { animation-delay: 0.18s; }
		.dot-loader span:nth-child(3) { animation-delay: 0.36s; }
		@keyframes dotBounce {
			0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
			40%            { transform: scale(1);   opacity: 1;   }
		}

		/* ---------- Fade-in animation ---------- */
		@keyframes fadeUp {
			from { opacity: 0; transform: translateY(20px); }
			to   { opacity: 1; transform: translateY(0); }
		}
		.fade-up {
			animation: fadeUp 0.7s cubic-bezier(.22,1,.36,1) forwards;
		}

		/* ---------- Error shake ---------- */
		@keyframes shake {
			0%,100% { transform: translateX(0); }
			20%      { transform: translateX(-6px); }
			40%      { transform: translateX(6px); }
			60%      { transform: translateX(-4px); }
			80%      { transform: translateX(4px); }
		}
		.shake { animation: shake 0.4s ease; }

		/* ---------- Checkbox styling ---------- */
		.bym-checkbox {
			accent-color: #EECE55;
		}
	</style>
</head>
<body class="dark min-h-screen text-white antialiased flex items-center justify-center px-4 py-12">

	<!-- Animated canvas background -->
	<canvas id="login-canvas" aria-hidden="true"></canvas>

	<!-- Login card -->
	<div class="relative z-10 w-full max-w-sm fade-up">
		<div class="glass-card rounded-2xl px-8 py-10">

			<!-- Brand -->
			<div class="flex flex-col items-center gap-3 mb-8">
				<div class="w-14 h-14 rounded-xl flex items-center justify-center shadow-xl"
				     style="background:linear-gradient(135deg,#EECE55,#d4b043);">
					<iconify-icon icon="solar:chef-hat-linear" style="font-size:1.8rem;color:#0a0a0f;"></iconify-icon>
				</div>
				<div class="text-center">
					<h1 class="text-2xl font-extrabold text-white">Bymoreish</h1>
					<p class="text-xs text-gray-400 tracking-widest uppercase mt-0.5">Inventory Management</p>
				</div>
			</div>

			<!-- Error message area -->
			<div id="error-box"
			     class="hidden mb-5 flex items-start gap-3 px-4 py-3 rounded-xl text-sm text-red-300"
			     style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);"
			     role="alert"
			     aria-live="polite">
				<iconify-icon icon="solar:danger-circle-linear" style="font-size:1.2rem;flex-shrink:0;margin-top:1px;"></iconify-icon>
				<span id="error-message"></span>
			</div>

			<!-- Login form -->
			<form id="login-form" novalidate>

				<!-- Username -->
				<div class="mb-4">
					<label for="bym-username" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
						Username
					</label>
					<div class="relative">
						<span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
						      style="color:#EECE55;">
							<iconify-icon icon="solar:user-linear" style="font-size:1.15rem;"></iconify-icon>
						</span>
						<input
							id="bym-username"
							name="username"
							type="text"
							autocomplete="username"
							placeholder="Enter your username"
							required
							class="bym-input w-full rounded-xl pl-10 pr-4 py-3 text-sm"
						>
					</div>
				</div>

				<!-- Password -->
				<div class="mb-5">
					<label for="bym-password" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
						Password
					</label>
					<div class="relative">
						<span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
						      style="color:#EECE55;">
							<iconify-icon icon="solar:lock-password-linear" style="font-size:1.15rem;"></iconify-icon>
						</span>
						<input
							id="bym-password"
							name="password"
							type="password"
							autocomplete="current-password"
							placeholder="Enter your password"
							required
							class="bym-input w-full rounded-xl pl-10 pr-11 py-3 text-sm"
						>
						<button
							type="button"
							id="toggle-password"
							aria-label="Toggle password visibility"
							class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors"
						>
							<iconify-icon id="eye-icon" icon="solar:eye-linear" style="font-size:1.15rem;"></iconify-icon>
						</button>
					</div>
				</div>

				<!-- Remember me -->
				<div class="flex items-center gap-2.5 mb-7">
					<input
						id="remember-me"
						name="remember_me"
						type="checkbox"
						class="bym-checkbox w-4 h-4 rounded"
					>
					<label for="remember-me" class="text-sm text-gray-400 cursor-pointer select-none">
						Remember me
					</label>
				</div>

				<!-- Submit button -->
				<button
					type="submit"
					id="login-btn"
					class="pill-btn w-full py-3 rounded-full text-sm font-bold tracking-wide"
				>
					<span id="btn-label">Sign In</span>
					<span id="btn-loader" class="dot-loader hidden">
						<span></span><span></span><span></span>
					</span>
				</button>
			</form>

			<!-- Back link -->
			<div class="mt-6 text-center">
				<a href="<?php echo esc_url( home_url( '/bymoreish/' ) ); ?>"
				   class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-300 transition-colors">
					<iconify-icon icon="solar:arrow-left-linear" style="font-size:1rem;"></iconify-icon>
					Back to Branch Selection
				</a>
			</div>
		</div>
	</div>

	<!-- Plugin JS -->
	<script src="<?php echo esc_url( $plugin_url ); ?>assets/js/main.js" defer></script>

	<script>
	/* ============================================================
	   Config
	   ============================================================ */
	const BYM_AJAX_URL = <?php echo wp_json_encode( $ajax_url ); ?>;

	/* ============================================================
	   Canvas food animation
	   ============================================================ */
	(function initCanvas() {
		const canvas = document.getElementById('login-canvas');
		const ctx    = canvas.getContext('2d');
		const ITEMS  = ['🍔','🍕','🥗','🍜','🥩','🍗','🌮','🥘','🥚','🍱','🧆','🥞','🍳','🫕','🫙'];
		const COUNT  = 15;
		let   W, H, particles;

		function resize() {
			W = canvas.width  = window.innerWidth;
			H = canvas.height = window.innerHeight;
		}

		function makeParticle(i) {
			return {
				emoji: ITEMS[i % ITEMS.length],
				x:     Math.random() * W,
				y:     Math.random() * H,
				vx:    (Math.random() - 0.5) * 0.45,
				vy:    (Math.random() - 0.5) * 0.45,
				size:  22 + Math.random() * 16,
				phase: Math.random() * Math.PI * 2,
				speed: 0.5 + Math.random() * 0.8,
			};
		}

		function init() {
			resize();
			particles = Array.from({ length: COUNT }, (_, i) => makeParticle(i));
		}

		function draw(ts) {
			ctx.clearRect(0, 0, W, H);

			// Update positions
			const t = ts * 0.001;
			particles.forEach(function(p) {
				p.x += p.vx;
				p.y += p.vy + Math.sin(t * p.speed + p.phase) * 0.15;
				if (p.x < -40)  p.x = W + 40;
				if (p.x > W+40) p.x = -40;
				if (p.y < -40)  p.y = H + 40;
				if (p.y > H+40) p.y = -40;
			});

			// Draw connecting lines (noodles) between nearby particles
			ctx.lineWidth = 1;
			for (let i = 0; i < particles.length; i++) {
				for (let j = i + 1; j < particles.length; j++) {
					const dx   = particles[i].x - particles[j].x;
					const dy   = particles[i].y - particles[j].y;
					const dist = Math.sqrt(dx * dx + dy * dy);
					if (dist < 160) {
						const alpha = (1 - dist / 160) * 0.12;
						ctx.strokeStyle = `rgba(238,206,85,${alpha})`;
						ctx.beginPath();
						ctx.moveTo(particles[i].x, particles[i].y);
						ctx.lineTo(particles[j].x, particles[j].y);
						ctx.stroke();
					}
				}
			}

			// Draw food items
			particles.forEach(function(p) {
				const scale  = 1 + Math.sin(t * p.speed + p.phase) * 0.06;
				const alpha  = 0.10 + Math.abs(Math.sin(t * 0.3 + p.phase)) * 0.06;
				ctx.globalAlpha = alpha;
				ctx.save();
				ctx.translate(p.x, p.y);
				ctx.scale(scale, scale);
				ctx.font = p.size + 'px serif';
				ctx.textAlign    = 'center';
				ctx.textBaseline = 'middle';
				ctx.fillText(p.emoji, 0, 0);
				ctx.restore();
			});

			ctx.globalAlpha = 1;
			requestAnimationFrame(draw);
		}

		window.addEventListener('resize', resize);
		init();
		requestAnimationFrame(draw);
	})();

	/* ============================================================
	   Password visibility toggle
	   ============================================================ */
	document.getElementById('toggle-password').addEventListener('click', function() {
		const pwInput  = document.getElementById('bym-password');
		const eyeIcon  = document.getElementById('eye-icon');
		const isHidden = pwInput.type === 'password';
		pwInput.type   = isHidden ? 'text' : 'password';
		eyeIcon.setAttribute('icon', isHidden ? 'solar:eye-closed-linear' : 'solar:eye-linear');
	});

	/* ============================================================
	   Form helpers
	   ============================================================ */
	function showLoading() {
		document.getElementById('btn-label').classList.add('hidden');
		document.getElementById('btn-loader').classList.remove('hidden');
		document.getElementById('login-btn').disabled = true;
	}

	function hideLoading() {
		document.getElementById('btn-label').classList.remove('hidden');
		document.getElementById('btn-loader').classList.add('hidden');
		document.getElementById('login-btn').disabled = false;
	}

	function showError(msg) {
		const box = document.getElementById('error-box');
		document.getElementById('error-message').textContent = msg;
		box.classList.remove('hidden');
		box.classList.remove('shake');
		void box.offsetWidth; // reflow
		box.classList.add('shake');
	}

	function hideError() {
		document.getElementById('error-box').classList.add('hidden');
	}

	/* ============================================================
	   AJAX helper
	   ============================================================ */
	async function bymAjax(action, data) {
		const body = new URLSearchParams({ action, ...data });
		const res  = await fetch(BYM_AJAX_URL, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		});
		if (!res.ok) throw new Error('Network error: ' + res.status);
		return res.json();
	}

	/* ============================================================
	   Navigate helper
	   ============================================================ */
	function navigateTo(path) {
		window.location.href = path;
	}

	/* ============================================================
	   Login form submission
	   ============================================================ */
	document.getElementById('login-form').addEventListener('submit', async function(e) {
		e.preventDefault();
		hideError();

		const username    = document.getElementById('bym-username').value.trim();
		const password    = document.getElementById('bym-password').value;
		const remember_me = document.getElementById('remember-me').checked ? '1' : '0';

		if (!username || !password) {
			showError('Please enter your username and password.');
			return;
		}

		showLoading();

		try {
			const response = await bymAjax('bym_login', { username, password, remember_me });
			if (response && response.success) {
				navigateTo(<?php echo wp_json_encode( home_url( '/bymoreish/home' ) ); ?>);
			} else {
				const msg = (response && response.data && response.data.message)
					? response.data.message
					: 'Invalid credentials. Please try again.';
				showError(msg);
				hideLoading();
			}
		} catch (err) {
			showError('Connection error. Please check your internet and try again.');
			hideLoading();
		}
	});

	// Clear error when user types
	['bym-username', 'bym-password'].forEach(function(id) {
		document.getElementById(id).addEventListener('input', hideError);
	});
	</script>
</body>
</html>
