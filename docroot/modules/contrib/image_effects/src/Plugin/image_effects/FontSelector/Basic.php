<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\image_effects\FontSelector;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file_mdm\FileMetadataManagerInterface;
use Drupal\image_effects\Plugin\Attribute\FontSelector;
use Drupal\image_effects\Plugin\ImageEffectsFontSelectorPluginInterface;
use Drupal\image_effects\Plugin\ImageEffectsPluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Basic font selector plugin.
 *
 * Allows typing in the font file URI/path.
 */
#[FontSelector(
  id: "basic",
  title: new TranslatableMarkup("Basic font selector"),
  shortTitle: new TranslatableMarkup("Basic"),
  help: new TranslatableMarkup("Allows typing in the font file URI/path."),
)]
class Basic extends ImageEffectsPluginBase implements ImageEffectsFontSelectorPluginInterface {

  /**
   * The file metadata manager service.
   *
   * @var \Drupal\file_mdm\FileMetadataManagerInterface
   */
  protected $fileMetadataManager;

  /**
   * Basic constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The image_effects logger.
   * @param \Drupal\file_mdm\FileMetadataManagerInterface $file_metadata_manager
   *   The file metadata manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, LoggerInterface $logger, FileMetadataManagerInterface $file_metadata_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $logger);
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
      $container->get('config.factory'),
      $container->get('logger.channel.image_effects'),
      $container->get(FileMetadataManagerInterface::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []): array {
    // Element.
    return array_merge([
      '#type' => 'textfield',
      '#title' => $this->t('Font URI/path'),
      '#description' => $this->t('An URI, an absolute path, or a relative path. Relative paths will be resolved relative to the Drupal installation directory.'),
      '#element_validate' => [[$this, 'validateSelectorUri']],
    ], $options);
  }

  /**
   * Validation handler for the selection element.
   */
  public function validateSelectorUri(array $element, FormStateInterface $form_state, array $form): void {
    if (!empty($element['#value'])) {
      if (!file_exists($element['#value'])) {
        $form_state->setErrorByName(implode('][', $element['#parents']), $this->t('The file does not exist.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(string $uri): ?string {
    return $this->fileMetadataManager->uri($uri)->getMetadata('font', 'FullName');
  }

}
