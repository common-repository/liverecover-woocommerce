jQuery(document).ready(function () {
  jQuery(document).on('click', '.lr-notice .notice-dismiss', function () {
    jQuery.post(
      params.ajaxurl,
      { action: 'liverecover_dismiss_notice' },
      function () { },
    );
  })
});
