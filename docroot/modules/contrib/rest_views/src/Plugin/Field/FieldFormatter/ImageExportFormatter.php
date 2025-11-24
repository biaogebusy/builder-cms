<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\rest_views\SerializedData;

/**
 * Process an image through an image style, and render the URL.
 *
 * @FieldFormatter(
 *   id = "image_export",
 *   label = @Translation("Export image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageExportFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = [
      'export_alt'   => FALSE,
      'export_title' => FALSE,
    ] + parent::defaultSettings();
    unset($settings['image_link']);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState): array {
    $form = parent::settingsForm($form, $formState);
    unset($form['image_link']);

    $alt = $this->getFieldSetting('alt_field');
    $title = $this->getFieldSetting('title_field');

    if ($alt) {
      $form['export_alt'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Export <em>Alt</em> field'),
        '#description'   => $this->t('Enabling this will export an object instead of a string.'),
        '#default_value' => $this->getSetting('export_alt'),
      ];
    }
    else {
      $form['export_alt'] = ['#type' => 'value', '#value' => FALSE];
    }
    if ($title) {
      $form['export_title'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Export <em>Title</em> field'),
        '#description'   => $this->t('Enabling this will export an object instead of a string.'),
        '#default_value' => $this->getSetting('export_title'),
      ];
    }
    else {
      $form['export_title'] = ['#type' => 'value', '#value' => FALSE];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    if ($this->getSetting('export_alt')) {
      $summary[] = $this->t('<em>Alt</em> field is exported.');
    }
    if ($this->getSetting('export_title')) {
      $summary[] = $this->t('<em>Title</em> field is exported.');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    $alt = $this->getSetting('export_alt');
    $title = $this->getSetting('export_title');

    foreach ($elements as $delta => $element) {
      $item = $element['#item'];
      if (($entity = $item->entity) && empty($item->uri)) {
        /** @var \Drupal\file\FileInterface $entity */
        $uri = $entity->getFileUri();
      }
      else {
        $uri = $item->uri;
      }

      if ($element['#image_style']) {
        /** @var \Drupal\image\ImageStyleInterface $style */
        $style = ImageStyle::load($element['#image_style']);

        // Determine the dimensions of the styled image.
        $dimensions = [
          'width'  => $item->width,
          'height' => $item->height,
        ];

        $style->transformDimensions($dimensions, $uri);
        $uri = $style->buildUrl($uri);
      }
      else {
        $uri = $this->fileUrlGenerator->generateAbsoluteString($uri);
      }

      if ($alt || $title) {
        $data = ['url' => $uri];
        if ($alt) {
          $data['alt'] = $item->alt;
        }
        if ($title && $item->title !== '') {
          $data['title'] = $item->title;
        }
        $elements[$delta] = [
          '#type' => 'data',
          '#data' => SerializedData::create($data),
        ];
      }
      else {
        $elements[$delta] = [
          '#type' => 'data',
          '#data' => SerializedData::create($uri),
        ];
      }
    }

    return $elements;
  }

}
