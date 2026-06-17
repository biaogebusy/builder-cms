<?php

namespace Drupal\layout_builder_st\Form;

use Drupal\Component\Utility\Html;
use Drupal\config_translation\Form\ConfigTranslationFormBase;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder_st\TranslatableSectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to translate a block plugin in the Layout Builder.
 *
 * @internal
 *   Form classes are internal.
 */
class TranslateBlockForm extends FormBase {

  use AjaxFormHelperTrait;
  use LayoutRebuildTrait;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder_st\TranslatableSectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * The UUID of the component.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * Constructs a new TranslateBlockForm.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ModuleHandlerInterface $module_handler, TypedConfigManagerInterface $typed_config_manager) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->moduleHandler = $module_handler;
    $this->typedConfigManager = $typed_config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('module_handler'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_block_translation';
  }

  /**
   * Builds the block translation form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage being configured.
   * @param int $delta
   *   The delta of the section.
   * @param string $region
   *   The region of the block.
   * @param string $uuid
   *   The UUID of the block being updated.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?TranslatableSectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $component = $section_storage->getSection($delta)->getComponent($uuid);

    $this->sectionStorage = $section_storage;
    $this->uuid = $component->getUuid();

    $configuration = $component->getPlugin()->getConfiguration();
    $type_definition = $this->typedConfigManager->getDefinition('block.settings.' . $component->getPlugin()->getPluginId());
    /** @var \Drupal\Core\TypedData\DataDefinitionInterface $definition */
    $definition = new $type_definition['definition_class']($type_definition);
    $definition->setClass($type_definition['class']);
    $definition->setTypedDataManager($this->typedConfigManager);

    /** @var \Drupal\Core\Config\Schema\Mapping $typed_data */
    $typed_data = $type_definition['class']::createInstance($definition);
    $typed_data->setValue($configuration);
    $translated_config = $this->sectionStorage->getTranslatedComponentConfiguration($this->uuid);
    foreach (array_keys($configuration) as $key) {
      if (!isset($translated_config[$key])) {
        $translated_config[$key] = NULL;
      }
    }

    $form['translation'] = $this->createTranslationElement($section_storage->getSourceLanguage(), $section_storage->getTranslationLanguage(), $typed_data, $translated_config);
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Translate'),
    ];

    if ($this->isAjax()) {
      $form['submit']['#ajax']['callback'] = '::ajaxSubmit';
      // @todo static::ajaxSubmit() requires data-drupal-selector to be the same
      //   between the various Ajax requests. A bug in
      //   \Drupal\Core\Form\FormBuilder prevents that from happening unless
      //   $form['#id'] is also the same. Normally, #id is set to a unique HTML
      //   ID via Html::getUniqueId(), but here we bypass that in order to work
      //   around the data-drupal-selector bug. This is okay so long as we
      //   assume that this form only ever occurs once on a page. Remove this
      //   workaround in https://www.drupal.org/node/2897377.
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    }
    return $form;
  }

  /**
   * Creates translation element.
   *
   * @param \Drupal\Core\Language\LanguageInterface $source_language
   *   The source language.
   * @param \Drupal\Core\Language\LanguageInterface $translation_language
   *   The translation language.
   * @param \Drupal\Core\TypedData\TraversableTypedDataInterface $typed_data
   *   The typed data of the configuration settings.
   * @param array $translated_configuration
   *   The translated configuration.
   *
   * @return array
   *   The translation element render array.
   */
  protected function createTranslationElement(LanguageInterface $source_language, LanguageInterface $translation_language, TraversableTypedDataInterface $typed_data, array $translated_configuration) {
    if ($this->moduleHandler->moduleExists('config_translation')) {
      // If config_translation is installed let it handle creating complex
      // schema.
      $form_element = ConfigTranslationFormBase::createFormElement($typed_data);
      $element_build = $form_element->getTranslationBuild($source_language, $translation_language, $typed_data->getValue(), $translated_configuration, []);
    }
    else {
      // If config_translation is not enabled only provide the 'label'
      // translation.
      if (($label_data = $typed_data->get('label')) && $label_data instanceof StringData) {
        $element_build['label']['source'] = [
          '#type' => 'item',
          '#title' => $this->t('Label'),
          '#markup' => $label_data->getValue() ?: '(' . $this->t('Empty') . ')',
          '#parents' => ['source', 'label'],
        ];
        $element_build['label']['translation'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#default_value' => $translated_configuration['label'] ?? '',
          '#parents' => ['translation', 'label'],
        ];
      }
    }
    $element_build['#tree'] = TRUE;
    return $element_build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $section_storage = $this->sectionStorage;
    $section_storage->setTranslatedComponentConfiguration($this->uuid, $form_state->getValue('translation'));
    $this->layoutTempstoreRepository->set($this->sectionStorage);
    $form_state->setRedirectUrl($this->sectionStorage->getLayoutBuilderUrl());
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->rebuildAndClose($this->sectionStorage);
  }

}
