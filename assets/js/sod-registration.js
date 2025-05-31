jQuery(document).ready(function($) {
    $('input[name="signing_dependent"]').on('change', function() {
        if ($('#signing-dependent-yes').is(':checked')) {
            $('#dependent-details').slideDown();
        } else {
            $('#dependent-details').slideUp();
        }
    });
});