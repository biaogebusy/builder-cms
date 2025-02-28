<?php

namespace Drupal\entity_print\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'entity_print_base64_image_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_print_base64_image_formatter",
 *   label = @Translation("Base64 Encoded Image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class Base64ImageFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ['image_style' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => image_style_options(FALSE),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    /** @var \Drupal\file\Entity\File $file */
    foreach ($items as $delta => $item) {
      $file = $item->entity;
      $uri = $file->getFileUri();

      // If we have a image style, use that instead.
      if ($this->getSetting('image_style')) {
        $image_style = ImageStyle::load($this->getSetting('image_style'));
        $uri = $this->getImageStyleUri($image_style, $file) ?: $uri;
      }

      $base_64_image = base64_encode(file_get_contents($uri));
      $filemime = $file->getMimeType();
      $element[$delta] = [
        '#theme' => 'image',
        '#uri' => "data:$filemime;charset=utf-8;base64,$base_64_image",
      ];
    }

    return $element;
  }

  /**
   * Gets the image style uri.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   The image style we want a URL for.
   * @param \Drupal\file\Entity\File $file
   *   The file object.
   *
   * @return bool|string
   *   A uri for this image style.
   *
   * @todo We should use ImageStyleDownloadController once core is fixed.
   * Currently this code does not acquire a look to generate the derivative and
   * may cause issues on high traffic sites with multiple web heads.
   *
   * @see https://drupal.org/node/1220116
   */
  protected function getImageStyleUri(ImageStyle $image_style, File $file) {
    $file_uri = $file->getFileUri();
    $image_style_uri = $image_style->buildUri($file_uri);
    if (file_exists($image_style_uri) || $image_style->createDerivative($file_uri, $image_style_uri)) {
      return $image_style_uri;
    }

    return FALSE;
  }

}
