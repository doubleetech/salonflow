   
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/default.js"></script>
   <script>
    var SALONFLOW = {
        APP_URL: '<?php echo APP_URL; ?>',
        INITIAL_TIMESTAMP: <?php echo json_encode(time()); ?>,
        USER_ID: <?php echo json_encode(Auth::id()); ?>,
        USER_ROLE: <?php echo json_encode(Session::get('user_role')); ?>
    };
</script>
     <script src="<?php echo APP_URL; ?>/assets/js/app.js?v=<?php echo ASSET_VERSION; ?>"></script>
</body>
</html>
