jQuery(document).ready(function($) {

    var stripe = Stripe(customStripeProductTabs.sandbox_pub_key); // Replace with your Stripe publishable key
    
    if(document.getElementById('card-element')){
        var elements = stripe.elements();
        var cardElement = elements.create('card');
        cardElement.mount('#card-element');

        cardElement.on('change', function(event) {
            var displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
    }

    // Handle payment submission
    $('#submit-payment').on('click', function(event) {
        event.preventDefault();

        var selectedProductId = $('input[name="selected_product"]:checked').val();

        // Fetch success and cancel URLs
        var successUrl = $('input[name="stripe_success_page"]').val();
        var cancelUrl = $('input[name="stripe_failure_page"]').val();
        var form_type = $('input[name="stripe_form_type"]').val();
        var email = $('#email').val(); // Get the email value

        stripe.createToken(cardElement).then(function(result) {
            if (result.error) {
                // Display error if there is an issue with the card
                var errorElement = document.getElementById('card-errors');
                errorElement.textContent = result.error.message;
            } else {
                // Send token to server for processing
                stripeTokenHandler(result.token,selectedProductId,successUrl,cancelUrl,form_type,email);
            }
        });
    });

    // Function to handle token submission to server
    function stripeTokenHandler(token, selectedProductId, successUrl, cancelUrl, formType,email) {
        $.ajax({
            url: customStripeProductTabs.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'process_payment',
                token: token.id,
                product_id: selectedProductId,
                success_url: successUrl,
                cancel_url: cancelUrl,
                form_type: formType,
                email   : email,
            },
            beforeSend: function() {
                $('.loading-indicator').show();
            },
            complete: function() {
                $('.loading-indicator').hide();
            },
            success: function(response) {
                // Handle successful payment (example: redirect or show success message)
                console.log(response);
                // Example: Redirect to a success page or show a success message
                if (response.success) {
                    window.location.href = response.data.redirect_url; // Redirect to success page
                } else {
                    alert('Payment failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $('.loading-indicator').hide();
            }
        });
    }

    $('#start-checkout').on('click', function(event) {
        event.preventDefault();
        console.log("Hello world test");
        var stripe = Stripe(customStripeProductTabs.sandbox_pub_key); // Replace with your Stripe publishable key
        
        // Fetch product ID selected
        var selectedProductId = $('input[name="selected_product"]:checked').val();

        // Fetch success and cancel URLs
        var successUrl = $('input[name="stripe_success_page"]').val();
        var cancelUrl = $('input[name="stripe_failure_page"]').val();
        var form_type = $('input[name="stripe_form_type"]').val();

        // Create checkout session
        $.ajax({
            url: customStripeProductTabs.ajaxurl,
            method: 'POST',
            data: {
                action: 'create_checkout_session',
                product_id: selectedProductId,
                success_url: successUrl,
                cancel_url: cancelUrl,
                form_type : form_type,
            },
            success: function(session) {
                console.log(session);
                // Redirect to Stripe Checkout
                if (session.success) {
                    // Redirect to Stripe Checkout
                    // https://checkout.stripe.com/c/pay/
                    stripe.redirectToCheckout({ sessionId: session.data.id })
                    
                    .then(function(result) {
                        if (result.error) {
                            console.error(result.error.message);
                            // Handle errors here
                        }
                    })
                    .catch(function(error) {
                        console.error('Error:', error);
                        // Handle errors here
                    });
                } else {
                    console.error('Error:', session.data);
                    // Handle errors here
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                // Handle AJAX errors here
            }
        });
    });
});
