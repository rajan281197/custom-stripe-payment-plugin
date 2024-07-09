(function($) {
    function fetchProductsBasedOnType(type) {
        $('#stripe_product_container').html('<div class="loading-indicator"><div class="loader"</div></div>');

        var data = {
            action: 'fetch_stripe_products',
            form_type: type,
            post_id: $('input[name="post_ID"]').val(),
            nonce: customStripePayment.nonce
        };

        $.ajax({
            type: 'POST',
            url: customStripePayment.ajaxurl,
            data: data,
            beforeSend: function() {
                // Optionally, show loader here if needed
            },
            success: function(response) {
                $('#stripe_product_container').html(response).show();
            },
            error: function() {
                $('#stripe_product_container').html('<div class="error">Error loading products. Please try again later.</div>');
            },
            complete: function() {
                // Optionally, hide loader here if shown
            }
        });
    }

    $(document).ready(function() {
        var initialFormType = $('#stripe_form_type').val();
        fetchProductsBasedOnType(initialFormType);

        $('#stripe_form_type').on('change', function() {
            var formType = $(this).val();
            fetchProductsBasedOnType(formType);
        });
    });
})(jQuery);
