jQuery(document).ready(function ($) {
    if ( $('.gv-edit-entry-wrapper').length > 0 && $('.ginput_container_email').length > 0) {
        $('.ginput_container_email').each(function () {
            var email = $(this).find('input:first'),
                emailId = email.attr('id'),
                emailVal = email.val(),
                emailVerification = $('#' + emailId + '_2');

            if (emailVal !== '' && emailVerification.length > 0) {
                emailVerification.val(emailVal);
            }
        });
    }
});