<?php

namespace Drupal\shs\Plugin\views\filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\shs\StringTranslationTrait;
use Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid;
use Drupal\taxonomy\VocabularyStorageInterface;
use Drupal\taxonomy\TermStorageInterface;

/**
 * Filter by term id using Simple hierarchical select widgets.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("shs_taxonomy_index_tid")
 */
class ShsTaxonomyIndexTid extends TaxonomyIndexTid {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, VocabularyStorageInterface $vocabulary_storage, TermStorageInterface $term_storage, AccountInterface $current_user = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $vocabulary_storage, $term_storage, $current_user);

    // Set translation context.
    $this->translationContext = 'shs:taxonomy_index_tid';
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildExtraOptionsForm($form, $form_state);

    $form['type']['#options']['shs'] = $this->t('Simple hierarchical select');
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $vocabulary = $this->vocabularyStorage->load($this->options['vid']);
    if (empty($vocabulary) && $this->options['limit']) {
      $form['markup'] = [
        '#markup' => '<div class="js-form-item form-item">' . $this->t('An invalid vocabulary is selected. Please change it in the options.') . '</div>',
      ];
      return;
    }

    // Let the parent class generate the base form.
    parent::valueForm($form, $form_state);

    if (($this->options['type'] !== 'shs') || !$form_state->get('exposed')) {
      // Stop further processing if the filter should not be rendered as exposed
      // filter or as Simple hierarchical select widget.
      return;
    }

    $settings_additional = [
      'required' => $this->options['expose']['required'],
      'multiple' => $this->options['expose']['multiple'],
      'anyLabel' => $this->t('- Any -'),
      'anyValue' => 'All',
      'addNewLabel' => $this->t('Add another item'),
    ];

    $bundle = $vocabulary->id();
    // Define default parents for the widget.
    $parents = [
      [
        [
          'parent' => 0,
          'defaultValue' => $settings_additional['anyValue'],
        ],
      ],
    ];
    $identifier = $this->options['expose']['identifier'];
    $default_value = (array) $this->value;
    if (empty($default_value)) {
      $exposed_input = isset($this->view->getExposedInput()[$identifier]) ? $this->view->getExposedInput()[$identifier] : [];
      if ($exposed_input) {
        $default_value = (array) $exposed_input;
      }
    }

    $options = empty($form['value']['#options']) ? [] : $form['value']['#options'];
    if (!empty($this->options['expose']['reduce'])) {
      $options = $this->reduceValueOptions($options);

      if (!empty($this->options['expose']['multiple']) && empty($this->options['expose']['required'])) {
        $default_value = [];
      }
    }
    if (empty($this->options['expose']['multiple'])) {
      if (empty($this->options['expose']['required']) && (empty($default_value) || !empty($this->options['expose']['reduce']))) {
        $default_value = 'All';
      }
      elseif (empty($default_value)) {
        $keys = array_keys($options);
        $default_value = array_shift($keys);
      }
      // Due to #1464174 there is a chance that array('') was saved in the admin
      // ui. Let's choose a safe default value.
      elseif ($default_value == ['']) {
        $default_value = 'All';
      }
      else {
        $copy = $default_value;
        $default_value = array_shift($copy);
      }
    }

    if (!empty($default_value)) {
      $widget_defaults = \Drupal::service('shs.widget_defaults');
      $parents = $widget_defaults->getParentDefaults($default_value, $settings_additional, 'taxonomy_term');
    }
    // @todo Allow individual settings per filter.
    $settings_shs = [
      'settings' => $settings_additional,
      'bundle' => $bundle,
      'baseUrl' => 'shs-term-data',
      'cardinality' => $this->options['expose']['multiple'] ? -1 : 1,
      'parents' => $parents,
      'defaultValue' => $default_value,
    ];
    $field_name = isset($this->definition['field_name']) ? $this->definition['field_name'] : $this->realField;
    $hooks = [
      'shs_js_settings',
      "shs_{$field_name}_js_settings",
      "shs_{$this->view->id()}__{$identifier}_js_settings",
      "shs_{$this->view->id()}__{$field_name}_js_settings",
      "shs_{$this->view->id()}__{$this->view->current_display}__{$identifier}_js_settings",
      "shs_{$this->view->id()}__{$this->view->current_display}__{$field_name}_js_settings",
    ];
    // Allow other modules to override the settings.
    \Drupal::moduleHandler()->alter($hooks, $settings_shs, $bundle, $field_name);

    $context = [
      'settings' => $settings_shs,
      'object' => $this,
    ];

    $form['value'] += [
      '#shs' => $settings_shs,
    ];
    if (empty($form['value']['#attributes'])) {
      $form['value']['#attributes'] = [];
    }
    $form['value']['#attributes'] = array_merge($form['value']['#attributes'], [
      'class' => ['shs-enabled'],
    ]);
    if (empty($form['value']['#attached'])) {
      $form['value']['#attached'] = [];
    }
    $form['value']['#attached'] = array_merge($form['value']['#attached'], [
      'library' => ['shs/shs.form'],
    ]);

    // Create unique key for field.
    $element_key = Html::getUniqueId(sprintf('shs-%s', $field_name));
    $form['value']['#attributes'] = array_merge($form['value']['#attributes'], [
      'data-shs-selector' => $element_key,
    ]);

    $form['value']['#shs'] += [
      'classes' => shs_get_class_definitions($field_name, $context),
    ];
    $form['value']['#attached'] = array_merge($form['value']['#attached'], [
      'drupalSettings' => [
        'shs' => [
          $element_key => $form['value']['#shs'],
        ],
      ],
    ]);
  }

}
