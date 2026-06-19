<?php

require_once __DIR__ . '/other/core.php';

ignore_user_abort(true);
set_time_limit(300);
ini_set('memory_limit', '512M');

minipix_process_upload();

?>
