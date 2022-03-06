<?php

defined('MUGSHOT_PATH') or die('Hacking attempt!');

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH . 'include/Logger.class.php');

defined('MUGSHOT_TABLE') or define('MUGSHOT_TABLE', 'face_tag_positions');

class MugShot_Services
{
  public static $MugShot_logger;

  static function add_mugshot_methods(array $arr) {
    $service = &$arr[0];
    $service -> addMethod(
        'mugshot.bookem',
        'MugShot_Services::book_mugshots',
        array(),
        'Parses face tags from user.'
    );
  }

  /**
   * Converts names to ucfirst case, as determined by a space between surnames.
   */
  static function get_pretty_name(string $labeledTagName): string
  {
    $safeName = pwg_db_real_escape_string($labeledTagName);

    $splitName = explode(" ", $safeName);

    $arraySize = count($splitName);

    $prettyName = array();

    for ($i=0; $i < $arraySize; $i++) {
      $prettyName[$i] = ucfirst($splitName[$i]);
    }

    return implode(" ", $prettyName);
  }

// Add the posted mugshots to the database
  static function book_mugshots(array $data, &$service)
  {
    self::$MugShot_logger->info("Entered ...", null, array('File'=>__FILE__,'Line'=>__LINE__,'Class'=>__CLASS__,'Function'=>__FUNCTION__,'data'=>$data));

    if (!$service -> isPost()) {
      return new PwgError(405, "HTTP POST REQUIRED");
    }

    $imageId = pwg_db_real_escape_string($data['imageId']);

    unset($data['imageId']);
    $imageIdTagIdInsertionString = '';        //
    $faceTagPositionsInsertionString = '';    // Variable string. Groups data for entry in SQL database.
    $deleteTagQuery = '';                     // Delete string. Tags in the current image being removed.
    $hideFaceQuery = '';                      // Hidden string. Faces in the currant image being soft-removed.

    foreach ($data as $key => $value) {
//    self::$MugShot_logger->info("client data received - ".$key, null, $value);

      $labeledTagName = self::get_pretty_name($value['name']);
      $prevTagName = self::get_pretty_name($value['prevName']);

      $existingTagId = pwg_db_real_escape_string($value['tagId']);
      $faceIndex = pwg_db_real_escape_string($value['faceIndex']);
      $top = pwg_db_real_escape_string($value['top']);
      $left = pwg_db_real_escape_string($value['left']);
      $width = pwg_db_real_escape_string($value['width']);
      $height = pwg_db_real_escape_string($value['height']);
      $imgW = pwg_db_real_escape_string($value['imageWidth']);
      $imgH = pwg_db_real_escape_string($value['imageHeight']);
      $rm = pwg_db_real_escape_string($value['removeThis']);
      $confirmed = pwg_db_real_escape_string($value['confirmed']);
      $prevConfirmed = pwg_db_real_escape_string($value['prevConfirmed']);

      // If it's a brand-new tag, we won't have sent a tag ID back with the data.
      if ($rm == 0 && ($existingTagId == -1 || $labeledTagName != $prevTagName) && $labeledTagName != 'Unidentified Person') {
        $newTagId = tag_id_from_tag_name($labeledTagName);
      } else {
        $newTagId = $existingTagId;
      }

      // Remove a mugshot
      if ($rm == 1) {
        if ($existingTagId != '' && $existingTagId != -1) {
          $deleteTagQuery .= $existingTagId . ',';
        } elseif ($faceIndex > 0) {
//        self::$MugShot_logger->info("Hiding MugShot ...", null, array('File'=>__FILE__,'Line'=>__LINE__,'Class'=>__CLASS__,
//            'Function'=>__FUNCTION__,'imageID'=>$imageId,'faceIndex'=>$faceIndex));
          $hideFaceQuery .= $faceIndex . ',';
        }
        continue;
      }

      // If something empty was submitted, just ignore it.
      if ($labeledTagName == '' || $labeledTagName == 'Unidentified Person') {
        continue;
      }

      // Add a mugshot
      if ($existingTagId == -1) {
        $faceTagPositionsInsertionString .= "('$newTagId','$imageId','$top','$left','$width','$height','$imgW','$imgH',TRUE),";
        $imageIdTagIdInsertionString .= "('$imageId','$newTagId'),";
      }

      // Update a mugshot
      if (($existingTagId != -1 && $existingTagId != $newTagId) || $prevConfirmed != $confirmed) {
        $sql = "UPDATE " . MUGSHOT_TABLE . " AS ftp SET ftp.tag_id='$newTagId', ftp.confirmed=$confirmed "
            ."WHERE ftp.image_id = '$imageId' AND ".($existingTagId == 0 ? "ftp.face_index = $faceIndex" : "ftp.tag_id = $existingTagId").";";
        pwg_query($sql);
        if ($existingTagId > 0) {
          $sql = "UPDATE " . IMAGE_TAG_TABLE . " AS pit SET pit.tag_id='$newTagId' "
              . "WHERE pit.image_id = '$imageId' AND pit.tag_id = '$existingTagId';";
          pwg_query($sql);
        } else {
          $imageIdTagIdInsertionString .= "('$imageId','$newTagId'),";
        }
      }
    }

    // Add new mugshot
    if ($faceTagPositionsInsertionString !== '') {
      $faceTagPositionsInsertionString = substr(trim($faceTagPositionsInsertionString), 0, -1);
      $frameSql = "INSERT INTO " . MUGSHOT_TABLE
          . " (`tag_id`, `image_id`, `top`, `lft`, `width`, `height`, `image_width`, `image_height`, `confirmed`) "
          . "VALUES $faceTagPositionsInsertionString ON DUPLICATE KEY UPDATE "
          . "`top`=VALUES(`top`), `lft`=VALUES(`lft`), `width`=VALUES(`width`), `height`=VALUES(`height`), "
          . "`image_width`=VALUES(`image_width`), `image_height`=VALUES(`image_height`), `confirmed`=VALUES(`confirmed`);";
      $frameResult = pwg_query($frameSql);
    } else {
      $frameResult = true;
    }
    if ($imageIdTagIdInsertionString !== '') {
      $imageIdTagIdInsertionString = substr(trim($imageIdTagIdInsertionString), 0, -1);
      $imageIdTagIdInsertionString = "INSERT IGNORE INTO " . IMAGE_TAG_TABLE
          . " (`image_id`, `tag_id`) VALUES $imageIdTagIdInsertionString;";
      $existingTagIdResult = pwg_query($imageIdTagIdInsertionString);
    } else {
      $existingTagIdResult = true;
    }

    // Delete mugshot
    if ($deleteTagQuery !== '') {
      $deleteTagQuery = '(' . substr(trim($deleteTagQuery), 0, -1) . ')';
      $deleteSql1 = "DELETE FROM " . MUGSHOT_TABLE
          ." WHERE `tag_id` IN $deleteTagQuery AND `image_id`='$imageId' AND (`face_index` IS NULL OR `face_index` = 0);";
      $deleteSql2 = "DELETE FROM " . IMAGE_TAG_TABLE
          ." WHERE `tag_id` IN $deleteTagQuery AND `image_id`='$imageId';";
      $dResult1 = pwg_query($deleteSql1);
      $dResult2 = pwg_query($deleteSql2);
    } else {
      $dResult1 = true;
      $dResult2 = true;
    }

    // Hide mugshot
    if ($hideFaceQuery !== '') {

      $hideFaceQuery = '(' . substr(trim($hideFaceQuery), 0, -1) . ')';
      // We don't actually remove detected entries from the database we just mark them to be ignored
      // Otherwise they'll reappear if detection is re-run on that photo
      $hideSql = "UPDATE " . MUGSHOT_TABLE . " AS ftp SET ftp.ignored = TRUE "
          ."WHERE ftp.face_index IN $hideFaceQuery AND ftp.image_id='$imageId';";
//    self::$MugShot_logger->info("Hide MugShot", null, array('File'=>__FILE__,'Line'=>__LINE__,'Class'=>__CLASS__,
//        'Function'=>__FUNCTION__,'ImageId'=>$imageId,'hideFaceQuery'=>$hideFaceQuery,'hideSql'=>$hideSql));
      $dResult3 = pwg_query($hideSql);
    } else {
      $dResult3 = true;
    }

    return json_encode([$existingTagIdResult, $frameResult, $dResult1, $dResult2, $dResult3]);
  }
}

global $conf;

MugShot_Services::$MugShot_logger = new Logger(array(
    'directory' => MUGSHOT_PATH . 'logs',
    'severity' => $conf['log_level'],
    'filename' => 'log_' . date('Y-m-d') . '_MugShot.log',
));
