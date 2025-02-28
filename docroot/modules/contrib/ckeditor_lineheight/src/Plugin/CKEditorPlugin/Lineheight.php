<?php

namespace Drupal\ckeditor_lineheight\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "Line Height" plugin.
 *
 * @CKEditorPlugin(
 *   id = "lineheight",
 *   label = @Translation("CKEditor Line Height"),
 *   module = "ckeditor_lineheight"
 * )
 */
class Lineheight extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    // Maintain backward compatibility with path in previous documentation.
    if (file_exists(DRUPAL_ROOT . '/libraries/ckeditor/plugins/lineheight/plugin.js')) {
      return 'libraries/ckeditor/plugins/lineheight/plugin.js';
    }
    return 'libraries/lineheight/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'lineheight' => [
        'label' => $this->t('Line height'),
        'image' => drupal_get_path('module', 'ckeditor_lineheight') . '/icons/lineheight.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'line_height' => "1px;2px;3px;4px;5px;6px;7px;8px;9px;10px;11px;12px;13px;14px;15px;16px;17px;18px;19px;20px;21px;22px;23px;24px;25px;26px;27px;28px;29px;30px;31px;32px;33px;34px;35px;36px;37px;38px;39px;40px;41px;42px;43px;44px;45px;46px;47px;48px;49px;50px;51px;52px;53px;54px;55px;56px;57px;58px;59px;60px;61px;62px;63px;64px;65px;66px;67px;68px;69px;70px;71px;72px"
    ];
}}
