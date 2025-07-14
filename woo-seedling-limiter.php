<?php
/**
 * Plugin Name: WooCommerce Seedling Quantity Limiter
 * Description: Ограничения на количество товаров из категории: минимум на вариацию и общий минимум по категории.
 * Version: 1.7
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
    public const NONCE_ACTION = 'seedling-limiter';
    /**
     * Шаблон уведомления по умолчанию для одной вариации.
     */
    public const DEFAULT_MSG_VARIATION = 'Минимальное количество для {name} ({attr}) — {min} шт. Сейчас — {current}.';
    /**
     * Шаблон уведомления по умолчанию для всей категории.
     */
    public const DEFAULT_MSG_TOTAL = 'Общее количество товаров из категории {category} должно быть не менее {min}. Сейчас — {current}.';

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
        add_action('wp_ajax_seedling_cart_validation', [$this, 'validate_cart']);
        add_action('wp_ajax_nopriv_seedling_cart_validation', [$this, 'validate_cart']);

        add_action('wp_ajax_seedling_get_cart_qty', [$this, 'get_cart_qty']);
        add_action('wp_ajax_nopriv_seedling_get_cart_qty', [$this, 'get_cart_qty']);
        add_filter('woocommerce_quantity_input_args', [$this, 'update_quantity_args'], 10, 2);
        add_filter('woocommerce_available_variation', [$this, 'update_available_variation'], 10, 3);


        add_action('wp_enqueue_scripts', [$this, 'enqueue_product_script']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_script']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_mini_cart_script']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_limit_script']);

        add_filter('woocommerce_cart_item_class', [$this, 'mark_cart_item'], 10, 3);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'enforce_cart_item_min'], 10, 4);
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
                $value       = get_option('woo_seedling_msg_variation', '');
                $placeholder = self::DEFAULT_MSG_VARIATION;
                echo "<input type='text' name='woo_seedling_msg_variation' value='" . esc_attr($value) . "' placeholder='" . esc_attr($placeholder) . "' style='width: 100%' />";
            },
            'woo-seedling-limit',
            'woo_seedling_main'
        );

        add_settings_field(
            'woo_seedling_msg_total',
            'Сообщение (общее < минимума)',
            function () {
                $value       = get_option('woo_seedling_msg_total', '');
                $slug        = get_option('woo_seedling_category_slug', 'seedling');
                $category    = $this->get_category_name($slug);
                $placeholder = str_replace('{category}', $category, self::DEFAULT_MSG_TOTAL);
                echo "<input type='text' name='woo_seedling_msg_total' value='" . esc_attr($value) . "' placeholder='" . esc_attr($placeholder) . "' style='width: 100%' />";
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
     * Сделан публичным для возможности повторного использования вне класса.
     *
     * @param string $input Raw user input from textarea field.
     *
     * @return string Sanitized text safe for storing in the database.
     */
    public function sanitize_multiline_text(string $input): string
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
     * Returns the notification template for a single variation.
     *
     * @return string Template string with placeholders.
     */
    private function get_variation_template(): string
    {
        $msg = get_option('woo_seedling_msg_variation');
        if (trim((string) $msg) === '') {
            $msg = self::DEFAULT_MSG_VARIATION;
        }

        return $msg;
    }

    /**
     * Returns the notification template for the category total.
     *
     * @return string Template string with placeholders including {category}.
     */
    private function get_total_template(): string
    {
        $msg = get_option('woo_seedling_msg_total');
        if (trim((string) $msg) === '') {
            $msg = self::DEFAULT_MSG_TOTAL;
        }

        return $msg;
    }

    /**
     * Returns the human readable category name from its slug.
     *
     * @param string $slug Category slug stored in plugin settings.
     *
     * @return string Category name or slug if term not found.
     */
    private function get_category_name(string $slug): string
    {
        $term = get_term_by('slug', $slug, 'product_cat');

        return $term instanceof WP_Term ? $term->name : $slug;
    }

    /**
     * Возвращает текущее количество выбранной вариации в корзине.
     *
     * SRP: подсчитывает только количество и ничем более.
     * Используется другими методами для расчёта ограничений.
     *
     * @param int $variation_id ID вариации товара.
     *
     * @return int Сумма количества в корзине.
     */
    private function get_variation_cart_quantity(int $variation_id): int
    {
        $qty = 0;
        foreach (WC()->cart->get_cart() as $item) {
            if ((int) $item['variation_id'] === $variation_id) {
                $qty += (int) $item['quantity'];
            }
        }

        return $qty;
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
            $template  = $this->get_variation_template();
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
        // Если это любой другой AJAX-запрос, не связанный с нашим плагином,
        // выходим раньше времени, чтобы не мешать WooCommerce.
        if (wp_doing_ajax() && (($_REQUEST['action'] ?? '') !== 'seedling_cart_validation')) {
            return;
        }

        // Determine whether the call comes from our AJAX handler
        // to avoid interrupting other WooCommerce AJAX actions.
        $is_plugin_ajax = wp_doing_ajax() &&
            (($_REQUEST['action'] ?? '') === 'seedling_cart_validation');

        // When called via our AJAX action verify the nonce for security.
        if ($is_plugin_ajax) {
            if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid security token'], 403);
            }
        }

        $slug      = get_option('woo_seedling_category_slug', 'seedling');
        $min_qty   = (int) get_option('woo_seedling_min_variation', 5);
        $min_total = (int) get_option('woo_seedling_min_total', 20);
        $msg_var   = $this->get_variation_template();
        $msg_total = $this->get_total_template();
        $category  = $this->get_category_name($slug);

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

        // Прерываемся, если подходящих товаров в корзине нет
        if ($total_in_category === 0) {
            if ($is_plugin_ajax) {
                // Для AJAX-сценария сразу возвращаем успешный ответ
                wp_send_json(['valid' => true, 'messages' => []]);
            }

            return;
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
            $errors[] = str_replace(
                ['{min}', '{current}', '{category}'],
                [$min_total, $total_in_category, $category],
                $msg_total
            );
        }

        if ($is_plugin_ajax) {
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
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid security token'], 403);
        }

        // Obtain the variation ID; absint() ensures a positive integer value
        $variation_id = isset($_GET['variation_id']) ? absint($_GET['variation_id']) : 0;

        // Missing or invalid variation ID should return a clear error
        if ($variation_id === 0) {
            wp_send_json_error(['message' => 'Invalid or missing variation_id']);
        }

        $sum = $this->get_variation_cart_quantity($variation_id);

        wp_send_json_success(['quantity' => $sum]);
    }

    /**
     * Модифицирует параметры поля количества на странице товара.
     *
     * SRP: вычисляет минимально допустимое значение без влияния
     * на вывод интерфейса.
     */
    public function update_quantity_args(array $args, WC_Product $product): array
    {
        $slug = get_option('woo_seedling_category_slug', 'seedling');
        $min  = (int) get_option('woo_seedling_min_variation', 5);

        $parent_id = $product instanceof WC_Product_Variation
            ? $product->get_parent_id()
            : $product->get_id();

        if (!has_term($slug, 'product_cat', $parent_id)) {
            return $args;
        }

        $variation_id = $product instanceof WC_Product_Variation ? $product->get_id() : 0;
        $current      = $variation_id ? $this->get_variation_cart_quantity($variation_id) : 0;
        $minimum      = max($min - $current, 1);

        if ($minimum > 1) {
            $args['min_value']   = $minimum;
            $args['input_value'] = $minimum;
        }

        return $args;
    }

    /**
     * Добавляет минимальное количество в массив данных вариации.
     *
     * SRP: подготавливает значения для JavaScript, не меняя логику WooCommerce.
     *
     * @param array                 $data      Данные вариации, которые будет получать скрипт.
     * @param WC_Product            $parent    Родительский товар‑переменный продукт.
     * @param WC_Product_Variation  $variation Объект конкретной вариации.
     *
     * @return array Обновлённый массив данных для вывода на фронтенде.
     */
    public function update_available_variation(array $data, WC_Product $parent, WC_Product_Variation $variation): array
    {
        $slug = get_option('woo_seedling_category_slug', 'seedling');
        if (!has_term($slug, 'product_cat', $parent->get_id())) {
            return $data;
        }

        $min     = (int) get_option('woo_seedling_min_variation', 5);
        $current = $this->get_variation_cart_quantity($variation->get_id());
        $minimum = max($min - $current, 1);

        if ($minimum > 1) {
            $data['min_qty']    = $minimum;
            $data['input_value'] = $minimum;
        }

        return $data;
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
                'minQty'  => (int) get_option('woo_seedling_min_variation', 5),
                'slug'    => get_option('woo_seedling_category_slug', 'seedling'),
                'nonce'   => wp_create_nonce(self::NONCE_ACTION),
                'ajaxUrl' => admin_url('admin-ajax.php'),
            ]
        );
    }

    /**
     * Checks whether the mini cart should be considered active.
     *
     * SRP: определяет наличие мини-корзины на сайте.
     * Метод проверяет стандартный виджет WooCommerce и DOM‑хуки популярных тем
     * (например, `mfn-ch-footer-buttons`). Позволяет расширять логику через
     * фильтр 'seedling_limiter_has_mini_cart'.
     */
    private function has_mini_cart(): bool
    {
        // Проверяем стандартный виджет WooCommerce.
        $active_widget = is_active_widget(false, false, 'woocommerce_widget_cart', true);

        // DOM-хуки популярных тем для вывода мини‑корзины.
        $theme_hooks = ['mfn-ch-footer-buttons'];
        $hook_found  = false;

        foreach ($theme_hooks as $hook) {
            if (has_action($hook)) {
                $hook_found = true;
                break;
            }
        }

        $detected = $active_widget || $hook_found;

        return (bool) apply_filters('seedling_limiter_has_mini_cart', $detected);
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
        if (!is_cart() && !is_checkout() && !$this->has_mini_cart()) {
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
                'ajaxUrl' => admin_url('admin-ajax.php?action=seedling_cart_validation'),
                'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            ]
        );
    }

    /**
     * Подключает скрипт ограничения количества в мини‑корзине.
     *
     * SRP: загружает seedling-mini-cart-limit.js и передает настройки.
     */
    public function enqueue_mini_cart_script(): void
    {
        if (!$this->has_mini_cart()) {
            return;
        }

        wp_enqueue_script(
            'seedling-mini-cart-limit',
            plugin_dir_url(__FILE__) . 'assets/js/seedling-mini-cart-limit.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script(
            'seedling-mini-cart-limit',
            'seedlingMiniCartSettings',
            [
                'minQty' => (int) get_option('woo_seedling_min_variation', 5),
                'slug'   => get_option('woo_seedling_category_slug', 'seedling'),
            ]
        );
    }

    /**
     * Подключает скрипт ограничения количества на странице корзины.
     *
     * SRP: загружает seedling-cart-limit.js только на странице корзины.
     */
    public function enqueue_cart_limit_script(): void
    {
        if (!is_cart()) {
            return;
        }

        wp_enqueue_script(
            'seedling-cart-limit',
            plugin_dir_url(__FILE__) . 'assets/js/seedling-cart-limit.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script(
            'seedling-cart-limit',
            'seedlingCartLimitSettings',
            [
                'minQty' => (int) get_option('woo_seedling_min_variation', 5),
            ]
        );
    }
    /**
     * Отмечает элементы корзины специальным классом при совпадении категории.
     *
     * SRP: только добавляет CSS‑класс без изменения других данных.
     *
     * @param string $classes      Строка с CSS‑классами элемента.
     * @param array  $cart_item    Данные товара из корзины.
     * @param string $cart_item_key Ключ текущего товара в корзине.
     *
     * @return string Обновлённая строка классов элемента корзины.
     */
    public function mark_cart_item(string $classes, array $cart_item, string $cart_item_key): string
    {
        $slug = get_option('woo_seedling_category_slug', 'seedling');

        if (has_term($slug, 'product_cat', $cart_item['product_id'])) {
            $classes = "$classes seedling-category-item";
        }

        return $classes;
    }

    /**
     * Принудительно устанавливает минимальное количество при обновлении.
     *
     * SRP: проверяет количество и корректирует его через WC_Cart.
     */
    public function enforce_cart_item_min(string $cart_item_key, int $quantity, int $old_quantity, WC_Cart $cart): void
    {
        $slug = get_option('woo_seedling_category_slug', 'seedling');
        $min  = (int) get_option('woo_seedling_min_variation', 5);

        $item = $cart->cart_contents[$cart_item_key] ?? null;
        if (!$item || !has_term($slug, 'product_cat', $item['product_id'])) {
            return;
        }

        if ($quantity < $min) {
            $cart->set_quantity($cart_item_key, $min);

            $variation = $item['variation_id'] ? wc_get_product($item['variation_id']) : null;
            $name      = $variation ? $variation->get_name() : '';
            $attrs     = $variation instanceof WC_Product_Variation ? $this->format_variation_attributes($variation) : '';
            $message   = str_replace(
                ['{min}', '{name}', '{attr}', '{current}'],
                [$min, $name, $attrs, $quantity],
                $this->get_variation_template()
            );

            wc_add_notice($message, 'error');
        }
    }
}


/**
 * Initializes the plugin only after all plugins are loaded.
 *
 * SRP: проверяет наличие WooCommerce и создаёт экземпляр
 * Seedling_Limiter только при активном WooCommerce. Это
 * предотвращает фатальные ошибки при активации плагина.
 */
add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        // Показываем уведомление в админке, если WooCommerce не активен
        add_action('admin_notices', static function (): void {
            echo '<div class="error"><p>'
                . esc_html__('WooCommerce Seedling Quantity Limiter requires WooCommerce to be installed and active.', 'woo-seedling-limiter')
                . '</p></div>';
        });

        return;
    }

    new Seedling_Limiter();
});

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
        'woo_seedling_msg_variation' => Seedling_Limiter::DEFAULT_MSG_VARIATION,
        'woo_seedling_msg_total'     => Seedling_Limiter::DEFAULT_MSG_TOTAL,
    ];

    foreach ($defaults as $option => $value) {
        if (get_option($option) === false) {
            add_option($option, $value);
        }
    }
}

/**
 * Handles plugin uninstallation by removing plugin options.
 *
 * SRP: удаляет все настройки плагина, чтобы не оставлять данные в базе.
 */
function seedling_limiter_uninstall(): void
{
    $options = [
        'woo_seedling_category_slug',
        'woo_seedling_min_variation',
        'woo_seedling_min_total',
        'woo_seedling_msg_variation',
        'woo_seedling_msg_total',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }
}


// Регистрация хука активации плагина. Привязываем его к функции
// seedling_limiter_activate(), чтобы WordPress сделал необходимые действия
// сразу после активации плагина.
register_activation_hook(__FILE__, 'seedling_limiter_activate');

// Регистрация хука удаления плагина. Привязываем его к функции
// seedling_limiter_uninstall(), чтобы при удалении опции были очищены.
register_uninstall_hook(__FILE__, 'seedling_limiter_uninstall');