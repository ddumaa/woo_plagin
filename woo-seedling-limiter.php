<?php
/**
 * Plugin Name: WooCommerce Seedling Quantity Limiter
 * Description: Ограничения на количество товаров из категории: минимум на вариацию и общий минимум по категории.
 * Version: 1.3
 * Author: Дмитрий Анисимов
 */

if (!defined('ABSPATH')) exit;

// === Настройки в админке ===
add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Ограничения товаров',
        'Ограничения товаров',
        'manage_woocommerce',
        'woo-seedling-limit',
        'render_seedling_limit_settings_page'
    );
});

function render_seedling_limit_settings_page() {
    ?>
    <div class="wrap">
        <h1>Ограничения категории</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('woo_seedling_limit_settings');
            do_settings_sections('woo-seedling-limit');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('woo_seedling_limit_settings', 'woo_seedling_category_slug');
    register_setting('woo_seedling_limit_settings', 'woo_seedling_min_variation');
    register_setting('woo_seedling_limit_settings', 'woo_seedling_min_total');
    register_setting('woo_seedling_limit_settings', 'woo_seedling_msg_variation');
    register_setting('woo_seedling_limit_settings', 'woo_seedling_msg_total');

    add_settings_section('woo_seedling_main', 'Основные настройки', null, 'woo-seedling-limit');

    add_settings_field('woo_seedling_category_slug', 'Слаг категории', function () {
        $value = get_option('woo_seedling_category_slug', 'seedling');
        echo "<input type='text' name='woo_seedling_category_slug' value='" . esc_attr($value) . "' />";
    }, 'woo-seedling-limit', 'woo_seedling_main');

    add_settings_field('woo_seedling_min_variation', 'Минимум на вариацию', function () {
        $value = get_option('woo_seedling_min_variation', 5);
        echo "<input type='number' name='woo_seedling_min_variation' value='" . esc_attr($value) . "' min='1' />";
    }, 'woo-seedling-limit', 'woo_seedling_main');

    add_settings_field('woo_seedling_min_total', 'Общий минимум по категории', function () {
        $value = get_option('woo_seedling_min_total', 20);
        echo "<input type='number' name='woo_seedling_min_total' value='" . esc_attr($value) . "' min='1' />";
    }, 'woo-seedling-limit', 'woo_seedling_main');

    add_settings_field('woo_seedling_msg_variation', 'Сообщение (для вариации < минимума)', function () {
        $value = get_option('woo_seedling_msg_variation', 'Минимальное количество для этой вариации — {min} шт. Сейчас — {current}.');
        echo "<input type='text' name='woo_seedling_msg_variation' value='" . esc_attr($value) . "' style='width: 100%' />";
    }, 'woo-seedling-limit', 'woo_seedling_main');

    add_settings_field('woo_seedling_msg_total', 'Сообщение (общее < минимума)', function () {
        $value = get_option('woo_seedling_msg_total', 'Общее количество товаров из категории должно быть не менее {min}. Сейчас — {current}.');
        echo "<input type='text' name='woo_seedling_msg_total' value='" . esc_attr($value) . "' style='width: 100%' />";
    }, 'woo-seedling-limit', 'woo_seedling_main');
});

// === Серверная проверка ===
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity, $variation_id = null) {
    $slug = get_option('woo_seedling_category_slug', 'seedling');
    $min_qty = (int)get_option('woo_seedling_min_variation', 5);
    if (!$variation_id) return $passed;
    if (!has_term($slug, 'product_cat', $product_id)) return $passed;

    $current_qty = 0;
    foreach (WC()->cart->get_cart() as $item) {
        if ($item['variation_id'] == $variation_id) {
            $current_qty += $item['quantity'];
        }
    }

    if (($current_qty + $quantity) < $min_qty) {
        wc_add_notice("Минимальное количество для этой вариации — {$min_qty} шт.", 'error');
        return false;
    }

    return $passed;
}, 10, 5);

// === Проверка при оформлении ===
add_action('woocommerce_checkout_process', 'seedling_validate_cart');
add_action('wp_ajax_seedling_validate_cart_full', 'seedling_validate_cart');
add_action('wp_ajax_nopriv_seedling_validate_cart_full', 'seedling_validate_cart');

function seedling_validate_cart() {
    $slug = get_option('woo_seedling_category_slug', 'seedling');
    $min_qty = (int)get_option('woo_seedling_min_variation', 5);
    $min_total = (int)get_option('woo_seedling_min_total', 20);
    $msg_var = get_option('woo_seedling_msg_variation');
    $msg_total = get_option('woo_seedling_msg_total');

    $variation_quantities = [];
    $total_in_category = 0;
    $errors = [];

    foreach (WC()->cart->get_cart() as $item) {
        $variation_id = $item['variation_id'];
        $parent_id = $item['product_id'];
        if (!$variation_id || !has_term($slug, 'product_cat', $parent_id)) continue;

        $variation_quantities[$variation_id] = ($variation_quantities[$variation_id] ?? 0) + $item['quantity'];
        $total_in_category += $item['quantity'];
    }

    foreach ($variation_quantities as $variation_id => $qty) {
        if ($qty < $min_qty) {
            $name = wc_get_product($variation_id)->get_name();
            $errors[] = str_replace(['{min}', '{name}', '{current}'], [$min_qty, $name, $qty], $msg_var);
        }
    }

    if ($total_in_category < $min_total) {
        $errors[] = str_replace(['{min}', '{current}'], [$min_total, $total_in_category], $msg_total);
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {
        wp_send_json([
            'valid' => empty($errors),
            'messages' => $errors,
        ]);
    } else {
        foreach ($errors as $msg) {
            wc_add_notice($msg, 'error');
        }
    }
}

/**
 * Handle AJAX request to get quantity of a variation already in the cart.
 *
 * Summarizes the quantity of products with a given variation ID and returns
 * the amount in a JSON response. This allows the frontend to adjust the
 * quantity input according to the current cart state.
 */
function seedling_get_cart_qty() {
    if (!isset($_GET['variation_id'])) {
        wp_send_json_error(['message' => 'Missing variation_id']);
    }

    $variation_id = (int)$_GET['variation_id'];
    $sum = 0;

    foreach (WC()->cart->get_cart() as $item) {
        if ((int)$item['variation_id'] === $variation_id) {
            $sum += (int)$item['quantity'];
        }
    }

    wp_send_json_success(['quantity' => $sum]);
}

add_action('wp_ajax_seedling_get_cart_qty', 'seedling_get_cart_qty');
add_action('wp_ajax_nopriv_seedling_get_cart_qty', 'seedling_get_cart_qty');

// === JS-проверка с AJAX ===
add_action('wp_footer', function () {
    if (!is_cart() && !is_checkout()) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ajaxUrl = '<?= admin_url('admin-ajax.php?action=seedling_validate_cart_full') ?>';
        const selectors = ['.checkout-button', '#place_order'];
        const noticeId = 'seedling-dynamic-warning';

        function showMessages(msgs) {
            let box = document.getElementById(noticeId);
            if (!box) {
                box = document.createElement('div');
                box.id = noticeId;
                box.className = 'woocommerce-error';
                const c = document.querySelector('.cart_totals, form.checkout, body');
                if (c) c.prepend(box);
            }
            box.innerHTML = msgs.map(m => `<li>${m}</li>`).join('');
        }

        function disableBtns(disable) {
            selectors.forEach(sel => {
                document.querySelectorAll(sel).forEach(btn => {
                    if (disable) {
                        btn.setAttribute('disabled', 'disabled');
                        btn.classList.add('disabled');
                    } else {
                        btn.removeAttribute('disabled');
                        btn.classList.remove('disabled');
                    }
                });
            });
        }

        function checkCart() {
            fetch(ajaxUrl, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(d => {
                    if (d.valid) {
                        document.getElementById(noticeId)?.remove();
                        disableBtns(false);
                    } else {
                        showMessages(d.messages);
                        disableBtns(true);
                    }
                });
        }

        checkCart();
        const mo = new MutationObserver(checkCart);
        mo.observe(document.body, { childList: true, subtree: true });
    });
    </script>
    <?php
});

add_action('wp_enqueue_scripts', function () {
    if (!is_product()) return;

    wp_enqueue_script('seedling-product-limit', plugin_dir_url(__FILE__) . 'assets/js/seedling-product-limit.js', [], null, true);

    wp_localize_script('seedling-product-limit', 'seedlingSettings', [
        'minQty' => (int)get_option('woo_seedling_min_variation', 5),
        'slug' => get_option('woo_seedling_category_slug', 'seedling'),
    ]);
});