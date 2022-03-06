<?php

class MugShot_Events
{
  public static $MugShot_logger;

  static function blockmanager_register_blocks($menu_ref_arr)
  {
    $menu = &$menu_ref_arr[0];

    //self::$MugShot_logger->info("blockmanager_register_blocks:", null, array(var_export($menu, true)));

    if ($menu->get_id() == 'menubar')
    {
      // identifier, title, owner
      $menu->register_block(new RegisteredBlock('mbMugShot', l10n('MugShot'), 'MugShot'));
    }
  }

  static function blockmanager_apply($menu_ref_arr)
  {
    global $tokens, $page;

    $menu = &$menu_ref_arr[0];

    // self::$MugShot_logger->info("blockmanager_apply:", null, array(var_export($menu, true)));

    if (($block = $menu->get_block('mbMugShot')) != null)
    {
      $block->data[] = array(
          'URL' => make_index_url(array('section' => 'unidentified')),
          'TITLE' => l10n('Photos with detected faces that haven\'t been identified'),
          'NAME' => l10n('Unidentified People'),
      );
      $block->data[] = array(
          'URL' => make_index_url(array('section' => 'unconfirmed')),
          'TITLE' => l10n('Photos with faces that appear to match people in other photos but haven\'t been confirmed'),
          'NAME' => l10n('Unconfirmed Matched People'),
      );
    }
    $block->template = realpath(MUGSHOT_PATH . 'template/menubar.tpl');
  }

  static function blockmanager_prepare_display($menu_ref_arr)
  {
    global $tokens, $page;

    $menu = &$menu_ref_arr[0];

    // self::$MugShot_logger->info("blockmanager_prepare_display:", null, array(var_export($menu, true)));

    if (($block = $menu->get_block('mbIdentification')) != null)
    {
      $pos = $block->get_position();

      if (($block = $menu->get_block('mbMugShot')) != null) {
        $block->set_position($pos - 10);
      }
    }
  }

  static function loc_end_section_init()
  {
    global $tokens, $page, $conf;

    // self::$MugShot_logger->info("loc_end_section_init - $tokens[0]", null, $page);

    if ($tokens[0] == 'unidentified')
    {
      // section_title is for breadcrumb, title is for page <title>
      $page['section'] = 'unidentified';
      $page['section_title'] = '<a href="'.get_absolute_root_url().'">'.l10n('Home').'</a>'.$conf['level_separator'].'<a href="'.make_index_url(array('section' => 'unidentified')).'">'.l10n('Unidentified').'</a>';
      $page['title'] = '<a href="'.duplicate_index_url(array('start' => 0)).'">'.l10n('Unidentified People').'</a>';

      $query = '
        SELECT id AS image_id FROM '.IMAGES_TABLE.' where id IN (
          SELECT DISTINCT image_id FROM '.MUGSHOT_TABLE.' WHERE tag_id IS NULL AND NOT ignored = 1
        )
      '.get_sql_condition_FandF(array('visible_images' => 'id'),'AND').' '.$conf['order_by'].';';

      // self::$MugShot_logger->info("unidentified", null, array($query));

      $page = array_merge(
          $page,
          array('items' => query2array($query, null, 'image_id'),)
      );
    } elseif ($tokens[0] == 'unconfirmed') {
      $page['section'] = 'unconfirmed';
      $page['section_title'] = '<a href="'.get_absolute_root_url().'">'.l10n('Home').'</a>'.$conf['level_separator'].'<a href="'.make_index_url(array('section' => 'unconfirmed')).'">'.l10n('unconfirmed').'</a>';
      $page['title'] = '<a href="'.duplicate_index_url(array('start' => 0)).'">'.l10n('Unconfirmed Matched People').'</a>';

      $query = '
        SELECT id AS image_id FROM '.IMAGES_TABLE.' where id IN (
          SELECT DISTINCT image_id FROM '.MUGSHOT_TABLE.' WHERE tag_id IS NOT NULL AND NOT confirmed = 1
        )
      '.get_sql_condition_FandF(array('visible_images' => 'id'),'AND').' '.$conf['order_by'].';';

      $page = array_merge(
        $page,
        array('items' => query2array($query, null, 'image_id'),)
      );
    }
  }
}

global $conf;

MugShot_Events::$MugShot_logger = new Logger(array(
    'directory' => MUGSHOT_PATH . 'logs',
    'severity' => $conf['log_level'],
    'filename' => 'log_' . date('Y-m-d') . '_MugShot.log',
));
