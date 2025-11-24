<?php

namespace Drupal\ckeditor_templates\Plugin\CkeditorTemplate;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ckeditor_templates\CkeditorTemplatePluginBase;
use Drupal\ckeditor_templates\CKEditorTemplatesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ckeditor_template.
 *
 * @CkeditorTemplate(
 *   id = "config_template",
 *   deriver = "\Drupal\ckeditor_templates\Plugin\Derivative\ConfigTemplateDeriver",
 * )
 */
class ConfigTemplate extends CkeditorTemplatePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path for the current module folder.
   *
   * @var string
   */
  protected string $moduleFolder;

  /**
   * The ckeditor_templates config entity.
   *
   * @var CKEditorTemplatesInterface
   */
  protected CKEditorTemplatesInterface $ckeditorTemplateConfig;

  /**
   * The file url generator instance.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a CkeditorTemplatePluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The provider for a list of available modules.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file url generator instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleExtensionList $extension_list_module, EntityTypeManagerInterface $entity_type_manager, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $extension_list_module);
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->ckeditorTemplateConfig = $entity_type_manager
      ->getStorage('ckeditor_templates')
      ->load($plugin_definition['ckeditor_template_id']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getThumb(): string {
    $image = '';
    $thumb = $this->ckeditorTemplateConfig->get('thumb')[0] ?? '';
    $thumb_alternative = $this->ckeditorTemplateConfig->get('thumb_alternative');

    if (!empty($thumb)) {
      $file = $this->entityTypeManager
        ->getStorage('file')
        ->load($thumb);

      if (isset($file)) {
        $fileUri = $file->getFileUri();

        $style = $this->entityTypeManager
          ->getStorage('image_style')
          ->load('thumbnail');
        if (isset($style)) {
          $image = $style->buildUrl($fileUri) ?? '';
        }
        else {
          $image = $this->fileUrlGenerator->generateAbsoluteString($fileUri) ?? '';
        }
      }
    }

    if (empty($image)) {
      $image = empty($thumb_alternative)
        ? parent::getThumb()
        : $thumb_alternative;
    }

    return $image;
  }

  /**
   * {@inheritdoc}
   */
  public function allowedFormats(): array {
    return $this->ckeditorTemplateConfig->get('formats');
  }

  /**
   * {@inheritdoc}
   */
  public function getHtml(): string {
    return $this->ckeditorTemplateConfig->get('code')['value'];
  }
}
