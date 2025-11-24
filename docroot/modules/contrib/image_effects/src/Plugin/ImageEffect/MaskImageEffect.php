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
 * Apply a mask to the image.
 */
#[ImageEffect(
  id: 'image_effects_mask',
  label: new TranslatableMarkup('Mask'),
  description: new TranslatableMarkup('Apply a mask to the image.'),
)]
class MaskImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  use AnchorTrait;

  /**
   * MaskImageEffect constructor.
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
      'mask_image' => '',
      'mask_width' => NULL,
      'mask_height' => NULL,
      'placement' => 'center-center',
      'x_offset' => NULL,
      'y_offset' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = [
      '#theme' => 'image_effects_mask_summary',
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
      '#title' => $this->t('Mask image'),
      '#description' => $this->t('Image to use for masking. The mask file should be a grayscale image, where full white pixels will let the original image through, and full black pixels will hide it.'),
      '#default_value' => $this->configuration['mask_image'],
      '#required' => TRUE,
    ];
    $form['mask_image'] = $this->imageSelector->selectionElement($options);
    $form['mask_resize'] = [
      '#type' => 'details',
      '#title' => $this->t('Mask resize'),
      '#description' => $this->t('Select dimensions either in pixels or as percentage of the <strong>current canvas</strong>. Leaving one dimension empty will resize the mask maintaing its aspect ratio. Leave both dimensions empty to apply the mask in its original dimensions.'),
      '#open' => TRUE,
    ];
    $form['mask_resize']['mask_width'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Mask width'),
      '#default_value' => $this->configuration['mask_width'],
      '#size' => 5,
      '#maxlength' => 5,
      '#required' => FALSE,
    ];
    $form['mask_resize']['mask_height'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Mask height'),
      '#default_value' => $this->configuration['mask_height'],
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
      '#description' => $this->t('Position of the mask on the canvas.'),
      '#required' => TRUE,
    ];
    $form['x_offset'] = [
      '#type'  => 'image_effects_px_perc',
      '#title' => $this->t('Horizontal offset'),
      '#description'   => $this->t("Additional horizontal offset from placement. Enter a value, and specify if pixels or percent of the canvas width. '+' or no sign shifts the mask rightward, '-' sign leftward."),
      '#default_value' => $this->configuration['x_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['y_offset'] = [
      '#type'  => 'image_effects_px_perc',
      '#title' => $this->t('Vertical offset'),
      '#description'   => $this->t("Additional vertical offset from placement. Enter a value, and specify if pixels or percent of the canvas height. '+' or no sign shifts the mask downward, '-' sign upward."),
      '#default_value' => $this->configuration['y_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['mask_image'] = $form_state->getValue('mask_image');
    $this->configuration['mask_width'] = $form_state->getValue(['mask_resize', 'mask_width']);
    $this->configuration['mask_height'] = $form_state->getValue(['mask_resize', 'mask_height']);
    $this->configuration['placement'] = $form_state->getValue('placement');
    $this->configuration['x_offset'] = $form_state->getValue('x_offset');
    $this->configuration['y_offset'] = $form_state->getValue('y_offset');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Get the mask image object.
    $mask_image = $this->imageFactory->get($this->configuration['mask_image']);
    if (!$mask_image->isValid()) {
      $this->logger->error('Image mask failed using the %toolkit toolkit on %path', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $this->configuration['mask_image'],
      ]);
      return FALSE;
    }

    // Determine mask dimensions if they need to be changed.
    if ((bool) $this->configuration['mask_width'] || (bool) $this->configuration['mask_height']) {
      $mask_aspect = $mask_image->getHeight() / $mask_image->getWidth();
      $mask_width = ImageUtility::percentFilter($this->configuration['mask_width'], $image->getWidth());
      $mask_height = ImageUtility::percentFilter($this->configuration['mask_height'], $image->getHeight());
      if ($mask_width && !$mask_height) {
        $mask_height = (int) round($mask_width * $mask_aspect);
      }
      elseif (!$mask_width && $mask_height) {
        $mask_width = (int) round($mask_height / $mask_aspect);
      }
    }
    else {
      $mask_width = $mask_image->getWidth();
      $mask_height = $mask_image->getHeight();
    }

    // Calculate position of mask on source image based on placement option.
    [$x, $y] = explode('-', $this->configuration['placement']);
    $x_pos = ImageUtility::getKeywordOffset($x, $image->getWidth(), $mask_width);
    $y_pos = ImageUtility::getKeywordOffset($y, $image->getHeight(), $mask_height);

    // Calculate offset based on px/percentage.
    $x_offset = (int) ImageUtility::percentFilter($this->configuration['x_offset'], $image->getWidth());
    $y_offset = (int) ImageUtility::percentFilter($this->configuration['y_offset'], $image->getHeight());

    return $image->apply('mask', [
      'mask_image' => $mask_image,
      'mask_width' => $mask_width !== $mask_image->getWidth() ? $mask_width : NULL,
      'mask_height' => $mask_height !== $mask_image->getHeight() ? $mask_height : NULL,
      'x_offset' => $x_pos + $x_offset,
      'y_offset' => $y_pos + $y_offset,
    ]);
  }

}
