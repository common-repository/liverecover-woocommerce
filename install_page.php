<div class="wrap">
  <div style="position: relative; max-width: 450px; margin: auto; top: 100px; text-align: center">

    <h1 id="header" style="margin-bottom: 50px; font-size: 24px">
      Welcome to LiveRecover!
    </h1>

    <p style="font-size: 18px">You're 1 click away from recovering sales over SMS.</p>

    <div style="display: flex; flex-direction: row; align-items: center; justify-content: space-evenly; margin: 30px 0 30px 0">
      <img src="<?php echo plugins_url('assets/liverecover.svg', __FILE__) ?>" alt="liverecover" width='200' />
      <div style="font-size: 40px; padding: 0 20px 0 20px">+</div>
      <img src="<?php echo plugins_url('assets/woocommerce.svg', __FILE__) ?>" alt="woocommerce" width='200' />
    </div>

    <p style="margin-bottom: 50px; font-size: 16px">
      Connect your store to start recovering abandoned carts with LiveRecover's human powered sales team.
    </p>

    <button id="connect" class="button button-primary" style="width: 200px; font-size: 20px">
      <strong>→ Let's connect</strong>
    </button>
  </div>
  <script>
    (function($) {
      var connectBtn = $('#connect');

      function disable() {
        connectBtn.prop('disabled', true);
        connectBtn.html('<strong>Connecting...</strong>');
      }

      function enable() {
        connectBtn.prop('disabled', false);
        connectBtn.html('<strong>Connect</strong>');
      }

      connectBtn.on('click', function () {
        disable();
        var win = window.open('<?php echo $url ?>', '_blank');

        var timer = setInterval(function () {
          if (win.closed) {
            clearInterval(timer);
            enable();
          }
        }, 500);

        window.addEventListener('message', function (e) {
          $.ajax({
            type: 'POST',
            url: "<?php echo admin_url('admin-ajax.php'); ?>",
            data: {
              "action": "savekey",
              "key": e.data,
            },
            success: function() {
              connectBtn.hide();
              window.location.href = '<?php echo admin_url('index.php') ?>';
            }
          });
        });
      });

    })(window.jQuery);
  </script>
</div>
