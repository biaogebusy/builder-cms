<?php

namespace Drupal\commerce_log\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LogSettingsForm extends ConfigFormBase {

  /**
   * The configuration name.
   */
  const CONFIG_NAME = 'commerce_log.settings';

  /**
   * The list of lot templates.
   */
  protected array $logTemplates;

  /**
   * The list of lot categories.
   */
  protected array $logCategories;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    // Filer out templates that can be manually created.
    $instance->logTemplates = array_filter(
      $container->get('plugin.manager.commerce_log_template')->getDefinitions(),
      function ($template_definition) {
        return $template_definition['can_disable'];
      }
    );
    $instance->logCategories = $container->get('plugin.manager.commerce_log_category')->getDefinitions();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_log_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(self::CONFIG_NAME);

    // The logic is reversed. We are going to store the "unchecked" checkboxes
    // while showing for the user the one that is not in the configuration.
    $form['enabled_templates'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled templates'),
      '#tree' => TRUE,
      '#description' => $this->t('Unselect the templates that should not be stored in the system.'),
    ];

    // Add section for every entity type used for the log system and use
    // category as a wrapper for the template checkboxes.
    $disabled_templates = $config->get('disabled_templates') ?? [];
    $log_templates = array_keys($this->logTemplates);
    foreach ($this->getGroupedTemplatesByCategory() as $category_id => $category_data) {
      $form['enabled_templates'][$category_id] = [
        '#type' => 'checkboxes',
        '#title' => $category_data['label'],
        '#options' => array_map(function ($template_label) {
          return $template_label;
        }, $category_data['templates']),
        '#default_value' => array_diff($log_templates, $disabled_templates),
      ];

      // Templates that have a not defined category shown at the bottom.
      if ($category_id == 'no_category') {
        $form['enabled_templates'][$category_id]['#weight'] = 20;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $config = $this->config(self::CONFIG_NAME);
    $config->set('disabled_templates', $this->getDisabledTemplates($form_state->getValue('enabled_templates')));
    $config->save();
  }

  /**
   * Returns the list of log templates grouped by entity type and category.
   */
  private function getGroupedTemplatesByCategory(): array {
    $grouped_templates = [];

    // Group all available log templates by entity type ID and category.
    // This list is used to generate checkboxes for the settings form.
    foreach ($this->logTemplates as $template_id => $template) {
      $category_id = $template['category'];
      $category = $this->logCategories[$category_id] ?? [];
      if (empty($category)) {
        $category_id = 'no_category';
        $category['label'] = $this->t('No category');
      }

      if (!isset($grouped_templates[$category_id])) {
        $grouped_templates[$category_id]['label'] = $category['label'];
      }
      $grouped_templates[$category_id]['templates'][$template_id] = $template['label'];
    }

    // Sort categories and templates within category in alphabetical order.
    uasort($grouped_templates, function ($a, $b) {
      return strcasecmp($a['label'], $b['label']);
    });
    foreach ($grouped_templates as &$category_data) {
      uasort($category_data['templates'], 'strcasecmp');
    }

    return $grouped_templates;
  }

  /**
   * Returns the list of disabled log templates.
   */
  private function getDisabledTemplates(array $values): array {
    // Get all submitted templates in every category.
    $enabled_templates = [];
    foreach ($this->logCategories as $category_id => $category) {
      $enabled_templates = array_merge($enabled_templates, array_filter($values[$category_id]));
    }
    $disabled_templates = array_diff(array_keys($this->logTemplates), $enabled_templates);

    // Reset array keys and return the value.
    return array_values($disabled_templates);
  }

}
