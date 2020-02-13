(function ($) {
    $();
    'use strict';

    /**
     * All of the code for your admin-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here, so the
     * $ function reference has been prepared for usage within the scope
     * of this function.
     *
     * This enables you to define handlers, for when the DOM is ready:
     *
     * $(function() {
     *
     * });
     *
     * When the window is loaded:
     *
     * $( window ).load(function() {
     *
     * });
     *
     * ...and/or other possibilities.
     *
     * Ideally, it is not considered best practise to attach more than a
     * single DOM-ready or window-load handler for a particular page.
     * Although scripts in the WordPress core, Plugins and Themes may be
     * practising this, we should strive to set a better example in our own work.
     */

})(jQuery);


function vaulty_process(id, level) {
    var $base = jQuery('.vaulty-attachment_' + id);
    $base.removeClass('unlocked locked').addClass('loading');
    $base.find(':input').prop('disabled', true);

    var nonce = $base.find(':input').data('nonce');

    jQuery.ajax({
        type: "post",
        dataType: "json",
        url: Vaulty.ajaxurl,
        cache: false,
        data: {action: "vaulty_secure", attachment_id: id, nonce: nonce, level: level},
        success: function (data) {
            $base.removeClass('loading').addClass(data.locked ? 'locked' : 'unlocked');
            $base.find('.vaulty-select').val(data.level);
            $base.find(':input').prop('disabled', false);
            $base.find(':input').data('nonce', data.nonce);
        },
        error: function (jqXHR, status) {
            if (jqXHR.errorCode == 401) {
                alert("It seems like the session expired - Please reload the page, if necessary log in and try again.");
            } else if (jqXHR.errorCode == 400) {
                alert("It seems like the request could not be handled correctly - please try again or contact your administrator.")
            } else if (jqXHR.errorCode == 500) {
                alert("There was a failure while securing the files - please contact your administrator.")
            }
            alert("There was a failure while securing the files - please contact your administrator.")
        },
        timeout: function () {
            alert("The server seems to run under heavy load, please try again later");
        }
    });

}