jQuery(document).ready(function ($) {
    if ($('.ginput_container_email').length > 0) {
        var email = $('.ginput_container_email input:first'),
            emailId = email.attr('id'),
            emailVal = email.val();

        if (emailVal !== '') {
            $('#' + emailId + '_2').val(emailVal);
        }
    }
});