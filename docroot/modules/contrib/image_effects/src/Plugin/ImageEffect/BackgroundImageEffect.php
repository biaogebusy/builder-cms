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
 * Places the source image anywhere over a selected background image.
 */
#[ImageEffect(
  id: 'image_effects_background',
  label: new TranslatableMarkup('Background'),
  description: new TranslatableMarkup('Places the source image anywhere over a selected background image.'),
)]
class BackgroundImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * BackgroundImageEffect constructor.
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
      'placement' => 'center-center',
      'x_offset' => 0,
      'y_offset' => 0,
      'opacity' => 100,
      'background_image' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = [
      '#theme' => 'image_effects_background_summary',
      '#data' => $this->configuration,
    ];
    $summary += parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [
      '#title' => $this->t('Background image'),
      '#description' => $this->t('Image to use for background.'),
      '#default_value' => $this->configuration['background_image'],
    ];
    $form['background_image'] = $this->imageSelector->selectionElement($options);
    $form['placement'] = [
      '#type' => 'radios',
      '#title' => $this->t('Placement'),
      '#options' => [
        'left-top' => $this->t('Top left'),
        'center-top' => $this->t('Top center'),
        'right-top' => $this->t('Top right'),
        'left-center' => $this->t('Center left'),
        'center-center' => $this->t('Center'),
        'right-center' => $this->t('Center right'),
        'left-bottom' => $this->t('Bottom left'),
        'center-bottom' => $this->t('Bottom center'),
        'right-bottom' => $this->t('Bottom right'),
      ],
      '#theme' => 'image_anchor',
      '#default_value' => $this->configuration['placement'],
      '#description' => $this->t('Position of the source image on the background image.'),
    ];
    $form['x_offset'] = [
      '#type'  => 'number',
      '#title' => $this->t('Horizontal offset'),
      '#field_suffix'  => 'px',
      '#description'   => $this->t('Additional horizontal offset from placement.'),
      '#default_value' => $this->configuration['x_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['y_offset'] = [
      '#type'  => 'number',
      '#title' => $this->t('Vertical offset'),
      '#field_suffix'  => 'px',
      '#description'   => $this->t('Additional vertical offset from placement.'),
      '#default_value' => $this->configuration['y_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Opacity'),
      '#field_suffix' => '%',
      '#description' => $this->t('Opacity of the source image when overlaid on the background image, in percentage. 0% means fully transparent, 100% fully opaque.'),
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
    $this->configuration['placement'] = $form_state->getValue('placement');
    $this->configuration['x_offset'] = $form_state->getValue('x_offset');
    $this->configuration['y_offset'] = $form_state->getValue('y_offset');
    $this->configuration['opacity'] = $form_state->getValue('opacity');
    $this->configuration['background_image'] = $form_state->getValue('background_image');

    // Stores background image width and height in configuration to avoid the
    // need to fetch the image from storage in ::transformDimensions.
    $background_image = $this->imageFactory->get($this->configuration['background_image']);
    $this->configuration['background_image_width'] = $background_image->getWidth();
    $this->configuration['background_image_height'] = $background_image->getHeight();
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $background_image = $this->imageFactory->get($this->configuration['background_image']);
    if (!$background_image->isValid()) {
      $this->logger->error('Image background failed using the %toolkit toolkit on %path', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $this->configuration['background_image'],
      ]);
      return FALSE;
    }
    [$x, $y] = explode('-', $this->configuration['placement']);
    $x_pos = ImageUtility::getKeywordOffset($x, $background_image->getWidth(), $image->getWidth());
    $y_pos = ImageUtility::getKeywordOffset($y, $background_image->getHeight(), $image->getHeight());
    return $image->apply('background', [
      'x_offset' => $x_pos + $this->configuration['x_offset'],
      'y_offset' => $y_pos + $this->configuration['y_offset'],
      'opacity' => $this->configuration['opacity'],
      'background_image' => $background_image,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    $dimensions['width'] = (int) $this->configuration['background_image_width'];
    $dimensions['height'] = (int) $this->configuration['background_image_height'];
  }

}
