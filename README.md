# WooCommerce Seedling Quantity Limiter

WooCommerce Seedling Quantity Limiter enforces minimum order quantities for products from selected categories. The plugin is written with SOLID principles in mind and heavily commented to ease maintenance.

## Features
- Set a minimum quantity for each product variation.
- Require a minimum total quantity across chosen categories.
- Display customizable warning messages on product, cart and checkout pages.
- JavaScript integrations to enforce limits on product pages, the cart and mini cart.
- Admin settings page under **WooCommerce → Ограничения товаров**.
- Configure a global quantity step that applies by default to all rules.

## Installation
1. Upload the plugin folder to `wp-content/plugins`.
2. Activate *WooCommerce Seedling Quantity Limiter* via the WordPress admin.
3. Configure options on the plugin settings page.

## Available Settings
- **Правила** (`woo_seedling_rules`) – array of rule blocks. Each rule contains:
  - `slug` – product category slug to monitor.
  - `min_variation` – minimum quantity per variation.
  - `min_total` – overall minimum within the category.
  - `msg_variation` – message shown when a variation is below the minimum.
  - `msg_total` – message shown when the category total is too low.
  - `step` – quantity step increment.
  - **Шаг по умолчанию** (`woo_seedling_step`) – default increment for new rules.

## Development Notes
Source scripts live in `assets/js`. Minified files with the `.min.js` suffix are included for production. When changing a script, regenerate its minified counterpart using any preferred tool, for example:

```bash
npm install --global terser
terser assets/js/seedling-product-limit.js -o assets/js/seedling-product-limit.min.js -c -m
terser assets/js/seedling-cart-validation.js -o assets/js/seedling-cart-validation.min.js -c -m
```

Ensure minified files stay in sync with their sources before committing.
