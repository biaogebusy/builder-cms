<?php

namespace Drupal\ckeditor_templates_ui;

use Drupal\ckeditor5_template\Plugin\CKEditor5Plugin\Template;
use Drupal\editor\EditorInterface;
use Drupal\Core\Form\FormStateInterface;

class CkeditorTemplatesUi extends Template {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Disable template path option.
    $form['file_path']['#disabled'] = TRUE;
    $form['file_path']['#description'] .= ' (' . t('Note: This option will not work when CKeditor templates UI module is enabled.') . ')';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);
    $query = \Drupal::entityTypeManager()->getStorage('ckeditor_template')->getQuery();
    $templates_ids = $query->execute();
    $ckeditor_templates = \Drupal::entityTypeManager()->getStorage('ckeditor_template')->loadMultiple($templates_ids);
    // Sorting in postLoad does not stick.
    uasort($ckeditor_templates, 'Drupal\Core\Config\Entity\ConfigEntityBase::sort');
    $i = 0;
    $templates = [];
    foreach ($ckeditor_templates as $value) {
      $templates[$i]['title'] = $value->label;
      $templates[$i]['description'] = $value->description;
      if ($value->icon) {
        // The CKEditor Templates plugin requires a "imagesPath" parameter
        // that cannot evaluate to false, is the same for all templates and
        // is used to create the image path. This makes it inconvenient for us.
        // For things to work out all url-s must start with "/".
        $templates[$i]['icon'] = \Drupal::service('file_url_generator')->generateAbsoluteString($value->icon);
        $templates[$i]['icon'] = \Drupal::service('file_url_generator')->transformRelative($templates[$i]['icon']);
        // Remove leading "/" since it will be added separately
        // by the "imagesPath" parameter.
        // $templates[$i]['icon'] = substr($templates[$i]['icon'], 1);
      }
      $templates[$i]['html'] = $value->html['value'];
      $i++;
    }
    $static_plugin_config['template']['templates'] = $templates;
    return $static_plugin_config;
  }

}
