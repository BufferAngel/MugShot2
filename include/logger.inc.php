<?php

global $conf;

if (!class_exists('Logger')) {
  include_once(PHPWG_ROOT_PATH . 'include/Logger.class.php');
}

if (!isset($MugShot_logger)) {
  $MugShot_logger = new Logger(array(
      'directory' => MUGSHOT_PATH . 'logs',
      'severity' => $conf['log_level'],
    // we use an hashed filename to prevent direct file access, and we salt with
    // the db_password instead of secret_key because the log must be usable in i.php
    // (secret_key is in the database)
      'filename' => 'log_' . date('Y-m-d') . '_MugShot.log',
  ));
}
