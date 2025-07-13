<?php
/**
 * Plugin Name: WooCommerce Seedling Quantity Limiter
 * Description: Ограничения на количество товаров из категории: минимум на вариацию и общий минимум по категории.
 * Version: 1.4
 * Author: Дмитрий Анисимов
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class responsible for registering hooks and handling validation.
 */
class Seedling_Limiter
{
    /**
     * Nonce action used for AJAX security checks.
     */
    private const NONCE_ACTION = 'seedling-limiter';
    /**
     * Seedling_Limiter constructor.
     *
     * Single responsibility: подключить все хуки WordPress,
     * необходимые плагину. Служит точкой объединения остальных
     * методов класса.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        add_filter(
            'woocommerce_add_to_cart_validation',
            [$this, 'validate_add_to_cart'],
            10,
            5
        );

        add_action('woocommerce_checkout_process', [$this, 'validate_cart']);
        add_action('wp_ajax_seedling_validate_cart_full', [$this, 'validate_cart']);
        add_action('wp_ajax_nopriv_seedling_validate_cart_full', [$this, 'validate_cart']);

        add_action('wp_ajax_seedling_get_cart_qty', [$this, 'get_cart_qty']);
        add_action('wp_ajax_nopriv_seedling_get_cart_qty', [$this, 'get_cart_qty']);


        add_action('wp_enqueue_scripts', [$this, 'enqueue_product_script']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_script']);
    }

    /**
     * Adds plugin submenu under WooCommerce menu.
     *
     * SRP: создание страницы настроек и привязка к
     * методу render_settings_page().
     */
    public function add_admin_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            'Ограничения товаров',
            'Ограничения товаров',
            'manage_woocommerce',
            'woo-seedling-limit',
            [$this, 'render_settings_page']
        );
    }


    /**
     * Renders the settings page for plugin options.
     *
     * SRP: вывод формы настроек. Используется только
     * методом add_admin_menu().
     */
    public function render_settings_page(): void
    {
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

    /**
     * Registers all plugin settings displayed on the admin page.
     *
     * SRP: определяет опции плагина и их поля.
     * Взаимодействует с render_settings_page() для вывода формы.
     */
    public function register_settings(): void
    {
        register_setting(
            'woo_seedling_limit_settings',
            'woo_seedling_category_slug',
            ['sanitize_callback' => 'sanitize_text_field']
        );
        register_setting(
            'woo_seedling_limit_settings',
            'woo_seedling_min_variation',
            ['sanitize_callback' => 'absint']
        );
        register_setting(
            'woo_seedling_limit_settings',
            'woo_seedling_min_total',
            ['sanitize_callback' => 'absint']
        );
        register_setting(
            'woo_seedling_limit_settings',
            'woo_seedling_msg_variation',
            ['sanitize_callback' => [$this, 'sanitize_multiline_text']]
        );
        register_setting(
            'woo_seedling_limit_settings',
            'woo_seedling_msg_total',
            ['sanitize_callback' => [$this, 'sanitize_multiline_text']]
        );

        add_settings_section('woo_seedling_main', 'Основные настройки', null, 'woo-seedling-limit');

        add_settings_field(
            'woo_seedling_category_slug',
            'Слаг категории',
            function () {
                $value = get_option('woo_seedling_category_slug', 'seedling');
                echo "<input type='text' name='woo_seedling_category_slug' value='" . esc_attr($value) . "' />";
            },
            'woo-seedling-limit',
            'woo_seedling_main'
        );

        add_settings_field(
            'woo_seedling_min_variation',
            'Минимум на вариацию',
            function () {
                $value = get_option('woo_seedling_min_variation', 5);
                echo "<input type='number' name='woo_seedling_min_variation' value='" . esc_attr($value) . "' min='1' />";
            },
            'woo-seedling-limit',
            'woo_seedling_main'
        );

        add_settings_field(
            'woo_seedling_min_total',
            'Общий минимум по категории',
            function () {
                $value = get_option('woo_seedling_min_total', 20);
                echo "<input type='number' name='woo_seedling_min_total' value='" . esc_attr($value) . "' min='1' />";
            },
            'woo-seedling-limit',
            'woo_seedling_main'
        );

        add_settings_field(
            'woo_seedling_msg_variation',
            'Сообщение (для вариации < минимума)',
            function () {
                $value = get_option(
                    'woo_seedling_msg_variation',
                    'Минимальное количество для {name} ({attr}) — {min} шт. Сейчас — {current}.'
                );
                echo "<input type='text' name='woo_seedling_msg_variation' value='" . esc_attr($value) . "' style='width: 100%' />";
            },
            'woo-seedling-limit',
            'woo_seedling_main'
        );

        add_settings_field(
            'woo_seedling_msg_total',
            'Сообщение (общее < минимума)',
            function () {
                $value = get_option(
                    'woo_seedling_msg_total',
                    'Общее количество товаров из категории должно быть не менее {min}. Сейчас — {current}.'
                );
                echo "<input type='text' name='woo_seedling_msg_total' value='" . esc_attr($value) . "' style='width: 100%' />";
            },
            'woo-seedling-limit',
            'woo_seedling_main'
        );
    }

    /**
     * Sanitizes multiline text fields from the settings page.
     *
     * ISP: метод занимается только очисткой входящих данных,
     * что упрощает поддержку и тестирование кода.
     *
     * @param string $input Raw user input from textarea field.
     *
     * @return string Sanitized text safe for storing in the database.
     */
    private function sanitize_multiline_text(string $input): string
    {
        return sanitize_textarea_field($input);
    }

    /**
     * Формирует человекочитаемую строку атрибутов вариации.
     *
     * @param WC_Product_Variation $variation Вариация товара.
     *
     * @return string Список атрибутов вида "Цвет: Красный, Размер: M".
     */
    private function format_variation_attributes(WC_Product_Variation $variation): string
    {
        $out = [];

        foreach ($variation->get_attributes() as $taxonomy => $term_slug) {
            if (!$term_slug) {
                continue;
            }

            $label = wc_attribute_label($taxonomy);
            $term  = get_term_by('slug', $term_slug, $taxonomy);
            $value = $term ? $term->name : $term_slug;

            $out[] = sprintf('%s: %s', $label, $value);
        }

        return implode(', ', $out);
    }

    /**
     * Validates adding a product variation to the cart.
     *
     * SRP: проверяет минимальное количество для выбранной вариации.
     * Используется фильтром WooCommerce перед добавлением в корзину.
     *
     * @param bool     $passed       Whether the add to cart should proceed.
     * @param int      $product_id   ID of the product being added.
     * @param int      $quantity     Quantity requested by the customer.
     * @param int|null $variation_id ID of the variation being added.
     *
     * @return bool Whether the add to cart action is allowed.
     */
    public function validate_add_to_cart($passed, $product_id, $quantity, $variation_id = null)
    {
        $slug    = get_option('woo_seedling_category_slug', 'seedling');
        $min_qty = (int) get_option('woo_seedling_min_variation', 5);
        if (!$variation_id) {
            return $passed;
        }
        if (!has_term($slug, 'product_cat', $product_id)) {
            return $passed;
        }

        $current_qty = 0;
        // Собираем количество данной вариации уже присутствующее в корзине
        foreach (WC()->cart->get_cart() as $item) {
            if ($item['variation_id'] == $variation_id) {
                $current_qty += $item['quantity'];
            }
        }

        $new_total = $current_qty + $quantity;

        if ($new_total < $min_qty) {
            $variation = wc_get_product($variation_id);
            $name      = $variation ? $variation->get_name() : '';
            $attrs     = $variation instanceof WC_Product_Variation ? $this->format_variation_attributes($variation) : '';
            $template  = get_option(
                'woo_seedling_msg_variation',
                'Минимальное количество для {name} ({attr}) — {min} шт. Сейчас — {current}.'
            );
            $message = str_replace(
                ['{min}', '{name}', '{attr}', '{current}'],
                [$min_qty, $name, $attrs, $new_total],
                $template
            );
            wc_add_notice($message, 'error');
            return false;
        }

        return $passed;
    }

    /**
     * Validates the cart either during checkout or via AJAX call.
     *
     * SRP: проверяет корзину на соответствие установленным ограничениям.
     * Вызывается как действие WooCommerce и через AJAX.
     */
    public function validate_cart(): void
    {
        // When called via AJAX verify the nonce
        if (defined('DOING_AJAX') && DOING_AJAX) {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');
        }

        $slug      = get_option('woo_seedling_category_slug', 'seedling');
        $min_qty   = (int) get_option('woo_seedling_min_variation', 5);
        $min_total = (int) get_option('woo_seedling_min_total', 20);
        $msg_var   = get_option('woo_seedling_msg_variation');
        $msg_total = get_option('woo_seedling_msg_total');

        $variation_quantities = [];
        $total_in_category    = 0;
        $errors               = [];

        // Подсчитываем количество каждой вариации и общий объём в категории
        foreach (WC()->cart->get_cart() as $item) {
            $variation_id = $item['variation_id'];
            $parent_id    = $item['product_id'];
            if (!$variation_id || !has_term($slug, 'product_cat', $parent_id)) {
                continue;
            }

            $variation_quantities[$variation_id] = ($variation_quantities[$variation_id] ?? 0) + $item['quantity'];
            $total_in_category += $item['quantity'];
        }

        // Формируем сообщения об ошибках для вариаций
        foreach ($variation_quantities as $variation_id => $qty) {
            if ($qty < $min_qty) {
                $variation = wc_get_product($variation_id);
                $name      = $variation ? $variation->get_name() : '';
                $attrs     = $variation instanceof WC_Product_Variation ? $this->format_variation_attributes($variation) : '';
                $errors[]  = str_replace(
                    ['{min}', '{name}', '{attr}', '{current}'],
                    [$min_qty, $name, $attrs, $qty],
                    $msg_var
                );
            }
        }

        // Проверяем общий минимум по категории
        if ($total_in_category < $min_total) {
            $errors[] = str_replace(['{min}', '{current}'], [$min_total, $total_in_category], $msg_total);
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json([
                'valid'    => empty($errors),
                'messages' => $errors,
            ]);
        } else {
            foreach ($errors as $msg) {
                wc_add_notice($msg, 'error');
            }
        }
    }

    /**
     * Returns quantity of a variation currently in the cart.
     *
     * SRP: отдаёт количество конкретной вариации через AJAX.
     * Вызывается из скрипта seedling-product-limit.js.
     */
    public function get_cart_qty(): void
    {
        // Verify nonce to ensure the request is legitimate
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        // Obtain the variation ID; absint() ensures a positive integer value
        $variation_id = isset($_GET['variation_id']) ? absint($_GET['variation_id']) : 0;

        // Missing or invalid variation ID should return a clear error
        if ($variation_id === 0) {
            wp_send_json_error(['message' => 'Invalid or missing variation_id']);
        }

        $sum = 0;

        // Подсчитываем сумму по всем позициям корзины для этой вариации
        foreach (WC()->cart->get_cart() as $item) {
            if ((int) $item['variation_id'] === $variation_id) {
                $sum += (int) $item['quantity'];
            }
        }

        wp_send_json_success(['quantity' => $sum]);
    }

    /**
     * Enqueues script that limits quantity selection on product page.
     *
     * SRP: подключает seedling-product-limit.js и
     * передает ему настройки через wp_localize_script().
     */
    public function enqueue_product_script(): void
    {
        // Скрипт нужен только на страницах товара
        if (!is_product()) {
            return;
        }

        wp_enqueue_script(
            'seedling-product-limit',
            plugin_dir_url(__FILE__) . 'assets/js/seedling-product-limit.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script(
            'seedling-product-limit',
            'seedlingProductSettings',
            [
                'minQty' => (int) get_option('woo_seedling_min_variation', 5),
                'slug'   => get_option('woo_seedling_category_slug', 'seedling'),
                'nonce'  => wp_create_nonce(self::NONCE_ACTION),
            ]
        );
    }

    /**
     * Подключает скрипт проверки корзины.
     *
     * SRP: загружает seedling-cart-validation.js и
     * передает AJAX URL для проверки корзины.
     * Скрипт нужен не только на страницах "Корзина" и "Оформление заказа",
     * но и там, где выводится мини‑корзина.
     */
    public function enqueue_cart_script(): void
    {
        // Мини-корзина может быть выведена через виджет WooCommerce
        $has_mini_cart = is_active_widget(false, false, 'woocommerce_widget_cart', true);

        // Скрипт нужен только когда есть корзина или мини-корзина
        if (!is_cart() && !is_checkout() && !$has_mini_cart) {
            return;
        }

        wp_enqueue_script(
            'seedling-cart-validation',
            plugin_dir_url(__FILE__) . 'assets/js/seedling-cart-validation.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script(
            'seedling-cart-validation',
            'seedlingCartSettings',
            [
                'ajaxUrl' => admin_url('admin-ajax.php?action=seedling_validate_cart_full'),
                'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            ]
        );
    }
}

new Seedling_Limiter();

/**
 * Handles plugin activation by creating default options if they are missing.
 *
 * SRP: гарантирует наличие всех необходимых опций с адекватными значениями
 * по умолчанию и не затрагивает пользовательские настройки.
 */
function seedling_limiter_activate(): void
{
    $defaults = [
        'woo_seedling_category_slug' => 'seedling',
        'woo_seedling_min_variation' => 5,
        'woo_seedling_min_total'     => 20,
        'woo_seedling_msg_variation' => 'Минимальное количество для {name} ({attr}) — {min} шт. Сейчас — {current}.',
        'woo_seedling_msg_total'     => 'Общее количество товаров из категории должно быть не менее {min}. Сейчас — {current}.',
    ];

    foreach ($defaults as $option => $value) {
        // Создаём опцию только при её отсутствии
        if (get_option($option) === false) {
            add_option($option, $value);
        }
    }
}

// Регистрация хука активации плагина. Привязываем его к функции
// seedling_limiter_activate(), чтобы отделить инициализацию от остальных задач.
register_activation_hook(__FILE__, 'seedling_limiter_activate');
