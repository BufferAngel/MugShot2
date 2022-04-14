<?php

global $conf, $MugShot_logger;

if (!class_exists('Logger')) {
  include(PHPWG_ROOT_PATH . 'include/Logger.class.php');
}

$MugShot_logger = new Logger(array(
    'directory' => MUGSHOT_PATH . 'logs',
    'severity' => $conf['log_level'],
    'filename' => 'log_' . date('Y-m-d') . '_MugShot.log'
));
