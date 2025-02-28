<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\file_mdm\FileMetadataManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Automatically adjusts the orientation of an image resource.
 *
 * Uses EXIF Orientation tags to determine the image orientation.
 * EXIF: https://en.wikipedia.org/wiki/Exchangeable_image_file_format.
 * EXIF orientation tag: http://sylvana.net/jpegcrop/exif_orientation.html.
 *
 * Originally contributed to the imagecache_actions module by jonathan_hunt
 * https://drupal.org/user/28976, September 1, 2009.
 *
 * @ImageEffect(
 *   id = "image_effects_auto_orient",
 *   label = @Translation("Automatically correct orientation"),
 *   description = @Translation("Automatically rotates images according to orientation flag set by many phones and digital cameras.")
 * )
 */
class AutoOrientImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * The file metadata manager service.
   *
   * @var \Drupal\file_mdm\FileMetadataManagerInterface
   */
  protected $fileMetadataManager;

  /**
   * Constructs an AutoOrientImageEffect object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\file_mdm\FileMetadataManagerInterface $file_metadata_manager
   *   The file metadata manager service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, LoggerInterface $logger, FileMetadataManagerInterface $file_metadata_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->fileMetadataManager = $file_metadata_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('image'),
      $container->get('file_metadata_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'scan_exif' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#type'  => 'details',
      '#title' => $this->t('Information'),
    ];
    $form['info']['help'] = [
      '#markup' => $this->t("<p>Certain cameras can embed <em>orientation</em> information into image
        files when they save them. This information is embedded in an EXIF tag
        and can be used to rotate images to their correct position for display.
        <em>Not all cameras or images contain this information.</em>
        This process is only useful for images that contain this information,
        whereas for other images it is harmless.
        </p>
        <p>Although most modern browsers do support the orientation tag, the
        information may get lost or become incorrect by other operations.
        So, to support all browsers and prevent rotation errors, it is better to
        start each image style with this effect.
        </p>
        <p>The expected/supported values are:<br/>
        <strong>Tag</strong>: <code>0x0112  Orientation</code>
        </p>
        <ul>
        <li>1 = Horizontal (normal)</li>
        <li>3 = Rotate 180</li>
        <li>6 = Rotate 90 CW</li>
        <li>8 = Rotate 270 CW</li>
        </ul>
        <p>Wikipedia: <a href='https://en.wikipedia.org/wiki/Exchangeable_image_file_format'>Exchangeable image file format</a></p>
      "),
    ];

    $form['scan_exif'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Scan image file'),
      '#description' => $this->t('When selected, original image files supporting EXIF data (e.g. JPEG, TIFF) will be scanned to determine styled image orientation and dimensions. This slightly impacts performance, but allows to render more accurate HTML <kbd>&lt;img&gt;</kbd> tags.'),
      '#default_value' => $this->configuration['scan_exif'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['scan_exif'] = $form_state->getValue('scan_exif');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Test to see if EXIF is supported by the image format.
    if (in_array($image->getMimeType(), ['image/jpeg', 'image/tiff'])) {
      // Hand over to toolkit.
      return $image->apply('auto_orient');
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    if ($dimensions['width'] && $dimensions['height'] && $this->configuration['scan_exif']) {
      // Both dimensions in input, and effect is configured to check the
      // the input file. Read EXIF data, and determine image orientation.
      $file = $this->fileMetadataManager->uri($uri);
      $exif_orientation = $file->getMetadata('exif', 'Orientation');
      if ($exif_orientation && in_array($exif_orientation['value'], [5, 6, 7, 8])) {
        $tmp = $dimensions['width'];
        $dimensions['width'] = $dimensions['height'];
        $dimensions['height'] = $tmp;
      }
      return;
    }
    // Either no full dimensions in input, or effect is configured to skip
    // checking the input file. Set both dimensions to NULL.
    $dimensions['width'] = $dimensions['height'] = NULL;
  }

}
