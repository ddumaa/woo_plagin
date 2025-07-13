// Скрипт проверяет корзину на соответствие минимальным ограничениям.
document.addEventListener('DOMContentLoaded', function () {
    // URL-адрес для проверки корзины через AJAX.
    // Сервер возвращает список сообщений и признак валидности.
    const ajaxUrl = seedlingCartSettings.ajaxUrl;
    // Кнопки оформления заказа, которые нужно блокировать при ошибках
    const selectors = ['.checkout-button', '#place_order', 'a.checkout'];
    // ID контейнера для вывода сообщений об ошибках
    const noticeId = 'seedling-dynamic-warning';

    // Отображает список сообщений об ошибках в специальном блоке
    function showMessages(msgs) {
		let box = document.getElementById(noticeId);
		if (!box) {
			box = document.createElement('ul');
			box.id = noticeId;
			box.className = 'woocommerce-error';

                        // Список контейнеров, куда можно поместить сообщения.
                        // Используется первый найденный элемент.
                        const insertPlaces = [
                                document.querySelector(".mfn-ch-content"),                    // контент темы
                                document.querySelector(".woocommerce-notices-wrapper"),        // основное место
                                document.querySelector(".woocommerce-cart-form"),              // корзина
                                document.querySelector(".woocommerce-checkout-review-order"),  // оформление заказа
                                document.querySelector(".woocommerce-mini-cart"),              // модальная корзина
                                document.querySelector("body")               // запасной вариант
                        ];

			for (const el of insertPlaces) {
				if (el) {
					el.prepend(box);
					break;
				}
			}
		}

		box.innerHTML = msgs.map(m => `<li>${m}</li>`).join('');
	}


    // Активирует или блокирует кнопки оформления заказа
    function disableBtns(disable) {
		selectors.forEach(sel => {
			document.querySelectorAll(sel).forEach(btn => {
				if (disable) {
					btn.classList.add('disabled');
					btn.setAttribute('aria-disabled', 'true');

					// Блокируем клик по ссылке
					btn.addEventListener('click', preventIfDisabled);
				} else {
					btn.classList.remove('disabled');
					btn.removeAttribute('aria-disabled');

					// Удаляем обработчик клика
					btn.removeEventListener('click', preventIfDisabled);
				}
			});
		});
	}

        // Предотвращает действие по клику, если элемент заблокирован
        function preventIfDisabled(e) {
		const el = e.currentTarget;
		if (el.classList.contains('disabled') || el.getAttribute('aria-disabled') === 'true') {
			e.preventDefault();
			e.stopPropagation();
		}
	}

    // Проверяет корзину через AJAX и отображает найденные ошибки
    function checkCart() {
        fetch(ajaxUrl + `&nonce=${seedlingCartSettings.nonce}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                const valid = d.valid;
                const messages = d.messages || [];

                if (valid) {
                    document.getElementById(noticeId)?.remove();
                    disableBtns(false);
                } else {
                    showMessages(messages);
                    disableBtns(true);
                }
            });
    }

    // Проверяем корзину сразу и при любых изменениях DOM
    checkCart();
    const mo = new MutationObserver(checkCart);
    mo.observe(document.body, { childList: true, subtree: true });

    // Проверяем корзину после обновления фрагментов WooCommerce
    jQuery(document.body).on('wc_fragments_refreshed updated_wc_div', checkCart);
});
