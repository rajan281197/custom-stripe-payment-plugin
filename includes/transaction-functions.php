<?php
// Include Stripe PHP library
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php' );

// Function to retrieve and display Stripe transactions
function display_stripe_transactions() {
    // Initialize Stripe API with your secret key
    \Stripe\Stripe::setApiKey( custom_stripe_payment_plugin_get_secret_key() );

    // Define parameters for fetching PaymentIntents
    $params = [
        'limit' => 50,
        'expand' => ['data.payment_method'],
    ];

    // Query Stripe for PaymentIntents
    try {
        $payment_intents = \Stripe\PaymentIntent::all( $params );
    } catch ( Exception $e ) {
        // Handle API error, if any
        echo '<div class="error"><p>Error fetching transactions: ' . esc_html( $e->getMessage() ) . '</p></div>';
        return;
    }

    ?>

    <div class="wrap">
        <h1><?php echo esc_html( 'Stripe Transactions' ); ?></h1>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Transaction ID', 'custom-stripe-payment-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'custom-stripe-payment-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Currency', 'custom-stripe-payment-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'custom-stripe-payment-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'custom-stripe-payment-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'custom-stripe-payment-plugin' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $payment_intents->data as $payment_intent ) : ?>
                    <tr>
                        <td><?php echo esc_html( $payment_intent->id ); ?></td>
                        <td><?php echo esc_html( number_format( $payment_intent->amount_received / 100, 2 ) ); ?></td>
                        <td><?php echo esc_html( strtoupper( $payment_intent->currency ) ); ?></td>
                        <td><?php echo esc_html( $payment_intent->description ); ?></td>
                        <td><?php echo esc_html( $payment_intent->status ); ?></td>
                        <td><?php echo esc_html( date( 'Y-m-d H:i:s', $payment_intent->created ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Ensure the function exists before including
if ( ! function_exists( 'custom_stripe_payment_plugin_get_secret_key' ) ) {
    // Include stripe-init.php to access the function
    require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/stripe-init.php' );
}


// Now you can safely use custom_stripe_payment_plugin_get_secret_key() here if needed
// Example function that uses the secret key function
function custom_stripe_payment_plugin_process_transaction() {
    $secret_key = custom_stripe_payment_plugin_get_secret_key();
    // Process transaction using the secret key
}