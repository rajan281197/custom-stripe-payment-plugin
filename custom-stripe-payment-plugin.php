<?php
/**
 * Plugin Name: Custom Stripe Payment Plugin
 * Plugin URI: Your Plugin URI
 * Description: Custom Stripe Payment Plugin for WordPress.
 * Version: 1.0
 * Author: Your Name
 * Author URI: Your Author URI
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-stripe-payment-plugin
 */

// Define constants
define( 'CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CUSTOM_STRIPE_PAYMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php' ); // Stripe PHP library
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/admin-settings.php' ); // Admin settings
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/stripe-init.php' ); // Stripe API initialization
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/payment-functions.php' ); // Payment form functions
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/transaction-functions.php' ); // Transaction management
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/post-types.php' ); // Custom post types

// Initialize Stripe API with your secret key
\Stripe\Stripe::setApiKey( custom_stripe_payment_plugin_get_secret_key() );

// Enqueue Stripe.js, jQuery UI, and custom script
function enqueue_custom_stripe_product_tabs_scripts() {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('custom-stripe-product-tabs', CUSTOM_STRIPE_PAYMENT_PLUGIN_URL. 'assets/js/custom-stripe-product-tabs.js', array('jquery', 'stripe-js', 'jquery-ui-tabs'), null, true);

        wp_localize_script('custom-stripe-product-tabs', 'customStripeProductTabs', array(
            'ajaxurl'           => admin_url('admin-ajax.php'),
            'sandbox_pub_key'   => get_option( 'stripe_sandbox_publishable_key', '' ),
            'nonce'             => wp_create_nonce('custom_stripe_product_tabs_nonce')
        ));
        wp_enqueue_style('custom-stripe-payment-style', CUSTOM_STRIPE_PAYMENT_PLUGIN_URL . 'assets/css/custom-stripe-payment.css');

}
add_action('wp_enqueue_scripts', 'enqueue_custom_stripe_product_tabs_scripts');

// AJAX action for fetching product details
add_action('wp_ajax_custom_stripe_fetch_product', 'custom_stripe_fetch_product_callback');

function custom_stripe_fetch_product_callback() {
    check_ajax_referer('custom-stripe-security', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $product_id = $_POST['product_id'];

    try {
        // Replace with your Stripe API call to retrieve the product details
        $product = \Stripe\Product::retrieve($product_id);

        // Get the price associated with the product to fetch amount and billing period
        $prices = \Stripe\Price::all(['product' => $product_id]);
        $price = !empty($prices->data) ? $prices->data[0] : null;

        // Prepare response data
        $response = array(
            'product' => array(
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'type' => $product->type, // Adjust accordingly based on Stripe API response
                'amount' => $price ? $price->unit_amount : '', // Get amount from price object
                'billing_period' => $price ? $price->recurring->interval : '', // Get billing period from price object
                // Include other necessary fields here
            ),
        );

        // Respond with success and data
        wp_send_json_success($response);
    } catch (Exception $e) {
        // Handle errors and send error response
        wp_send_json_error($e->getMessage());
    }

    // Always use wp_die() at the end of AJAX callback
    wp_die();
}

// Add AJAX action for creating checkout session
add_action('wp_ajax_create_checkout_session', 'create_checkout_session_ajax_handler');
add_action('wp_ajax_nopriv_create_checkout_session', 'create_checkout_session_ajax_handler');

function create_checkout_session_ajax_handler() {
    // Ensure the necessary data is provided
    if (!isset($_POST['product_id']) || !isset($_POST['success_url']) || !isset($_POST['cancel_url'])) {
        wp_send_json_error('Missing parameters.');
    }

    // Include Stripe PHP library
    \Stripe\Stripe::setApiKey(custom_stripe_payment_plugin_get_secret_key());

    // Sanitize and validate input data
    $product_id     =   sanitize_text_field($_POST['product_id']);
    $success_url    =   esc_url_raw($_POST['success_url']);
    $cancel_url     =   esc_url_raw($_POST['cancel_url']);
    $form_type      =  sanitize_text_field($_POST['form_type']) == 'subscription' ? sanitize_text_field($_POST['form_type']) : 'payment';

    // Fetch product details from Stripe
    try {
        $product = \Stripe\Product::retrieve($product_id);
        $prices = \Stripe\Price::all([
            'product' => $product_id,
            'active' => true,
            'limit' => 1,
        ]);

        if (count($prices->data) > 0) {
            $price = $prices->data[0];
        } else {
            wp_send_json_error('No active price found for the product.');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error fetching product details from Stripe.');
    }

    // Create checkout session
    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $price->id,
                // 'price_data' => [
                //     'currency' => $price->currency,
                //     'product_data' => [
                //         'name' => $product->name,
                //     ],
                //     'unit_amount' => $price->unit_amount,
                // ],
                'quantity' => 1,
            ]],
            'mode' => $form_type,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
        ]);

        // Return session ID to the frontend
        wp_send_json_success(['id' => $session->id]);
    } catch (Exception $e) {
        wp_send_json_error('Error creating checkout session: ' . $e->getMessage());
    }
}

// AJAX action for processing payment
add_action('wp_ajax_process_payment', 'process_payment');
add_action('wp_ajax_nopriv_process_payment', 'process_payment');

function process_payment() {
    // Retrieve necessary POST data
    $token = $_POST['token'];
    $product_id = $_POST['product_id'];
    $success_url = $_POST['success_url'];
    $cancel_url = $_POST['cancel_url'];
    $email = sanitize_email( $_POST['email'] );
    $form_type      =  sanitize_text_field($_POST['form_type']) == 'subscription' ? sanitize_text_field($_POST['form_type']) : 'payment';

    // Include Stripe PHP library
    \Stripe\Stripe::setApiKey(custom_stripe_payment_plugin_get_secret_key());

    // Example: Process payment using the token (replace with your actual logic)
    try {
        // Retrieve product and price details from Stripe
        $product = \Stripe\Product::retrieve($product_id);
        $prices = \Stripe\Price::all([
            'product' => $product_id,
            'active' => true,
            'limit' => 1,
        ]);


        // echo "<pre>";
        // print_r($prices);
        // echo "</pre>";

        // Create a new customer
       
        
        if (count($prices->data) > 0) {
            $price = $prices->data[0];
        } else {
            wp_send_json_error('No active price found for the product.');
        }

        if( $form_type == 'subscription' ) {

            $customer = \Stripe\Customer::create([
                'email' => $email,
                'source' => $token,
            ]);

            
            // Create the subscription
            $subscription = \Stripe\Subscription::create([
                'customer' => $customer->id,
                'items' => [[
                    'plan' => $price->id,
                ]],
            ]);

            // Return success response
            wp_send_json_success([
                'message' => 'Subscription created successfully!',
                'redirect_url' => $success_url,
            ]);
        } else {

             // Create the charge
             $charge = \Stripe\Charge::create([
                'amount' => $price->unit_amount,
                'currency' => $price->currency,
                'description' => $product->name,
                'source' => $token,
                'receipt_email' => $email,
                'metadata' => [
                    'product_id' => $product_id,
                    'price_id' => $price->id,
                    'email' => $email,
                ],
            ]);

             // Return success response
             wp_send_json_success([
                'message' => 'Payment successfully!',
                'redirect_url' => $success_url,
            ]);
        }
        

        // Send JSON response back to frontend
        wp_send_json($response);

    } catch (Exception $e) {
        // Handle Stripe API exceptions
        wp_send_json_error('Error processing payment: ' . $e->getMessage());
    }

    // Ensure wp_send_json or wp_send_json_error is the last thing in this function
    wp_die();
}

