# WooCommerce Seedling Quantity Limiter

WooCommerce Seedling Quantity Limiter enforces minimum order quantities for products from a specific category. The plugin is written with SOLID principles in mind and heavily commented to ease maintenance.

## Features
- Set a minimum quantity for each product variation.
- Require a minimum total quantity across a chosen category.
- Display customizable warning messages on product, cart and checkout pages.
- JavaScript integrations to enforce limits on product pages, the cart and mini cart.
- Admin settings page under **WooCommerce → Ограничения товаров**.

## Installation
1. Upload the plugin folder to `wp-content/plugins`.
2. Activate *WooCommerce Seedling Quantity Limiter* via the WordPress admin.
3. Configure options on the plugin settings page.

## Available Settings
- **Слаг категории** (`woo_seedling_category_slug`) – slug of the product category to monitor.
- **Минимум на вариацию** (`woo_seedling_min_variation`) – minimum quantity required per variation.
- **Общий минимум по категории** (`woo_seedling_min_total`) – total minimum across all variations in the category.
- **Сообщение для вариации** (`woo_seedling_msg_variation`) – template shown when a single variation is below the minimum.
- **Сообщение для категории** (`woo_seedling_msg_total`) – template for when the whole category total is below the limit.

## Development Notes
Source scripts live in `assets/js`. Minified files with the `.min.js` suffix are included for production. When changing a script, regenerate its minified counterpart using any preferred tool, for example:

```bash
npm install --global terser
terser assets/js/seedling-product-limit.js -o assets/js/seedling-product-limit.min.js -c -m
terser assets/js/seedling-cart-validation.js -o assets/js/seedling-cart-validation.min.js -c -m
```

Ensure minified files stay in sync with their sources before committing.
