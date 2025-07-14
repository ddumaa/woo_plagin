// Скрипт обеспечивает выполнение минимального количества для вариаций товара
// на странице продукта.
// Использует AJAX-метод get_cart_qty из PHP-плагина для расчёта доступного
// минимума.
document.addEventListener('DOMContentLoaded', function () {
    // Минимальное количество для текущей вариации
    const min = seedlingProductSettings.minQty;
    // Слаг категории, для которой действует ограничение
    const slug = seedlingProductSettings.slug;
    const body = document.body;
    // Основная форма выбора вариаций
    const variationForm = document.querySelector('form.variations_form');

    // Скрытое поле с выбранной вариацией может создаваться динамически,
    // поэтому каждый раз ищем его заново. Метод ограничен только поиском
    // элемента в DOM и следует принципу SRP.
    function getVariationIdInput() {
        return document.querySelector('input[name="variation_id"]');
    }

    // Проверяем, существует ли форма выбора вариации. Если её нет,
    // обработчики событий не назначаются, чтобы избежать ошибок.
    if (!variationForm) {
        return;
    }

    if (!body.classList.contains(`product_cat-${slug}`)) return;

    // Поддержка тем, где поле количества создаётся динамически.
    // Поэтому не завершаем выполнение скрипта, если его нет в DOM прямо сейчас.
    // Метод ищет поле при каждом обращении, что соответствует принципу SRP:
    // функция занимается только поиском элемента в DOM.
    function getQtyInput() {
        return document.querySelector('input.qty');
    }

    // Фактический минимум, разрешённый в поле количества.
    // Значение изменяется функцией checkAndUpdateQuantity.
    let enforcedMin = 1;

    // Ссылка на кнопку "минус" для корректного управления обработчиками
    let boundMinusBtn = null;

    /**
     * Binds event listeners to the quantity input and minus button.
     *
     * SRP: отвечает только за присоединение обработчиков для поля
     * количества и кнопки уменьшения.
     */
    function attachQtyListeners() {
        const input = getQtyInput();
        if (input) {
            input.removeEventListener('input', handleQtyInput);
            input.addEventListener('input', handleQtyInput);
        }

        const minusBtn = document.querySelector('.quantity .minus');
        if (boundMinusBtn && boundMinusBtn !== minusBtn) {
            boundMinusBtn.removeEventListener('click', handleMinusClick);
        }
        if (minusBtn) {
            minusBtn.removeEventListener('click', handleMinusClick);
            minusBtn.addEventListener('click', handleMinusClick);
            boundMinusBtn = minusBtn;
        }
    }

    /**
     * Обработчик ввода в поле количества.
     * Следит за тем, чтобы значение не было меньше заданного минимума.
     */
    function handleQtyInput() {
        const input = getQtyInput();
        if (!input) return;
        if (parseInt(input.value || '0') < enforcedMin) {
            input.value = enforcedMin;
        }
        updateMinusButtonState();
    }

    /**
     * Обработчик клика на кнопку уменьшения количества.
     * После изменения значения проверяет соблюдение минимума.
     */
    function handleMinusClick() {
        setTimeout(() => {
            const input = getQtyInput();
            if (!input) return;
            if (parseInt(input.value || '0') < enforcedMin) {
                input.value = enforcedMin;
            }
            updateMinusButtonState();
        }, 100);
    }

    /**
     * Activates or disables the minus button based on current quantity.
     * Prevents decreasing below the enforced minimum value.
     */
    function updateMinusButtonState() {
        if (!boundMinusBtn) return;
        const input = getQtyInput();
        if (!input) return;
        const qty = parseInt(input.value || '0');
        if (qty <= enforcedMin) {
            boundMinusBtn.disabled = true;
            boundMinusBtn.classList.add('disabled');
            boundMinusBtn.setAttribute('aria-disabled', 'true');
        } else {
            boundMinusBtn.disabled = false;
            boundMinusBtn.classList.remove('disabled');
            boundMinusBtn.removeAttribute('aria-disabled');
        }
    }

    /**
     * Применяет минимальное значение к полю количества и уведомляет
     * другие скрипты об изменении через событие change.
     * SRP: только устанавливает значение и атрибуты поля.
     *
     * @param {number} value Минимально допустимое количество.
     */
    function applyQty(value) {
        const input = getQtyInput();
        if (!input) return;
        input.value = value;
        input.min = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Получает количество выбранной вариации в корзине и
     * устанавливает минимально допустимое значение в поле ввода.
     * SRP: вычисляет требуемый минимум и обновляет интерфейс.
     */
    function checkAndUpdateQuantity() {
        const variationInput = getVariationIdInput();
        const variationId = parseInt(variationInput?.value || '0');
        if (!variationId) {
            // Когда вариация не выбрана, применяем базовый минимум
            enforcedMin = min;
            applyQty(enforcedMin);
            attachQtyListeners();
            updateMinusButtonState();
            return;
        }

        const url = `${seedlingProductSettings.ajaxUrl}?action=seedling_get_cart_qty&variation_id=${variationId}&nonce=${seedlingProductSettings.nonce}`;
        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const alreadyInCart = data.data.quantity || 0;
                enforcedMin = Math.max(min - alreadyInCart, 1);

                applyQty(enforcedMin);

                // Подключаем обработчики после изменения количества
                // и обновляем состояние кнопки
                attachQtyListeners();
                updateMinusButtonState();
            });
    }

    // Пересчитываем минимальное значение при смене вариации.
    // События woocommerce_variation_select_change, found_variation и show_variation
    // являются пользовательскими и генерируются через jQuery, поэтому
    // обработчики подключаем также через jQuery.
    jQuery(function ($) {
        $(variationForm).on('woocommerce_variation_select_change', function () {
            // variation_id ещё может быть не установлен
            setTimeout(checkAndUpdateQuantity, 100);
        });

        // Проверяем количество сразу после выбора вариации WooCommerce
        $(variationForm).on('found_variation', function (event, variation) {
            const variationId = variation?.variation_id;

            if (!variationId) return;

            // WooCommerce может установить значение позже, поэтому подстрахуемся
            const variationInput = getVariationIdInput();
            if (variationInput) {
                variationInput.value = variationId;
            }
            setTimeout(checkAndUpdateQuantity, 0);
        });

        // Дополнительно реагируем на событие show_variation,
        // которое вызывается после полной инициализации вариации.
        $(variationForm).on('show_variation', function () {
            setTimeout(checkAndUpdateQuantity, 0);
        });
    });

    // Выполним проверку сразу при загрузке страницы,
    // если вариация выбрана по умолчанию. Используем задержку,
    // чтобы дождаться инициализации скриптов WooCommerce.
    setTimeout(checkAndUpdateQuantity, 0);
	
    // Отслеживаем процесс добавления товара в корзину,
    // чтобы при ошибке показать сообщение из сессии WooCommerce.
    // Используем jQuery для обработки уведомлений WooCommerce
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
