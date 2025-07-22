// Скрипт ограничивает уменьшение количества в обычной корзине.
// Аналогичен mini-cart-limit.js, но работает на странице корзины.
document.addEventListener('DOMContentLoaded', function () {
    const rules = seedlingCartLimitSettings.rules || [];

    /**
     * Обновляет состояние конкретной строки корзины.
     * SRP: устанавливает минимальное значение и блокирует кнопку минус.
     *
     * @param {Element} item Элемент с классом seedling-category-item
     */
    function processItem(item) {
        const slugClass = Array.from(item.classList).find(c => c.startsWith('seedling-category-item-'));
        const rule = rules.find(r => slugClass && slugClass.endsWith(r.slug));
        if (!rule) return;
        const min = rule.minQty;
        const step = rule.step || 1;
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
        // При клике на "минус" проверяем, активна ли кнопка.
        // Принцип единственной ответственности соблюдается: функция
        // обрабатывает только логику ограничения ввода.
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
     * Инициализирует обработку всех элементов корзины.
     */
    function init() {
        document.querySelectorAll('.cart_item.seedling-category-item').forEach(processItem);
    }

    init();
    // Повторная инициализация после обновления корзины WooCommerce
    jQuery(document.body).on('updated_wc_div', init);
});
