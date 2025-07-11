document.addEventListener('DOMContentLoaded', function () {
    const min = seedlingSettings.minQty;
    const slug = seedlingSettings.slug;
	const body = document.body;
	const variationForm = document.querySelector('form.variations_form');

    if (!body.classList.contains(`product_cat-${slug}`)) return;

    if (!qtyInput || !variationIdInput) return;

    variationForm.addEventListener('woocommerce_variation_select_change', () => {
        // variation_id ещё может быть не установлен
        setTimeout(checkAndUpdateQuantity, 100);
    });

    variationForm.addEventListener('found_variation', function (e) {
        const variation = e.detail?.variation || e.detail;
        const variationId = variation?.variation_id;
		const qtyInput = document.querySelector('input.qty');
		const variationIdInput = document.querySelector('input[name="variation_id"]');

        if (!variationId) return;

        fetch(`/wp-admin/admin-ajax.php?action=seedling_get_cart_qty&variation_id=${variationId}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const alreadyInCart = data.data.quantity || 0;
                const toAdd = Math.max(min - alreadyInCart, 1);

                qtyInput.value = toAdd;

                qtyInput.addEventListener('input', () => {
                    if (parseInt(qtyInput.value || '0') < toAdd) {
                        qtyInput.value = toAdd;
                    }
                });

                const minusBtn = document.querySelector('.quantity .minus');
                if (minusBtn) {
                    minusBtn.addEventListener('click', function () {
                        setTimeout(() => {
                            if (parseInt(qtyInput.value || '0') < toAdd) {
                                qtyInput.value = toAdd;
                            }
                        }, 100);
                    });
                }
            });
    });
	
	jQuery(function ($) {
        $(document.body).on('click', '.single_add_to_cart_button', function (e) {
            let wasAdded = false;

            $(document.body).one('added_to_cart', function () {
                wasAdded = true;
            });

            setTimeout(() => {
                if (!wasAdded) {
                    // Если не добавилось — попробуем показать уведомление из сессии
                    $.get('/?wc-ajax=get_refreshed_fragments', function (data) {
                        if (data && data.fragments && data.fragments['div.woocommerce-error']) {
                            const container = $('.woocommerce-notices-wrapper, .mfn-ch-content, .cart-empty');

							if (container.length) {
								container.first().html(data.fragments['div.woocommerce-error']);
							} else {
								$('form.cart').before(data.fragments['div.woocommerce-error']);
							}
                        }
                    });
                }
            }, 1200);
        });
    });
	
});
