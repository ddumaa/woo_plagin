// Скрипт проверяет корзину на соответствие минимальным ограничениям.
document.addEventListener('DOMContentLoaded', function () {
    // URL-адрес для проверки корзины через AJAX
    const ajaxUrl = seedlingCartSettings.ajaxUrl;
    // URL-адрес для получения общего количества по категории
    const totalUrl = seedlingCartSettings.totalCheckUrl;
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

			const insertPlaces = [
				document.querySelector('.woocommerce-notices-wrapper'),        // основное место
				document.querySelector('.woocommerce-cart-form'),              // корзина
				document.querySelector('.woocommerce-checkout-review-order'),  // оформление
				document.querySelector('.woocommerce-mini-cart'),              // модальная
				document.querySelector('body')                                 // fallback
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
        fetch(ajaxUrl, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                let valid = d.valid;
                let messages = d.messages || [];

                fetch(totalUrl, { credentials: 'same-origin' })
                    .then(r2 => r2.json())
                    .then(catData => {
                        if (!catData.valid) {
                            valid = false;
                            const msg = `Общее количество товаров из категории должно быть не менее ${catData.min}. Сейчас — ${catData.total}.`;
                            messages.push(msg);
                        }

                        if (valid) {
                            document.getElementById(noticeId)?.remove();
                            disableBtns(false);
                        } else {
                            showMessages(messages);
                            disableBtns(true);
                        }
                    });
            });
    }

    // Проверяем корзину сразу и при любых изменениях DOM
    checkCart();
    const mo = new MutationObserver(checkCart);
    mo.observe(document.body, { childList: true, subtree: true });

    // Проверяем корзину после обновления фрагментов WooCommerce
    jQuery(document.body).on('wc_fragments_refreshed updated_wc_div', checkCart);
});
