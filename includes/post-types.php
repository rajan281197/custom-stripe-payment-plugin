<?php
// Register custom post type for Stripe payments
function custom_stripe_payment_post_type() {
    $labels = array(
        'name' => 'Stripe Payments',
        'singular_name' => 'Stripe Payment',
        'menu_name' => 'Stripe Payments',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Stripe Payment',
        'edit_item' => 'Edit Stripe Payment',
        'new_item' => 'New Stripe Payment',
        'view_item' => 'View Stripe Payment',
        'view_items' => 'View Stripe Payments',
        'search_items' => 'Search Stripe Payments',
        'not_found' => 'No Stripe Payments found',
        'not_found_in_trash' => 'No Stripe Payments found in Trash',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'supports' => array( 'title', 'custom-fields' ),
        'rewrite' => array( 'slug' => 'stripe-payment' ),
        'show_in_menu' => 'admin.php?page=custom-stripe-payment-plugin',
        'register_meta_box_cb' => 'custom_stripe_payment_meta_boxes', // Register meta box callback
    );

    register_post_type( 'stripe_payment', $args );
}

add_action( 'init', 'custom_stripe_payment_post_type' );


// Meta box setup function
function custom_stripe_payment_meta_boxes() {
    add_meta_box(
        'stripe_payment_details',
        __('Stripe Payment Details', 'custom-stripe-payment-plugin'),
        'custom_stripe_payment_meta_box_callback',
        'stripe_payment',
        'normal',
        'high'
    );
}

// Meta box callback function
function custom_stripe_payment_meta_box_callback( $post ) {
    wp_nonce_field( 'custom_stripe_payment_nonce', 'custom_stripe_payment_nonce_field' );

    // Retrieve saved meta data
    $form_type = get_post_meta( $post->ID, '_stripe_form_type', true );
    $checkout_type = get_post_meta( $post->ID, '_stripe_checkout_type', true );
    $success_page = get_post_meta( $post->ID, '_stripe_success_page', true );
    $failure_page = get_post_meta( $post->ID, '_stripe_failure_page', true );
    $selected_products = get_post_meta( $post->ID, '_stripe_products', true ) ?: [];

    ?>
    <table class="form-table">
        <tr>
            <th><label for="stripe_form_type"><?php _e( 'Form Type', 'custom-stripe-payment-plugin' ); ?></label></th>
            <td>
                <select name="stripe_form_type" id="stripe_form_type">
                    <option value="oneoff" <?php selected( $form_type, 'oneoff' ); ?>><?php _e( 'One Time Payment', 'custom-stripe-payment-plugin' ); ?></option>
                    <option value="subscription" <?php selected( $form_type, 'subscription' ); ?>><?php _e( 'Subscription', 'custom-stripe-payment-plugin' ); ?></option>
                </select>
            </td>
        </tr>

        <tr>
            <th><label for="stripe_checkout_type"><?php _e( 'Payment Type', 'custom-stripe-payment-plugin' ); ?></label></th>
            <td>
                <select name="stripe_checkout_type" id="stripe_checkout_type">
                    <option value="inline_payment" <?php selected( $checkout_type, 'inline_payment' ); ?>><?php _e( 'Inline Payment', 'custom-stripe-payment-plugin' ); ?></option>
                    <option value="checkout_session_payment" <?php selected( $checkout_type, 'checkout_session_payment' ); ?>><?php _e( 'Checkout Session Payment', 'custom-stripe-payment-plugin' ); ?></option>
                </select>
            </td>
        </tr>

        <tr>
            <th><label for="stripe_success_page"><?php _e( 'Success Page URL', 'custom-stripe-payment-plugin' ); ?></label></th>
            <td>
                <input type="url" name="stripe_success_page" id="stripe_success_page" value="<?php echo esc_url( $success_page ); ?>" class="regular-text">
                <button type="button" class="button select-link" data-target="#stripe_success_page"><?php _e( 'Select Link', 'custom-stripe-payment-plugin' ); ?></button>
            </td>
        </tr>
        <tr>
            <th><label for="stripe_failure_page"><?php _e( 'Failure/Return Page URL', 'custom-stripe-payment-plugin' ); ?></label></th>
            <td>
                <input type="url" name="stripe_failure_page" id="stripe_failure_page" value="<?php echo esc_url( $failure_page ); ?>" class="regular-text">
                <button type="button" class="button select-link" data-target="#stripe_failure_page"><?php _e( 'Select Link', 'custom-stripe-payment-plugin' ); ?></button>
            </td>
        </tr>
        <tr>
            <th><label for="stripe_products"><?php _e( 'Select Products', 'custom-stripe-payment-plugin' ); ?></label></th>
            <td>
                <div id="stripe_product_container">
                    <?php echo render_stripe_products( $form_type, $selected_products ); ?>
                </div>
            </td>
        </tr>
    </table>
    <?php
}

add_action('add_meta_boxes', 'custom_stripe_payment_meta_boxes');


// Render Stripe products based on type
function render_stripe_products( $form_type, $selected_products ) {
    $products = fetch_stripe_products( $form_type );

    ob_start();
    foreach ( $products as $product ) {
        $checked = in_array( $product['product_id'], $selected_products ) ? 'checked' : '';
        echo '<div>';
        echo '<input type="checkbox" name="stripe_products[]" value="' . esc_attr( $product['product_id'] ) . '" ' . $checked . '>';
        echo esc_html( $product['product_name'] . ' (' . strtoupper( $product['currency'] ) . ' ' . $product['price'] . ')' );
        echo '</div>';
    }
    return ob_get_clean();
}

// Fetch Stripe products based on type
function fetch_stripe_products( $form_type ) {
    \Stripe\Stripe::setApiKey( get_option( 'stripe_live_mode' ) ? get_option( 'stripe_live_secret_key' ) : get_option( 'stripe_sandbox_secret_key' ) );

    try {
        $products = \Stripe\Product::all( [ 'limit' => 100 ] );
        $all_products = [];

        foreach ( $products->data as $product ) {
            $prices = \Stripe\Price::all( [ 'product' => $product->id ] );

            foreach ( $prices->data as $price ) {
                $product_details = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price_id' => $price->id,
                    'price' => $price->unit_amount / 100,
                    'currency' => $price->currency,
                ];

                if ( isset( $price->recurring ) && isset( $price->recurring->interval ) ) {
                    $product_details['interval'] = $price->recurring->interval;
                    $product_details['type'] = 'subscription';
                } else {
                    $product_details['type'] = 'oneoff';
                }

                if ( $form_type === $product_details['type'] ) {
                    $all_products[] = $product_details;
                }
            }
        }
        return $all_products;

    } catch ( \Stripe\Exception\ApiErrorException $e ) {
        error_log( 'Stripe API Error: ' . $e->getMessage() );
        return [];
    }
}

// Handle AJAX request to fetch Stripe products
function ajax_fetch_stripe_products() {
    check_ajax_referer( 'custom_stripe_payment_nonce', 'nonce' );

    $form_type = isset( $_POST['form_type'] ) ? sanitize_text_field( $_POST['form_type'] ) : 'oneoff';
    $selected_products = get_post_meta( intval( $_POST['post_id'] ), '_stripe_products', true ) ?: [];
    echo render_stripe_products( $form_type, $selected_products );
    wp_die();
}
add_action( 'wp_ajax_fetch_stripe_products', 'ajax_fetch_stripe_products' );

// Save post meta when the post is saved
function save_custom_stripe_payment_meta( $post_id ) {
    if ( ! isset( $_POST['custom_stripe_payment_nonce_field'] ) || ! wp_verify_nonce( $_POST['custom_stripe_payment_nonce_field'], 'custom_stripe_payment_nonce' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['stripe_form_type'] ) ) {
        update_post_meta( $post_id, '_stripe_form_type', sanitize_text_field( $_POST['stripe_form_type'] ) );
    }

    if ( isset( $_POST['stripe_checkout_type'] ) ) {
        update_post_meta( $post_id, '_stripe_checkout_type', sanitize_text_field( $_POST['stripe_checkout_type'] ) );
    }

    if ( isset( $_POST['stripe_success_page'] ) ) {
        update_post_meta( $post_id, '_stripe_success_page', esc_url_raw( $_POST['stripe_success_page'] ) );
    }

    if ( isset( $_POST['stripe_failure_page'] ) ) {
        update_post_meta( $post_id, '_stripe_failure_page', esc_url_raw( $_POST['stripe_failure_page'] ) );
    }

    if ( isset( $_POST['stripe_products'] ) ) {
        $products = array_map( 'sanitize_text_field', $_POST['stripe_products'] );
        update_post_meta( $post_id, '_stripe_products', $products );
    }
}
add_action( 'save_post', 'save_custom_stripe_payment_meta' );

// Enqueue scripts and localize the ajaxurl
function custom_stripe_payment_enqueue_scripts($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }
    wp_enqueue_script('custom-stripe-payment-script', CUSTOM_STRIPE_PAYMENT_PLUGIN_URL . 'assets/js/custom-stripe-payment.js', ['jquery'], null, true);

    wp_enqueue_style('custom-stripe-payment-style', CUSTOM_STRIPE_PAYMENT_PLUGIN_URL . 'assets/css/custom-stripe-payment.css');

    wp_localize_script('custom-stripe-payment-script', 'customStripePayment', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('custom_stripe_payment_nonce')
    ]);

    

}
add_action('admin_enqueue_scripts', 'custom_stripe_payment_enqueue_scripts');



// Function to generate shortcode with payment form and product selection as radio buttons
function generate_stripe_payment_shortcode($atts) {
    // Extract shortcode attributes
    $atts = shortcode_atts(
        array(
            'post_id' => 0,
        ),
        $atts,
        'custom_stripe_payment'
    );

    // Ensure $post_id is numeric and valid
    if (!is_numeric($atts['post_id']) || !get_post_status($atts['post_id'])) {
        return ''; // Return empty string if post ID is invalid
    }

    // Retrieve specific meta fields for display
    $form_type = get_post_meta($atts['post_id'], '_stripe_form_type', true);
    $checkout_type = get_post_meta($atts['post_id'], '_stripe_checkout_type', true);
    $selected_products = get_post_meta($atts['post_id'], '_stripe_products', true);
    $success_page = get_post_meta($atts['post_id'], '_stripe_success_page', true );
    $failure_page = get_post_meta($atts['post_id'], '_stripe_failure_page', true );

    // Include Stripe PHP library
    \Stripe\Stripe::setApiKey( custom_stripe_payment_plugin_get_secret_key() );

    // Fetch product details from Stripe
    $products = [];
    foreach ($selected_products as $product_id) {
        try {
            $product = \Stripe\Product::retrieve($product_id);
            $prices = \Stripe\Price::all([
                'product' => $product_id,
                'active' => true,
                'limit' => 1,
            ]);
    
            if (count($prices->data) > 0) {
                $price = $prices->data[0];
                
                // Check if recurring object exists
                if (isset($price->recurring)) {
                    $recur = $price->recurring;
                    $interval = ucfirst($recur->interval);
                    if (isset($recur->interval_count) && $recur->interval_count > 1) {
                        $interval .= 's';
                    }
                } else {
                    // Handle case where recurring details are not available
                    $recur = null;
                    $interval = ''; // Set default or handle appropriately
                }
    
                $products[] = [
                    'id' => $product_id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $price->unit_amount, // Assuming the price is in cents
                    'interval' => $interval,
                ];
            }
        } catch (Exception $e) {
            // Handle error
            continue; // Skip to the next product on error
        }
    }
    

    // Prepare output HTML
    ob_start();
    ?>
    <style>
        /* Basic CSS styles for the custom-stripe-product-selection */
        .custom-stripe-product-selection {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
        }

        .custom-stripe-product-selection h2 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .product-option {
            margin-bottom: 15px;
        }

        .product-option label {
            display: block;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .product-option label:hover {
            background-color: #f0f0f0;
        }

        .product-option label h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .product-option label p {
            margin-bottom: 8px;
            color: #666;
        }

        .product-option label p.price {
            font-size: 16px;
            font-weight: bold;
        }

        .product-option label p.interval {
            font-size: 14px;
            color: #999;
        }

        /* Hide radio inputs */
        .product-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .product-option input[type="radio"]:checked + label {
            background-color: #4CAF50; /* Green background color */
            color: #fff; /* White text color */
        }

        #card-element {
            background-color: #fff;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        #card-errors {
            color: #dc3545; /* Error message color */
            font-size: 14px;
            margin-top: 10px;
        }


    </style>
    <div class="custom-stripe-product-selection">
        <h2><?php echo get_the_title($atts['post_id']); ?></h2>
        <form id="stripe-payment-form" method="POST" action="">
            <input type="hidden" name="stripe_post_id" value="<?php echo esc_attr($atts['post_id']); ?>">
            <input type="hidden" name="stripe_form_type" value="<?php echo esc_attr($form_type); ?>">
            <input type="hidden" name="stripe_checkout_type" value="<?php echo esc_attr($checkout_type); ?>">
            <input type="hidden" name="stripe_success_page" value="<?php echo esc_url($success_page); ?>">
            <input type="hidden" name="stripe_failure_page" value="<?php echo esc_url($failure_page); ?>">

            <div id="product-selection">
                <?php foreach ($products as $product): ?>
                    <div class="product-option">
                        <input type="radio" name="selected_product" value="<?php echo esc_attr($product['id']); ?>" id="product-<?php echo esc_attr($product['id']); ?>">
                        <label for="product-<?php echo esc_attr($product['id']); ?>">
                            <h3><?php echo esc_html($product['name']); ?></h3>
                            <p><?php echo esc_html($product['description']); ?></p>
                            <p><strong><?php _e('Price:', 'custom-stripe-payment-plugin'); ?></strong> $<?php echo number_format($product['price'] / 100, 2); ?></p>
                            <p class="interval"><?php echo $product['interval']; ?></p>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Payment Form -->
            <?php if ($checkout_type == 'inline_payment'): ?>
                <div id="card-element"><!-- Stripe will insert the card element here --></div>
                <div id="card-errors" role="alert"></div>
                <input type="email" id="email" name="email" placeholder="<?php _e('Enter your email', 'custom-stripe-payment-plugin'); ?>" required>

                <button id="submit-payment"><?php _e('Pay Now', 'custom-stripe-payment-plugin'); ?></button>
                <div class="loading-indicator" style="display: none;">
                    <div class="loader"></div>
                </div>
            <?php else: ?>
                <button id="start-checkout"><?php _e('Proceed to Checkout', 'custom-stripe-payment-plugin'); ?></button>
            <?php endif; ?>
        </form>
    </div>
    
    <?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('custom_stripe_payment', 'generate_stripe_payment_shortcode');





// Add custom column to display shortcode on post listing page
function add_shortcode_column_to_stripe_payments($columns) {
    $columns['shortcode'] = 'Shortcode';
    return $columns;
}
add_filter('manage_stripe_payment_posts_columns', 'add_shortcode_column_to_stripe_payments');

// Display shortcode in custom column
function display_stripe_payment_shortcode($column_name, $post_id) {
    if ($column_name === 'shortcode') {
        echo '<input type="text" id="shortcode_' . $post_id . '" readonly="readonly" value="[custom_stripe_payment post_id=' . $post_id . ']" onclick="copyToClipboard(this)" style="width: 100%; box-sizing: border-box; padding: 3px;">';
        echo '<span id="copy_message_' . $post_id . '" style="display:none;color:green;">Copied!</span>';
    }
}
add_action('manage_stripe_payment_posts_custom_column', 'display_stripe_payment_shortcode', 10, 2);

// Enqueue jQuery and custom script
function enqueue_custom_admin_scripts() {

    wp_enqueue_script('jquery');

    wp_enqueue_script('custom-admin-scripts', CUSTOM_STRIPE_PAYMENT_PLUGIN_URL . 'assets/js/admin-scripts.js', array('jquery'), null, true);
    wp_add_inline_script('jquery', '
        function copyToClipboard(element) {
            var $temp = jQuery("<input>");
            jQuery("body").append($temp);
            $temp.val(jQuery(element).val()).select();
            document.execCommand("copy");
            $temp.remove();
            showCopyMessage(jQuery(element).attr("id"));
        }

        function showCopyMessage(elementId) {
            var messageId = "copy_message_" + elementId.split("_")[1];
            var messageElement = jQuery("#" + messageId);
            messageElement.show();
            setTimeout(function() {
                messageElement.fadeOut();
            }, 2000);
        }
    ');


}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_scripts');