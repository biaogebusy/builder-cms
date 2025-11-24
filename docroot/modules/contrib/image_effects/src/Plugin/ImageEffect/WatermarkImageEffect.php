<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Component\ImageUtility;
use Drupal\image_effects\Plugin\ImageEffectsPluginBaseInterface;
use Drupal\image_effects\Plugin\ImageSelectorPluginManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds watermark image.
 */
#[ImageEffect(
  id: 'image_effects_watermark',
  label: new TranslatableMarkup('Watermark'),
  description: new TranslatableMarkup('Add watermark image effect.'),
)]
class WatermarkImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  use AnchorTrait;

  /**
   * WatermarkImageEffect constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Image\ImageFactory $imageFactory
   *   The image factory service.
   * @param \Drupal\image_effects\Plugin\ImageEffectsPluginBaseInterface $imageSelector
   *   The image selector plugin.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    LoggerInterface $logger,
    protected readonly ImageFactory $imageFactory,
    protected readonly ImageEffectsPluginBaseInterface $imageSelector,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
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
      $container->get('image.factory'),
      $container->get(ImageSelectorPluginManager::class)->getPlugin()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'watermark_image' => '',
      'watermark_width' => NULL,
      'watermark_height' => NULL,
      'placement' => 'center-center',
      'x_offset' => 0,
      'y_offset' => 0,
      'opacity' => 100,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = [
      '#theme' => 'image_effects_watermark_summary',
      '#data' => $this->configuration,
    ];
    $summary += parent::getSummary();

    // Get the human readable label for placement.
    $summary['#data']['placement'] = $this->anchorOptions()[$summary['#data']['placement']];

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [
      '#title' => $this->t('Watermark image'),
      '#description' => $this->t('Image to use as watermark.'),
      '#default_value' => $this->configuration['watermark_image'],
    ];
    $form['watermark_image'] = $this->imageSelector->selectionElement($options);
    $form['watermark_resize'] = [
      '#type' => 'details',
      '#title' => $this->t('Watermark resize'),
      '#description' => $this->t('Select dimensions either in pixels or as percentage of the <strong>current canvas</strong>. Leaving one dimension empty will resize the watermark maintaing its aspect ratio. Leave both dimensions empty to apply the watermark in its original dimensions.'),
      '#open' => TRUE,
    ];
    $form['watermark_resize']['watermark_width'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Watermark width'),
      '#default_value' => $this->configuration['watermark_width'],
      '#size' => 5,
      '#maxlength' => 5,
      '#required' => FALSE,
    ];
    $form['watermark_resize']['watermark_height'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Watermark height'),
      '#default_value' => $this->configuration['watermark_height'],
      '#size' => 5,
      '#maxlength' => 5,
      '#required' => FALSE,
    ];
    $form['placement'] = [
      '#type' => 'radios',
      '#title' => $this->t('Placement'),
      '#options' => $this->anchorOptions(),
      '#theme' => 'image_anchor',
      '#default_value' => $this->configuration['placement'],
      '#description' => $this->t('Position of the watermark on the canvas.'),
    ];
    $form['x_offset'] = [
      '#type'  => 'image_effects_px_perc',
      '#title' => $this->t('Horizontal offset'),
      '#description'   => $this->t("Additional horizontal offset from placement. Enter a value, and specify if pixels or percent of the canvas width. '+' or no sign shifts the watermark rightward, '-' sign leftward."),
      '#default_value' => $this->configuration['x_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['y_offset'] = [
      '#type'  => 'image_effects_px_perc',
      '#title' => $this->t('Vertical offset'),
      '#description'   => $this->t("Additional vertical offset from placement. Enter a value, and specify if pixels or percent of the canvas height. '+' or no sign shifts the watermark downward, '-' sign upward."),
      '#default_value' => $this->configuration['y_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Opacity'),
      '#field_suffix' => '%',
      '#description' => $this->t('Opacity, in percentage, of the watermark on the canvas. 0% is fully transparent, 100% is fully opaque.'),
      '#default_value' => $this->configuration['opacity'],
      '#min' => 0,
      '#max' => 100,
      '#maxlength' => 3,
      '#size' => 3,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['watermark_image'] = $form_state->getValue('watermark_image');
    $this->configuration['watermark_width'] = $form_state->getValue(['watermark_resize', 'watermark_width']);
    $this->configuration['watermark_height'] = $form_state->getValue(['watermark_resize', 'watermark_height']);
    $this->configuration['placement'] = $form_state->getValue('placement');
    $this->configuration['x_offset'] = $form_state->getValue('x_offset');
    $this->configuration['y_offset'] = $form_state->getValue('y_offset');
    $this->configuration['opacity'] = $form_state->getValue('opacity');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Get the watermark image object.
    $watermark_image = $this->imageFactory->get($this->configuration['watermark_image']);
    if (!$watermark_image->isValid()) {
      $this->logger->error('Image watermark failed using the %toolkit toolkit on %path', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $this->configuration['watermark_image'],
      ]);
      return FALSE;
    }

    // Determine watermark dimensions if they need to be changed.
    if ((bool) $this->configuration['watermark_width'] || (bool) $this->configuration['watermark_height']) {
      $watermark_aspect = $watermark_image->getHeight() / $watermark_image->getWidth();
      $watermark_width = ImageUtility::percentFilter($this->configuration['watermark_width'], $image->getWidth());
      $watermark_height = ImageUtility::percentFilter($this->configuration['watermark_height'], $image->getHeight());
      if ($watermark_width && !$watermark_height) {
        $watermark_height = (int) round($watermark_width * $watermark_aspect);
      }
      elseif (!$watermark_width && $watermark_height) {
        $watermark_width = (int) round($watermark_height / $watermark_aspect);
      }
    }
    else {
      $watermark_width = $watermark_image->getWidth();
      $watermark_height = $watermark_image->getHeight();
    }

    // Calculate position of watermark on source image based on placement
    // option.
    [$x, $y] = explode('-', $this->configuration['placement']);
    $x_pos = ImageUtility::getKeywordOffset($x, $image->getWidth(), $watermark_width);
    $y_pos = ImageUtility::getKeywordOffset($y, $image->getHeight(), $watermark_height);

    // Calculate offset based on px/percentage.
    $x_offset = (int) ImageUtility::percentFilter($this->configuration['x_offset'], $image->getWidth());
    $y_offset = (int) ImageUtility::percentFilter($this->configuration['y_offset'], $image->getHeight());

    return $image->apply('watermark', [
      'watermark_image' => $watermark_image,
      'watermark_width' => $watermark_width !== $watermark_image->getWidth() ? $watermark_width : NULL,
      'watermark_height' => $watermark_height !== $watermark_image->getHeight() ? $watermark_height : NULL,
      'x_offset' => $x_pos + $x_offset,
      'y_offset' => $y_pos + $y_offset,
      'opacity' => $this->configuration['opacity'],
    ]);
  }

}
