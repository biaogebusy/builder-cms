<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Language fallback processor.
 *
 * @ImportProcessor(
 *   id = "language_fallback",
 *   label = @Translation("Language fallback"),
 *   description = @Translation("Allow to set the language of the imported entity."),
 *   stages = {
 *     "prepare_entity_data" = 0,
 *   },
 *   locked = false,
 * )
 */
class LanguageFallback extends ImportProcessorPluginBase implements PluginFormInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The langcode to insert as replacement.
   *
   * @var string
   */
  protected $langcodeToInsert;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'fallback_language' => LanguageInterface::LANGCODE_SITE_DEFAULT,
      'force_language' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $language_options = [
      LanguageInterface::LANGCODE_SITE_DEFAULT => $this->t("Site's default language (@language)", [
        '@language' => $this->languageManager->getDefaultLanguage()->getName(),
      ]),
    ];

    $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
    foreach ($languages as $langcode => $language) {
      $language_options[$langcode] = $language->isLocked() ? $this->t('- @name -', ['@name' => $language->getName()]) : $language->getName();
    }

    $form['fallback_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Fallback language'),
      '#description' => $this->t('The language that will be applied to all entities in a language not present on the website.'),
      '#options' => $language_options,
      '#default_value' => $this->configuration['fallback_language'],
      '#required' => TRUE,
    ];

    $form['force_language'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force the language'),
      '#description' => $this->t('If checked, the selected language will be applied to all imported data. <strong>Warning! This can make unforeseen consequences on content translations!</strong>'),
      '#default_value' => $this->configuration['force_language'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
    $field_mappings = $runtime_import_context->getFieldMappings();
    $parsed_type = explode('--', $entity_json_data['type']);
    $entity_type_id = $parsed_type[0];
    $entity_bundle = $parsed_type[1];
    // @todo Refactor in attributes to avoid getting entity keys each time.
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_keys = $entity_storage->getEntityType()->getKeys();

    $langcode_public_name = FALSE;
    if (!empty($entity_keys['langcode']) && isset($field_mappings[$entity_type_id][$entity_bundle][$entity_keys['langcode']])) {
      $langcode_public_name = $field_mappings[$entity_type_id][$entity_bundle][$entity_keys['langcode']];
    }

    // The entity does not have a langcode.
    if (!$langcode_public_name) {
      return;
    }
    // We force the langcode.
    elseif ($this->configuration['force_language']) {
      $entity_json_data['attributes'][$langcode_public_name] = $this->getLangcodeToInsert();
      return;
    }

    $data_langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    if ($langcode_public_name && !empty($entity_json_data['attributes'][$langcode_public_name])) {
      $data_langcode = $entity_json_data['attributes'][$langcode_public_name];
    }

    // Check if we try to import an entity with langcode in a disabled language.
    if (is_null($this->languageManager->getLanguage($data_langcode))) {
      $entity_json_data['attributes'][$langcode_public_name] = $this->getLangcodeToInsert();
    }
  }

  /**
   * Handle the case of default site language selected.
   *
   * @return string
   *   The langcode to insert.
   */
  protected function getLangcodeToInsert() : string {
    if (!$this->langcodeToInsert) {
      $langcode_to_insert = $this->configuration['fallback_language'];
      if ($langcode_to_insert == LanguageInterface::LANGCODE_SITE_DEFAULT) {
        $langcode_to_insert = $this->languageManager->getDefaultLanguage()->getId();
      }

      $this->langcodeToInsert = $langcode_to_insert;
    }
    return $this->langcodeToInsert;
  }

}
