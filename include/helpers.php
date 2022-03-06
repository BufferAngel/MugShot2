<?php

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/*
 * Creates the tagging user group and associates the current user with that group;
 */
function create_tag_group() {
    global $conf, $config_default;

    if (!isset($conf['MugShot'])):
      include(dirname(__FILE__).'/config_default.inc.php');
      conf_update_param('MugShot', $config_default);
      load_conf_from_db();
    endif;

    $conf['MugShot'] = safe_unserialize($conf['MugShot']);

    // Checks to see if a taggers group exists.
    $checkTaggerGroupQuery = "SELECT id FROM " . GROUPS_TABLE . " WHERE name='Taggers'";
    
    $result = fetch_sql($checkTaggerGroupQuery, 'id', false);

    if ($result == 0)
    {
      $makeTaggerGroupQuery = 'INSERT INTO ' . GROUPS_TABLE . ' (name) VALUES ("Taggers")';
      pwg_query($makeTaggerGroupQuery);
      
      $result = fetch_sql($checkTaggerGroupQuery, 'id', false);
    }

    $group_id = $result[0];

    $user = $_SESSION['pwg_uid'];

    // Determines if the taggers group is associated with the current user and, if not, associates it.
    $checkUserAssociationQuery = "SELECT * FROM " . USER_GROUP_TABLE . " WHERE group_id=$group_id AND user_id=$user";
    
    $association = fetch_sql($checkUserAssociationQuery, 'id', false);

    if ($association == 0)
    {
        $makeUserAssociationQuery = "INSERT INTO " . USER_GROUP_TABLE . " (group_id, user_id) VALUES ('$group_id','$user')";

        pwg_query($makeUserAssociationQuery);
    }

    // Note that array_push returns the index of the new array item.
    array_push($conf['MugShot']['groups'], $group_id);

    $conf['MugShot']['groups'] = array_unique($conf['MugShot']['groups']);

    conf_update_param(MUGSHOT_ID, $conf['MugShot']);

    load_conf_from_db();
}

/*
 * Creates the drop trigger to clear database values
 */
function create_tag_drop_trigger() {
    $deleteTriggerQuery = "DROP TRIGGER IF EXISTS `sync_mug_shot`;";

    pwg_query($deleteTriggerQuery);

    // [mysql error 1419] You do not have the SUPER privilege and binary logging is enabled (you *might* want to use the less safe log_bin_trust_function_creators variable)
    // This query is silently failing.
    $makeTriggerQuery = "CREATE TRIGGER `sync_mug_shot`
      AFTER DELETE ON ".TAGS_TABLE."
      FOR EACH ROW UPDATE face_tag_positions SET tag_id = NULL, confirmed = FALSE
      WHERE face_tag_positions.tag_id = old.id";

    pwg_query($makeTriggerQuery);
}

/*
 * Creates the MugShot face tag table with all data columns required for resizing.
 */
function create_facetag_table($copy=false) {
    $configQuery = 'INSERT INTO ' . CONFIG_TABLE . ' (param,value,comment) VALUES ("MugShot","","MugShot configuration values") ON DUPLICATE KEY UPDATE comment = "MugShot configuration values";';

  pwg_query($configQuery);

  $table_name = MUGSHOT_TABLE . ($copy ? "-copy" : "");

  $createTableQuery = "
CREATE TABLE IF NOT EXISTS " . $table_name . "(
      `id` mediumint(8) unsigned auto_increment NOT NULL primary key,
      `image_id` mediumint(8) unsigned NOT NULL default 0,
      `face_index` smallint(5) unsigned default NULL,
      `tag_id` smallint(5) unsigned default null,
      `top` float unsigned NOT NULL default 0.0,
      `lft` float unsigned NOT NULL default 0.0,
      `width` float unsigned NOT NULL default 0.0,
      `height` float unsigned NOT NULL default 0.0,
      `image_width` float unsigned NOT NULL default 0.0,
      `image_height` float unsigned NOT NULL default 0.0,
      `confirmed` bool NOT NULL default false,
      `ignored` bool NOT NULL default false
    );
"
."CREATE INDEX `ftp_index_image_tag` on ".$table_name." (`image_id`, `tag_id`);"
."CREATE INDEX `ftp_index_image_face` on ".$table_name." (`image_id`, `face_index`);";

  pwg_multi_query($createTableQuery);

  if ($copy) {
    $sql_copy_data = "
        INSERT INTO " . $table_name . " AS new (id, image_id, face_index, tag_id, top, lft, width, height, 
        image_width, image_height, confirmed, ignored) SELECT prev.id, prev.image_id, prev.face_index, prev.tag_id, prev.top, prev.lft, prev.width, prev.height, 
        prev.image_width, prev.image_height, prev.confirmed, prev.ignored FROM ".MUGSHOT_TABLE." AS prev;
    ";
  }
}


/**
 * Execute a multi-statement query
 *
 * @param string $query
 * @return array
 */
function pwg_multi_query(string $query): array
{
  global $mysqli;

  $mysqli->multi_query($query);

  $result = [];

  do {
      /* store the result set in PHP */
      $stmt_result = $mysqli->store_result();
      if ($stmt_result != false) {
          $result[] = $stmt_result->fetch_all(MYSQLI_ASSOC);
      } else {
          $result[] = false;
      }
  } while ($mysqli->next_result());
  return $result;
}


/*
 * Updates a table to add a new column if it doesn't already exist.
 */
/**
 * @param $table
 * @param $column
 * @param $column_type
 * @param $default_option
 * @return bool
 */
function AddColumnIfNotExists($table, $column, $column_type, $default_option): bool
{
  $addColumnQuery = <<<SQL_TEXT
SELECT COUNT(*)
  INTO @exist
  FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '$table'
    AND COLUMN_NAME = '$column' LIMIT 1;
    
SET @query = IF(@exist <= 0, 'ALTER TABLE $table ADD COLUMN $column $column_type $default_option;',
                'SELECT 1');

PREPARE statement FROM @query;
EXECUTE statement;
DEALLOCATE PREPARE statement;
SQL_TEXT;

  $result = [];
  try {
    $result = pwg_multi_query($addColumnQuery);
  } catch(Exception $e) {
    //var_dump($e);
  }

  if (count($result) >= 5) {
    if ($result[3] == false) {
      return true;
    }
  }

  return false;
}


/*
 * Updates the MugShot face tag table with all data columns required for resizing.
 */
function update_facetag_table() {
  $result = AddColumnIfNotExists(MUGSHOT_TABLE, 'id', 'mediumint(8) unsigned auto_increment', 'NOT NULL primary key FIRST');
  $result = AddColumnIfNotExists(MUGSHOT_TABLE, 'ignored', 'BOOL', 'NOT NULL DEFAULT false');
  $result = AddColumnIfNotExists(MUGSHOT_TABLE, 'confirmed', 'BOOL', 'NOT NULL DEFAULT false');
  $result = AddColumnIfNotExists(MUGSHOT_TABLE, 'face_index', 'BOOL', 'NULL');

  if ($result) {
    // Column was created mark existing entries that are not unidentified as confirmed
    // Initialize confirmed for user-created entries
    $updateConfirmedQuery = 'UPDATE '.MUGSHOT_TABLE.' set confirmed = true where tag_id not in (select id from `piwigo_tags` WHERE `name` LIKE "Unidentified Person #%");';

    pwg_query($updateConfirmedQuery);
  }
}

/*
 * Fetches Sql
 */
function fetch_sql($sql, $col, $ser) {
  $result = pwg_query($sql);
  
    while ($row = pwg_db_fetch_assoc($result)) {
      $data[] = $row;
    }
  
    if (!isset($data)) {
      $data = 0;
    } else {
      if($col !== false) {
        $data = array_column($data, $col);
      }
    }
  
    return ($ser) ? json_encode($data) : $data;
  }

?>
