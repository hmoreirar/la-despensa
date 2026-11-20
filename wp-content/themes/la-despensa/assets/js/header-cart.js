(function () {
	'use strict';

	const SELECTOR = '.ld-cart__count';
	const ACTIONS = [
		'.wc-block-components-product-button__button',
		'.single_add_to_cart_button',
		'.wc-block-cart-item__remove-link',
		'.wc-block-components-quantity-selector__button',
		'.wc-block-cart__submit-button',
	].join(',');

	function setCount(value) {
		const count = String(value).replace(/[^0-9]/g, '') || '0';

		document.querySelectorAll(SELECTOR).forEach(function (element) {
			element.textContent = count;

			const link = element.closest('.ld-cart');
			if (link) {
				link.setAttribute('aria-label', 'Carrito, ' + count + ' productos');
			}
		});
	}

	function refreshCount() {
		fetch('/?wc-ajax=get_refreshed_fragments', { credentials: 'same-origin' })
			.then(function (response) { return response.json(); })
			.then(function (data) {
				const html = data && data.fragments && data.fragments[SELECTOR];
				if (!html) {
					return;
				}

				const holder = document.createElement('div');
				holder.innerHTML = html;
				setCount(holder.textContent.trim());
			})
			.catch(function () {});
	}

	function scheduleRefresh() {
		window.setTimeout(refreshCount, 700);
		window.setTimeout(refreshCount, 1800);
	}

	document.addEventListener('click', function (event) {
		if (event.target.closest(ACTIONS)) {
			scheduleRefresh();
		}
	});

	if (window.jQuery) {
		jQuery(document.body).on('added_to_cart wc_fragments_refreshed updated_cart_totals', scheduleRefresh);
	}

	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'visible') {
			refreshCount();
		}
	});
})();
