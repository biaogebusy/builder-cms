<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Ai content suggestions settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager $pluginManager
   *   The AI Content Suggestions Plugin Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle info service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected AiContentSuggestionsPluginManager $pluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.ai_content_suggestions'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_content_suggestions_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ai_content_suggestions.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $content_suggestions_config = $this->config('ai_content_suggestions.settings');
    $form['plugins'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings for plugins'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    // Collect available plugins.
    $available_plugins = [];
    foreach ($this->pluginManager->getDefinitions() as $id => $config) {
      /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsInterface $plugin */
      if ($plugin = $this->pluginManager->createInstance($id, $config)) {
        if ($plugin->isAvailable()) {
          $available_plugins[$id] = $plugin;
        }
      }
    }

    // Show warning if no plugins are available.
    if (empty($available_plugins)) {
      $form['plugins']['no_providers'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('No AI content suggestion plugins are currently available. Please <a href="@url">configure and enable an AI provider</a> first to see the list of available plugins.', [
          '@url' => Url::fromRoute('ai.admin_providers')->toString(),
        ]),
        '#attributes' => [
          'class' => ['messages', 'messages--warning'],
        ],
      ];
    }
    else {
      $form['plugins']['introduction'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Below is a list of the available plugins you can use to analyze your content.'),
      ];

      foreach ($available_plugins as $plugin) {
        $plugin->buildSettingsForm($form['plugins']);
      }
    }

    $form['field_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings for per field suggestions'),
      '#tree' => TRUE,
    ];
    $form['field_settings']['field_widget_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System Prompt For Content Suggestions from Field Widget'),
      '#default_value' => $content_suggestions_config->get('field_widget_prompt') ?? '',
      '#description' => $this->t('This prompt will be used for all string/text field types if the AI Content Suggestions are enabled for the field in the widget settings of form display. Make sure that the parts with ```html  ``` are always in the prompt as some functionality depends on the response structure.'),
    ];

    $form['entity_type_settings'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Configure AI content suggestion settings for different entity types'),
    ];

    $form['entity_type_settings']['entity_types'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Entity types'),
    ];
    $form['#attached']['library'][] = 'ai_content_suggestions/settings.admin';
    $entity_types = $this->entityTypeManager->getDefinitions();
    $labels = [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }
      $labels[$entity_type_id] = $entity_type->getLabel() ?: $entity_type_id;
    }
    asort($labels);
    foreach ($labels as $entity_type_id => $label) {
      $form['entity_type_settings']['entity_types'][$entity_type_id] = [
        '#type' => 'details',
        '#title' => $label,
        '#group' => 'entity_type_settings][entity_types',
        '#attributes' => [
          'class' => ['entity-type-tab'],
        ],
      ];
      $form['entity_type_settings']['entity_types'][$entity_type_id]['mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Which bundles should have AI suggestions?'),
        '#options' => [
          'enable' => $this->t('Only those selected'),
          'disable' => $this->t('All except those selected'),
        ],
        '#default_value' => $content_suggestions_config->get('entity_types.' . $entity_type_id . '.mode') ?? 'enable',
      ];
      $bundle_info = $this->bundleInfo->getBundleInfo($entity_type_id);
      $options = [];
      foreach ($bundle_info as $bundle_name => $bundle) {
        $options[$bundle_name] = $bundle['label'];
      }
      $form['entity_type_settings']['entity_types'][$entity_type_id]['bundles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Bundles'),
        '#options' => $options,
        '#attributes' => [
          'class' => ['entity-type-bundles'],
        ],
        '#default_value' => $content_suggestions_config->get('entity_types.' . $entity_type_id . '.bundles') ?? [],
      ];
    }

    // If new suggestion plugins are added, or new providers make existing
    // plugins available, we want to rebuild the form.
    $form['#cache']['contexts'][] = 'ai_content_suggestions_plugins';
    $form['#cache']['contexts'][] = 'ai_providers';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = [];
    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsInterface $plugin */
      if ($plugin = $this->pluginManager->createInstance($id, $definition)) {
        if ($plugin->isAvailable()) {
          $value = $form_state->getValue($id);
          // Ensure $value is an array before accessing keys.
          if (is_array($value) && !empty($value[$id . '_enabled'])) {
            $values[$id] = $value[$id . '_model'];
          }
          if (method_exists($plugin, 'saveSettingsForm')) {
            $plugin->saveSettingsForm($form, $form_state);
          }
        }
      }
    }
    $entity_types = $form_state->getValue(['entity_type_settings', 'entity_types']);
    if (!empty($entity_types['entity_type_settings__entity_types__active_tab'])) {
      unset($entity_types['entity_type_settings__entity_types__active_tab']);
    }
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!is_array($entity_type)) {
        continue;
      }
      $entity_types[$entity_type_id]['bundles'] = array_filter($entity_type['bundles']);
      if (empty($entity_types[$entity_type_id]['bundles']) && $entity_types[$entity_type_id]['mode'] === 'enable') {
        unset($entity_types[$entity_type_id]);
      }
    }
    $this->config('ai_content_suggestions.settings')
      ->set('field_widget_prompt', $form_state->getValue(['field_settings', 'field_widget_prompt']))
      ->set('plugins', $values)
      ->set('entity_types', $entity_types)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
