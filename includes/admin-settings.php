<?php
// Admin menu page setup
add_action( 'admin_menu', 'custom_stripe_payment_plugin_menu' );

function custom_stripe_payment_plugin_menu() {
    add_menu_page(
        'Stripe Payment Plugin',
        'Stripe Plugin',
        'manage_options',
        'custom-stripe-payment-plugin',
        'custom_stripe_payment_plugin_options_page'
    );

    add_submenu_page( 
        'custom-stripe-payment-plugin',
        'Stripe Forms',
        'Stripe Forms',
        'manage_options',
        'edit.php?post_type=stripe_payment'
    );

    // Add submenu page for transactions
    add_submenu_page(
        'custom-stripe-payment-plugin',
        'Stripe Transactions',
        'Transactions',
        'manage_options',
        'custom-stripe-payment-plugin-transactions',
        'custom_stripe_payment_plugin_transactions_page'
    );

    add_submenu_page(
        'custom-stripe-payment-plugin',  
        'Stripe Products',
        'Stripe Products',
        'manage_options',
        'stripe-products',
        'display_stripe_products_page'
    );

    add_submenu_page(
        'custom-stripe-payment-plugin', // Parent menu slug
        'User Information', // Page title
        'User Information', // Menu title
        'manage_options', // Capability
        'custom-stripe-user-info', // Menu slug
        'custom_stripe_user_info_callback' // Callback function
    );




}

// Callback function for options page
function custom_stripe_payment_plugin_options_page() {
    // Display settings form and handle form submission here
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle form submission
    if ( isset( $_POST['submit'] ) ) {
        // Process and save form data
        update_option( 'stripe_live_mode', isset( $_POST['stripe_live_mode'] ) ? 1 : 0 );
        update_option( 'stripe_live_secret_key', sanitize_text_field( $_POST['stripe_live_secret_key'] ) );
        update_option( 'stripe_sandbox_publishable_key', sanitize_text_field( $_POST['stripe_sandbox_publishable_key'] ) );
        update_option( 'stripe_sandbox_secret_key', sanitize_text_field( $_POST['stripe_sandbox_secret_key'] ) );
        update_option( 'stripe_success_page_url', esc_url_raw( $_POST['stripe_success_page_url'] ) );
        update_option( 'stripe_failure_page_url', esc_url_raw( $_POST['stripe_failure_page_url'] ) );

        // Additional fields handling for product creation, subscriptions, checkout options, etc.
    }

    // Display the settings form
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Live Mode', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><label><input type="checkbox" name="stripe_live_mode" value="1" <?php checked( get_option( 'stripe_live_mode', false ) ); ?>> <?php esc_html_e( 'Enable Live Mode', 'custom-stripe-payment-plugin' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Live Publishable Key', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="text" name="stripe_live_publishable_key" value="<?php echo esc_attr( get_option( 'stripe_live_publishable_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Live Secret Key', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="text" name="stripe_live_secret_key" value="<?php echo esc_attr( get_option( 'stripe_live_secret_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sandbox Publishable Key', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="text" name="stripe_sandbox_publishable_key" value="<?php echo esc_attr( get_option( 'stripe_sandbox_publishable_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sandbox Secret Key', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="text" name="stripe_sandbox_secret_key" value="<?php echo esc_attr( get_option( 'stripe_sandbox_secret_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Success Page URL', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="url" name="stripe_success_page_url" value="<?php echo esc_url( get_option( 'stripe_success_page_url', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Failure Page URL', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="url" name="stripe_failure_page_url" value="<?php echo esc_url( get_option( 'stripe_failure_page_url', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <!-- Additional fields for product creation, subscriptions, checkout options, etc. -->
            </table>
            <?php submit_button( 'Save Settings' ); ?>
        </form>

        <?php if(!empty(get_option( 'stripe_sandbox_secret_key' ))){ ?>
            <h2><?php esc_html_e( 'Test Transaction', 'custom-stripe-payment-plugin' ); ?></h2>
            <form id="stripe-test-form">
                <input type="hidden" name="action" value="custom_stripe_test_transaction">
                <button type="submit"><?php esc_html_e( 'Run Test Transaction', 'custom-stripe-payment-plugin' ); ?></button>
            </form>
            <div id="stripe-test-result"></div>
        <?php } ?>
    </div>
    <?php
}

// AJAX handler for test transaction
function custom_stripe_test_transaction() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    // Initialize Stripe with the correct API key
    \Stripe\Stripe::setApiKey( custom_stripe_payment_plugin_get_secret_key() );

    try {
        // Create a test payment intent
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => 1000, // $10.00
            'currency' => 'usd',
            'payment_method_types' => ['card'],
        ]);

        wp_send_json_success( 'Test transaction successful: ' . $payment_intent->id );
    } catch ( Exception $e ) {
        wp_send_json_error( 'Error creating test transaction: ' . $e->getMessage() );
    }
}
add_action( 'wp_ajax_custom_stripe_test_transaction', 'custom_stripe_test_transaction' );

// Enqueue script to handle test transaction form submission
function custom_stripe_payment_plugin_admin_scripts() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('stripe-test-form').addEventListener('submit', function(e) {
            e.preventDefault();

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    var resultDiv = document.getElementById('stripe-test-result');
                    if (response.success) {
                        resultDiv.innerHTML = '<p>' + response.data + '</p>';
                    } else {
                        resultDiv.innerHTML = '<p>Error: ' + response.data + '</p>';
                    }
                }
            };

            var formData = new FormData(document.getElementById('stripe-test-form'));
            var params = new URLSearchParams(formData).toString();
            xhr.send(params);
        });
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'custom_stripe_payment_plugin_admin_scripts' );

// Callback function for transactions page
function custom_stripe_payment_plugin_transactions_page() {
    // Display transactions
    display_stripe_transactions();
}


// Function to display Stripe products page
function display_stripe_products_page() {
    // Include Stripe PHP library
    require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php' );

    // Initialize Stripe API with your secret key
    \Stripe\Stripe::setApiKey( custom_stripe_payment_plugin_get_secret_key() );

    // Fetch existing Stripe products
    try {
        $products = \Stripe\Product::all(['limit' => 50]);
    } catch (Exception $e) {
        echo '<div class="error"><p>Error fetching products: ' . esc_html($e->getMessage()) . '</p></div>';
        return;
    }

    // Display products
    ?>
    <div class="wrap">
        <h1><?php echo esc_html('Stripe Products'); ?></h1>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product ID', 'custom-stripe-payment-plugin'); ?></th>
                    <th><?php esc_html_e('Name', 'custom-stripe-payment-plugin'); ?></th>
                    <th><?php esc_html_e('Description', 'custom-stripe-payment-plugin'); ?></th>
                    <th><?php esc_html_e('Price (USD)', 'custom-stripe-payment-plugin'); ?></th>
                    <th><?php esc_html_e('Type', 'custom-stripe-payment-plugin'); ?></th>
                    <th><?php esc_html_e('Interval', 'custom-stripe-payment-plugin'); ?></th>
                    <th><?php esc_html_e('Created', 'custom-stripe-payment-plugin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($products->data as $product) {

                    try {
                        // Retrieve the product from Stripe to check if it's active
                        $stripe_product = \Stripe\Product::retrieve($product->id);
                        
                        // Check if the product is active
                        if ($stripe_product->active) {
                                    // Retrieve prices associated with the product
                                    $prices = \Stripe\Price::all(['product' => $product->id]);
                                    $price = !empty($prices->data) ? $prices->data[0] : null;
                        
                                    ?>
                            <tr>
                                <td><?php echo esc_html($product->id); ?></td>
                                <td><?php echo esc_html($product->name); ?></td>
                                <td><?php echo esc_html($product->description); ?></td>
                                <td><?php echo esc_html($price ? number_format($price->unit_amount / 100, 2) : 'N/A'); ?></td>
                                <td><?php echo esc_html($price && $price->type === 'recurring' ? 'Recurring' : 'One-off'); ?></td>
                                <td>
                                    <?php 
                                        if ($price && $price->type === 'recurring') {
                                            echo esc_html(ucfirst($price->recurring->interval));
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', $product->created)); ?></td>
                            </tr>
                            <?php
                        }
                        } catch (Exception $e) {
                            echo '<div class="error"><p>Error fetching product details: ' . esc_html($e->getMessage()) . '</p></div>';
                    }
                } ?>
            </tbody>
        </table>
    </div>
   
    <?php
}

function custom_stripe_user_info_callback() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="wrap">
        <h1>Stripe Customers</h1>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Default Payment Method</th>
                    <th>Created</th>
                    <th>Description</th>
                    <th>Subscription Name</th>
                    <th>Subscription Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    // Initialize Stripe with your API key
                    \Stripe\Stripe::setApiKey(custom_stripe_payment_plugin_get_secret_key());

                    // Retrieve a list of customers from Stripe
                    $stripe_customers = \Stripe\Customer::all(['limit' => 10]); // Adjust limit as needed

                    foreach ($stripe_customers->data as $customer) {
                        ?>
                        <tr>
                            <td><?php echo $customer->id; ?></td>
                            <td><?php echo $customer->name ?? 'N/A'; ?></td>
                            <td><?php echo $customer->email; ?></td>
                            <td><?php echo isset($customer->invoice_settings->default_payment_method) ? $customer->invoice_settings->default_payment_method : 'N/A'; ?></td>
                            <td><?php echo date('Y-m-d H:i:s', $customer->created); ?></td>
                            <td><?php echo $customer->description ?? 'N/A'; ?></td>
                            <td>
                                <?php
                                // Check if the customer has active subscriptions
                                $subscriptions = \Stripe\Subscription::all(['customer' => $customer->id]);
                                
                                if (!empty($subscriptions->data)) {
                                    foreach ($subscriptions->data as $subscription) {
                                        if ($subscription->status === 'active') {
                                            echo $subscription->plan->nickname;
                                            break;
                                        }
                                    }
                                } else {
                                    echo 'No subscriptions';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Check if the customer has active subscriptions
                                $active_subscription = false;
                                foreach ($subscriptions->data as $subscription) {
                                    if ($subscription->status === 'active') {
                                        $active_subscription = true;
                                        break;
                                    }
                                }
                                echo $active_subscription ? 'Active' : 'Inactive';
                                ?>
                            </td>
                        </tr>
                        <?php
                    }
                } catch (Exception $e) {
                    echo '<tr><td colspan="8">Error fetching customers: ' . $e->getMessage() . '</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}


// Ensure the Stripe PHP SDK is properly included in your plugin


