// Скрипт обеспечивает выполнение минимального количества для вариаций товара
// на странице продукта.
document.addEventListener('DOMContentLoaded', function () {
    // Минимальное количество для текущей вариации
    const min = seedlingProductSettings.minQty;
    // Слаг категории, для которой действует ограничение
    const slug = seedlingProductSettings.slug;
    const body = document.body;
    // Основная форма выбора вариаций
    const variationForm = document.querySelector('form.variations_form');
    // Поле ввода количества товара
    const qtyInput = document.querySelector('input.qty');
    // Скрытое поле с выбранной вариацией
    const variationIdInput = document.querySelector('input[name="variation_id"]');

    // Проверяем, существует ли форма выбора вариации. Если её нет,
    // обработчики событий не назначаются, чтобы избежать ошибок.
    if (!variationForm) {
        return;
    }

    if (!body.classList.contains(`product_cat-${slug}`)) return;

    if (!qtyInput || !variationIdInput) return;

    // Фактический минимум, разрешённый в поле количества.
    // Значение изменяется функцией checkAndUpdateQuantity.
    let enforcedMin = 1;

    // Ссылка на кнопку "минус" для корректного управления обработчиками
    let boundMinusBtn = null;

    /**
     * Обработчик ввода в поле количества.
     * Следит за тем, чтобы значение не было меньше заданного минимума.
     */
    function handleQtyInput() {
        if (parseInt(qtyInput.value || '0') < enforcedMin) {
            qtyInput.value = enforcedMin;
        }
    }

    /**
     * Обработчик клика на кнопку уменьшения количества.
     * После изменения значения проверяет соблюдение минимума.
     */
    function handleMinusClick() {
        setTimeout(() => {
            if (parseInt(qtyInput.value || '0') < enforcedMin) {
                qtyInput.value = enforcedMin;
            }
        }, 100);
    }

    /**
     * Получает количество выбранной вариации в корзине и
     * устанавливает минимально допустимое значение в поле ввода.
     */
    function checkAndUpdateQuantity() {
        const variationId = parseInt(variationIdInput.value || '0');
        if (!variationId) return;

        fetch(`/wp-admin/admin-ajax.php?action=seedling_get_cart_qty&variation_id=${variationId}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const alreadyInCart = data.data.quantity || 0;
                enforcedMin = Math.max(min - alreadyInCart, 1);

                qtyInput.value = enforcedMin;

                // Обновляем обработчики, чтобы избежать дублирования при смене вариаций.
                qtyInput.removeEventListener('input', handleQtyInput);
                qtyInput.addEventListener('input', handleQtyInput);

                const minusBtn = document.querySelector('.quantity .minus');
                if (boundMinusBtn && boundMinusBtn !== minusBtn) {
                    boundMinusBtn.removeEventListener('click', handleMinusClick);
                }
                if (minusBtn) {
                    minusBtn.removeEventListener('click', handleMinusClick);
                    minusBtn.addEventListener('click', handleMinusClick);
                    boundMinusBtn = minusBtn;
                }
            });
    }

    variationForm.addEventListener('woocommerce_variation_select_change', () => {
        // variation_id ещё может быть не установлен
        setTimeout(checkAndUpdateQuantity, 100);
    });

    variationForm.addEventListener('found_variation', function (e) {
        const variation = e.detail?.variation || e.detail;
        const variationId = variation?.variation_id;

        if (!variationId) return;

        // WooCommerce может установить значение позже, поэтому подстрахуемся
        variationIdInput.value = variationId;
        checkAndUpdateQuantity();
    });
	
    // Отслеживаем процесс добавления товара в корзину, чтобы при ошибке
    // показать сообщение из сессии WooCommerce.
    jQuery(function ($) {
        $(document.body).on('click', '.single_add_to_cart_button', function () {
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
