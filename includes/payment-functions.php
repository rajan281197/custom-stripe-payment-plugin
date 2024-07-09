<?php
// Function to generate payment form shortcode
function custom_stripe_payment_form_shortcode( $atts ) {
    ob_start();
    ?>
    <form action="" method="post" id="custom-stripe-payment-form">
        <!-- Payment form fields -->
        <label for="card-element">
            Credit or debit card
        </label>
        <div id="card-element">
            <!-- A Stripe Element will be inserted here. -->
        </div>

        <!-- Used to display form errors. -->
        <div id="card-errors" role="alert"></div>

        <button id="submit-payment-btn">Submit Payment</button>
    </form>

    <script>
        var stripe = Stripe('<?php echo esc_js( custom_stripe_payment_plugin_get_secret_key() ); ?>');
        var elements = stripe.elements();
        var card = elements.create('card');
        card.mount('#card-element');

        // Handle form submission
        document.getElementById('custom-stripe-payment-form').addEventListener('submit', function(event) {
            event.preventDefault();
            stripe.createToken(card).then(function(result) {
                if (result.error) {
                    // Inform the user if there was an error
                    var errorElement = document.getElementById('card-errors');
                    errorElement.textContent = result.error.message;
                } else {
                    // Token successfully created, submit form with token ID
                    stripeTokenHandler(result.token);
                }
            });
        });

        function stripeTokenHandler(token) {
            // Insert the token ID into the form so it gets submitted to the server
            var form = document.getElementById('custom-stripe-payment-form');
            var hiddenInput = document.createElement('input');
            hiddenInput.setAttribute('type', 'hidden');
            hiddenInput.setAttribute('name', 'stripeToken');
            hiddenInput.setAttribute('value', token.id);
            form.appendChild(hiddenInput);

            // Submit the form
            form.submit();
        }
    </script>
    <?php
    return ob_get_clean();
}

// Function to handle Stripe Checkout session creation
function custom_plugin_create_stripe_checkout_session( $product_id, $price_id, $success_url, $cancel_url ) {
    // Function implementation
    $checkout_session = \Stripe\Checkout\Session::create( array(
        'payment_method_types' => array( 'card' ),
        'line_items' => array(
            array(
                'price' => $price_id,
                'quantity' => 1,
            ),
        ),
        'mode' => 'payment',
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
    ) );

    return $checkout_session;
}

