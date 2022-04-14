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
    $unescaped_data = str_replace('\\', '', $data['data']);

    self::$MugShot_logger->info("Entered ...", null, array('File'=>__FILE__, 'Line'=>__LINE__, 'Class'=>__CLASS__,
        'Function'=>__FUNCTION__,'data'=>$unescaped_data));

    $request = json_decode($unescaped_data, true);

    if (!$service -> isPost()) {
      return new PwgError(405, "HTTP POST REQUIRED");
    }

    $imageId = $request['imageId'];

    unset($request['imageId']);
    $imageIdTagIdInsertionString = '';        //
    $faceTagPositionsInsertionString = '';    // Variable string. Groups data for entry in SQL database.
    $deleteFaceIdQuery = '';                  // Delete string. Id in face_tag_positions in the current image being removed.
    $deleteTagQuery = '';                     // Delete string. Tags in the current image being removed.
    $hideFaceIdQuery = '';                    // Hidden string. Faces in the currant image being soft-removed.
    $removeMatchFaceIdQuery = '';             // Rejected Match. Remove the tag from face_tag_positions.

    foreach ($request as $key => $value) {
      self::$MugShot_logger->info("client data received - ".$key, null, array(var_export($value, true)));

      $faceId = $value['faceId'];

      if ($value['name'] != null && $value['name'] != '') {
        $labeledTagName = self::get_pretty_name($value['name']);
      } else {
        $labeledTagName = null;
      }

      $prevTagName = $value['prevName'];

      $existingTagId = $value['tagId'];
      $faceIndex = $value['faceIndex'];
      $top = $value['top'];
      $left = $value['left'];
      $width = $value['width'];
      $height = $value['height'];
      $imgW = $value['imageWidth'];
      $imgH = $value['imageHeight'];
      $rm = $value['removeThis'];
      $confirmed = $value['confirmed'];
      $prevConfirmed = $value['prevConfirmed'];

      // Remove a mugshot
      if ($rm == 1) {
        if ($faceIndex > 0) {
//        self::$MugShot_logger->info("Hiding MugShot ...", null, array('File'=>__FILE__,'Line'=>__LINE__,'Class'=>__CLASS__,
//            'Function'=>__FUNCTION__,'imageID'=>$imageId,'faceIndex'=>$faceIndex));
          $hideFaceIdQuery .= $faceId . ',';
        } else {
          $deleteFaceIdQuery .= $faceId . ',';
        }
        if ($existingTagId != null) {
          $deleteTagQuery .= $existingTagId . ',';
        }
        continue;
      }

      // If something empty was submitted, just ignore it.
      if ($faceId == null && ($labeledTagName == null || $labeledTagName == '')) {
        continue;
      }

      // Handle a rejected match
      if ($labeledTagName == null && $existingTagId != null) {
        $removeMatchFaceIdQuery .= $faceId . ',';
        continue;
      }

      // If it's a brand-new tag, there won't be a tag ID.
      if ($labeledTagName != null && $labeledTagName != $prevTagName) {
        $newTagId = tag_id_from_tag_name($labeledTagName);
      } else {
        $newTagId = $existingTagId;
      }

      if ($faceId == null && $existingTagId == null) {
        // Add a new mugshot
        $faceTagPositionsInsertionString .= "('$newTagId','$imageId','$top','$left','$width','$height','$imgW','$imgH',TRUE),";
        $imageIdTagIdInsertionString .= "('$imageId','$newTagId'),";
      } elseif ($existingTagId != $newTagId || $prevConfirmed != $confirmed) {
        // Update a mugshot
        $sql = "UPDATE " . MUGSHOT_TABLE . " AS ftp SET ftp.tag_id = $newTagId, ftp.confirmed = $confirmed "
            ."WHERE ftp.id = $faceId;";
        pwg_query($sql);
        if ($existingTagId > 0) {
          $sql = "UPDATE " . IMAGE_TAG_TABLE . " AS pit SET pit.tag_id = $newTagId "
              . "WHERE pit.image_id = $imageId AND pit.tag_id = $existingTagId;";
          pwg_query($sql);
          if (pwg_db_changes() == 0) {
            $imageIdTagIdInsertionString .= "('$imageId','$newTagId'),";
          }
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
    if ($deleteFaceIdQuery !== '') {
      $deleteFaceIdQuery = '(' . substr(trim($deleteFaceIdQuery), 0, -1) . ')';
      $deleteSql1 = "DELETE FROM " . MUGSHOT_TABLE. " WHERE `id` IN $deleteFaceIdQuery;";
      $dResult1 = pwg_query($deleteSql1);
    } else {
      $dResult1 = true;
    }

    if ($deleteTagQuery !== '') {
      $deleteTagQuery = '(' . substr(trim($deleteTagQuery), 0, -1) . ')';
      $deleteSql2 = "DELETE FROM " . IMAGE_TAG_TABLE
          ." WHERE `tag_id` IN $deleteTagQuery AND `image_id` = $imageId;";
      $dResult2 = pwg_query($deleteSql2);
    } else {
      $dResult2 = true;
    }

    // Hide mugshot
    if ($hideFaceIdQuery !== '') {
      $hideFaceIdQuery = '(' . substr(trim($hideFaceIdQuery), 0, -1) . ')';
      // We don't actually remove detected entries from the database we just mark them to be ignored
      // Otherwise they'll reappear if detection is re-run on that photo
      $hideSql = "UPDATE " . MUGSHOT_TABLE . " AS ftp SET ftp.ignored = TRUE "
          ."WHERE ftp.id IN $hideFaceIdQuery;";
//    self::$MugShot_logger->info("Hide MugShot", null, array('File'=>__FILE__,'Line'=>__LINE__,'Class'=>__CLASS__,
//        'Function'=>__FUNCTION__,'ImageId'=>$imageId,'hideFaceQuery'=>$hideFaceQuery,'hideSql'=>$hideSql));
      $dResult3 = pwg_query($hideSql);
    } else {
      $dResult3 = true;
    }

    // Handle Rejected Match by removing tag
    if ($removeMatchFaceIdQuery !== '') {
      $removeMatchFaceIdQuery = '(' . substr(trim($removeMatchFaceIdQuery), 0, -1) . ')';
      $removeMatchSql = "UPDATE " . MUGSHOT_TABLE . " AS ftp SET ftp.tag_id = null "
          ."WHERE ftp.id IN $removeMatchFaceIdQuery;";
      $dResult4 = pwg_query($removeMatchSql);
    } else {
      $dResult4 = true;
    }

    return json_encode([$existingTagIdResult, $frameResult, $dResult1, $dResult2, $dResult3, $dResult4]);
  }
}

global $conf;

MugShot_Services::$MugShot_logger = new Logger(array(
    'directory' => MUGSHOT_PATH . 'logs',
    'severity' => $conf['log_level'],
    'filename' => 'log_' . date('Y-m-d') . '_MugShot.log',
));
