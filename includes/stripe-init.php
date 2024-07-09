<?php
// Initialize Stripe API
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php' );

function custom_stripe_payment_plugin_init() {
    \Stripe\Stripe::setApiKey( custom_stripe_payment_plugin_get_secret_key() );
}

// Retrieve the appropriate Stripe secret key based on the current mode
function custom_stripe_payment_plugin_get_secret_key() {
    $live_mode = get_option( 'stripe_live_mode', false );

    if ( $live_mode ) {
        return get_option( 'stripe_live_secret_key' );
    } else {
        return get_option( 'stripe_sandbox_secret_key' );
    }
}

// Hook into WordPress initialization to set up Stripe API
add_action( 'init', 'custom_stripe_payment_plugin_init' );
