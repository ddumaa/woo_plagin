// Скрипт ограничивает уменьшение количества в мини-корзине.
// Управляет полем количества и кнопкой "минус" для товаров из целевой категории.
document.addEventListener('DOMContentLoaded', function () {
    const min = seedlingMiniCartSettings.minQty;

    /**
     * Обновляет состояние конкретного элемента мини-корзины.
     * SRP: устанавливает минимальное значение и блокирует кнопку минус.
     *
     * @param {Element} item Элемент с классом seedling-category-item
     */
    function processItem(item) {
        const qtyInput = item.querySelector('input.qty');
        const minusBtn = item.querySelector('.quantity .minus');

        function enforce() {
            if (qtyInput) {
                let val = parseInt(qtyInput.value || '0');
                if (val < min) {
                    val = min;
                    qtyInput.value = val;
                    qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (minusBtn) {
                    if (val <= min) {
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
        minusBtn?.addEventListener('click', () => setTimeout(enforce, 50));
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
