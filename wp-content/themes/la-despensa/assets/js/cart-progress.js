(function () {
	'use strict';

	const box = document.querySelector('.ld-free-shipping');

	if (!box || !window.ldCartProgress) {
		return;
	}

	const config = window.ldCartProgress;
	const message = box.querySelector('p');
	const bar = box.querySelector('.ld-free-shipping__bar');
	const fill = bar ? bar.querySelector('span') : null;
	let timer;

	function money(amount) {
		return '$ ' + new Intl.NumberFormat('es-CL').format(Math.round(amount));
	}

	function render(state) {
		box.hidden = Boolean(state.empty);

		if (state.empty) {
			return;
		}

		box.classList.toggle('is-complete', Boolean(state.free));
		message.textContent = state.free
			? 'Tu pedido tiene envío gratis.'
			: 'Te faltan ' + money(state.remaining) + ' para envío gratis.';

		const percent = state.threshold > 0
			? Math.min(100, Math.round((state.subtotal / state.threshold) * 100))
			: 100;

		if (bar) {
			bar.setAttribute('aria-valuenow', String(percent));
		}

		if (fill) {
			fill.style.width = percent + '%';
		}
	}

	async function refresh() {
		const body = new URLSearchParams({
			action: 'ld_cart_progress',
			nonce: config.nonce,
		});

		try {
			const response = await fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body,
			});
			const payload = await response.json();

			if (payload && payload.success) {
				render(payload.data);
			}
		} catch (error) {
			// El aviso inicial sigue visible si falla la actualización.
		}
	}

	function schedule() {
		window.clearTimeout(timer);
		timer = window.setTimeout(refresh, 500);
	}

	const cart = document.querySelector('.wc-block-cart');

	if (cart) {
		new MutationObserver(schedule).observe(cart, {
			childList: true,
			subtree: true,
			characterData: true,
		});
	}

	window.setTimeout(refresh, 1200);
})();
