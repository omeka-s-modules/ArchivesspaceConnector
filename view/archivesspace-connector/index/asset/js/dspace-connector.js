(function($) {
    $(document).ready(function() {
        $('#expand-all').on('click', function() {
            $('.communities .expand').click();
        });
        $('#collapse-all').on('click', function() {
            $('.communities .collapse').click();
        });
    });

    $(document).ready(function() {
        $('#index-submit').on('click', function() {
	        $('.spinner-display').addClass('loading');
        });
    });
})(jQuery)
