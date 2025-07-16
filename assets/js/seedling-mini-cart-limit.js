// Скрипт ограничивает уменьшение количества в мини-корзине.
// Управляет полем количества и кнопкой "минус" для товаров из целевой категории.
document.addEventListener('DOMContentLoaded', function () {
    const min = seedlingMiniCartSettings.minQty;
    const step = seedlingMiniCartSettings.step || 1;

    /**
     * Обновляет состояние конкретного элемента мини-корзины.
     * SRP: устанавливает минимальное значение и блокирует кнопку минус.
     *
     * @param {Element} item Элемент с классом seedling-category-item
     */
    function processItem(item) {
        const qtyInput = item.querySelector('input.qty');
        const minusBtn = item.querySelector('.quantity .minus');
        if (qtyInput) {
            qtyInput.step = step;
        }

        function enforce() {
            if (qtyInput) {
                let val = parseInt(qtyInput.value || '0');
                if (val < min) {
                    val = min;
                }
                if (val % step !== 0) {
                    val = Math.ceil(val / step) * step;
                }
                qtyInput.value = val;
                qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
                if (minusBtn) {
                    if (val - step < min) {
                        minusBtn.disabled = true;
                        minusBtn.classList.add('disabled');
                        minusBtn.setAttribute('aria-disabled', 'true');
                    } else {
                        minusBtn.disabled = false;
                        minusBtn.classList.remove('disabled');
                        minusBtn.removeAttribute('aria-disabled');
                    }
                }
            }
        }

        qtyInput?.addEventListener('input', enforce);
        // При клике на "минус" предотвращаем действие, если кнопка уже
        // заблокирована. Это гарантирует соблюдение минимального
        // количества товара. После изменения WooCommerce обновит DOM, поэтому
        // вызываем enforce с небольшой задержкой.
        minusBtn?.addEventListener('click', (e) => {
            if (minusBtn.disabled) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            setTimeout(enforce, 50);
        });
        enforce();
    }

    /**
     * Инициализирует обработку всех актуальных элементов мини-корзины.
     */
    function init() {
        document.querySelectorAll('.seedling-category-item').forEach(processItem);
    }

    init();
    // Повторная инициализация после обновления мини-корзины WooCommerce
    jQuery(document.body).on('wc_fragments_loaded wc_fragments_refreshed', init);
});
