var SubmitHandler = ( function( SubmitHandler, $ ) {

    $('.wpcf7-form').on('submit', function() {
        console.log('disable');
        $(this).find('.wpcf7-submit').attr('disabled', true);
    });
    
    $('.wpcf7').on('wpcf7submit', function () {
        console.log('enable');
        $(this).find('.wpcf7-submit').removeAttr('disabled');
    });

	return SubmitHandler;

} ( SubmitHandler || {}, jQuery ) );