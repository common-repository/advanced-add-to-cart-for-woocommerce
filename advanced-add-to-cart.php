<?php

/*
Plugin Name:  Advanced Add To Cart for WooCommerce
Description:  Extended functionality for WooCommerce simple products
Version:      1.0.0
Author:       OnePix
Author URI:   https://onepix.net
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  aatc
*/


class WC_Advanced_Add_To_Cart
{

    protected static $plugin_url;
    protected static $plugin_path;
    public $version;
    public $domain;
    public $settings;
    public static $icon;
    public static $small_icon;

    public function __construct()
    {
        self::$plugin_url = plugin_dir_url(__FILE__);
        self::$plugin_path = plugin_dir_path(__FILE__);

        $this->version = time();

        add_filter('woocommerce_add_to_cart_fragments', array($this, 'add_cart_counter'), 10, 1);
        add_filter('woocommerce_loop_add_to_cart_link', array($this, 'aatc_change_link'), 10, 3);
        add_action('wp_enqueue_scripts', array($this, 'aatc_scripts'), 10);
        add_action('wp_ajax_nopriv_aatc_add_to_cart_quantity', array($this, 'aatc_add_to_cart_quantity_handler'), 10);
        add_action('wp_ajax_aatc_add_to_cart_quantity', array($this, 'aatc_add_to_cart_quantity_handler'), 10);
        add_action('woocommerce_simple_add_to_cart', array($this, 'simple_product_cart'), 10);
    }

    public function simple_product_cart()
    {
        global $product;
        ob_start();

        woocommerce_template_loop_add_to_cart(
            array(
                'quantity' => 1,
            )
        );
        $link = ob_get_clean();
        echo $this->aatc_change_link($link, $product, []);
    }

    public function aatc_scripts()
    {
        wp_enqueue_style(
            'aatc',
            self::$plugin_url . 'assets/css/aatc.css',
            [],
            filemtime(self::$plugin_path . 'assets/css/aatc.css')
        );

        wp_enqueue_script(
            'aatc',
            self::$plugin_url . 'assets/js/aatc.js',
            ['jquery'],
            filemtime(self::$plugin_path . 'assets/js/aatc.js'),
            false
        );
        wp_localize_script('aatc', 'aatc', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function add_cart_counter($content)
    {
        $input_quantity = !empty($_POST['quantity']) ? intval($_POST['quantity']) : false;
        $input_product = !empty($_POST['product_id']) ? intval($_POST['product_id']) : false;
        if ($input_quantity == 0) {
            unset($GLOBALS['product']);
            $GLOBALS['product'] = wc_get_product($input_product);
            ob_start();

            woocommerce_template_loop_add_to_cart(
                array(
                    'quantity' => 1,
                )
            );
            $data = ob_get_clean();
            $content['newdata'] = $data;
        } else {
            $key = '';
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if ($input_product == $cart_item['product_id']) {
                    $key = $cart_item_key;
                }
            }

            $content['newdata'] = $this->get_counter($input_product, $key, $input_quantity, 'advanced-ajax');
        }

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $dom = 'a.ajax_add_to_cart[data-product_id="'.$cart_item['product_id'].'"]';
            $content[$dom] = $this->get_counter($cart_item['product_id'], $cart_item_key, $cart_item['quantity'], 'advanced-ajax');
        }
        return $content;
    }

    function aatc_change_link($link, $product, $args)
    {
        if (!is_admin() && $product->is_type('simple')) {
            if (!WC()->cart->is_empty()) {
                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                    if ($product->get_id() == $cart_item['product_id']) {
                        $quantity = $cart_item['quantity'];
                        $link = $this->get_counter($cart_item['product_id'], $cart_item_key, $quantity);
                    }
                }
            }
        }


        return $link;
    }

    public function get_counter($product_id, $cart_item_key, $quantity, $class = '')
    {
        return '<div class="custom-counter ' . $class . '">
            <button class="qty-minus"><svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 4H1a1 1 0 0 0 0 2h8a1 1 0 1 0 0-2Z" fill="currentColor"/></svg></button>
            <input type="number" data-product_id="' . $product_id . '" data-key="' . $cart_item_key . '" value="' . $quantity . '" min="0" max="100" class="input-number aatc-input btn-block" />
            <button class="qty-plus"><svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 1a1 1 0 0 0-2 0v8a1 1 0 1 0 2 0V1Z" fill="currentColor"/><path d="M9 4H1a1 1 0 0 0 0 2h8a1 1 0 1 0 0-2Z" fill="currentColor"/></svg></button>
        </div>';
    }

    public static function aatc_add_to_cart_quantity_handler()
    {
        $input_quantity = !empty($_POST['quantity']) ? intval($_POST['quantity']) : false;
        if ($input_quantity == 0) {
            WC()->cart->remove_cart_item($_POST['item_key']);
        } else {
            $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
            $quantity = !$input_quantity ? 1 : wc_stock_amount($input_quantity);

            if (WC()->cart->set_quantity(sanitize_text_field( $_POST['item_key'] ), $quantity)) {

                do_action('woocommerce_ajax_added_to_cart', $product_id);

                if ('yes' === get_option('woocommerce_cart_redirect_after_add')) {
                    wc_add_to_cart_message(array($product_id => $quantity), true);
                }
            } else {

                $data = array(
                    'error' => true,
                    'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id));

                echo wp_send_json($data);
            }
        }
        WC_AJAX:: get_refreshed_fragments();

        WC()->cart->calculate_totals();
        woocommerce_cart_totals();

        wp_die();
    }
}

new WC_Advanced_Add_To_Cart();