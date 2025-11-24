<?php

declare(strict_types=1);

namespace Drupal\video_embed_wysiwyg\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;
use Drupal\video_embed_field\Plugin\Field\FieldFormatter\Video;

/**
 * CKEditor 5 VideoEmbedWysiwyg plugin configuration.
 */
class VideoEmbedWysiwyg extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * {@inheritDoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    // Transmit some useful Drupal data to the javascript plugin.
    $parent_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);
    return array_merge_recursive($parent_config,
      [
        'videoEmbed' => [
          // Used by VideoEmbedUi.openEditingDialog().
          'format' => $editor->id(),
        ],
      ]);
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration(): array {
    return [
      'defaults' => [
        'children' => [
          'responsive' => TRUE,
          'width' => '854',
          'height' => '480',
          'autoplay' => TRUE,
          'title_format' => '@provider | @title',
          'title_fallback' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['defaults'] = [
      '#title' => $this->t('Default Settings'),
      '#type' => 'fieldset',
      '#tree' => TRUE,
      'children' => Video::mockInstance($this->configuration['defaults']['children'])
        ->settingsForm([], new FormState()),
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $value = $form_state->getValue('defaults');
    $value['children']['autoplay'] = (bool) $value['children']['autoplay'];
    $value['children']['responsive'] = (bool) $value['children']['responsive'];
    $form_state->setValue('defaults', $value);
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['defaults'] = $form_state->getValue('defaults');
  }

}
