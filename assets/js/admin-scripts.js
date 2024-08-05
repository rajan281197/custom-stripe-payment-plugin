jQuery(document).ready(function($) {
    console.log("fdgfdgd");
    
    $('.select-link').on('click', function(e) {
        e.preventDefault();
        var targetInput = $(this).data('target');
        
        wpLink.open();
        
        $('#wp-link-submit').off('click').on('click', function(event) {
            event.preventDefault();
            var link = wpLink.getAttrs();
            $(targetInput).val(link.href);
            wpLink.close();
        });
    });
});
