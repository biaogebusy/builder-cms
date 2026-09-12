<?php

namespace Drupal\chosen_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'chosen_select' widget.
 */
#[FieldWidget(
  id: 'chosen_select',
  label: new TranslatableMarkup('Chosen'),
  field_types: [
    'list_integer',
    'list_float',
    'list_string',
    'entity_reference',
  ],
  multiple_values: TRUE
)]
class ChosenFieldWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'chosen_placeholder' => '',
      'no_results_text' => '',
      'search_contains' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element += [
      '#chosen' => 1,
      '#chosen_placeholder' => $this->getSetting('chosen_placeholder'),
      '#no_results_text' => $this->getSetting('no_results_text'),
      '#search_contains' => (int) $this->getSetting('search_contains'),
    ];

    if ($this->supportsTaxonomyTermAutoCreate()) {
      $element['#attributes']['data-create_option'] = 'true';
      $element['#after_build'][] = [static::class, 'afterBuildTaxonomyTermAutoCreate'];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if ($this->supportsTaxonomyTermAutoCreate()) {
      $values = $this->convertNewTaxonomyTermLabelsToIds($values);
    }

    return parent::massageFormValues($values, $form, $form_state);
  }

  /**
   * Adds submitted Chosen-created values to #options before select validation.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form element.
   */
  public static function afterBuildTaxonomyTermAutoCreate(array $element, FormStateInterface $form_state) {
    $submitted_value = $element['#value'] ?? NULL;

    if ($submitted_value === NULL && !empty($element['#parents'])) {
      $submitted_value = $form_state->getValue($element['#parents']);
    }

    if ($submitted_value === NULL || $submitted_value === '' || $submitted_value === '_none') {
      return $element;
    }

    $submitted_values = is_array($submitted_value) ? $submitted_value : [$submitted_value];

    foreach ($submitted_values as $submitted_item) {
      if (is_array($submitted_item)) {
        continue;
      }

      if ($submitted_item === '' || $submitted_item === NULL || $submitted_item === '_none') {
        continue;
      }

      if (!isset($element['#options'][$submitted_item])) {
        $element['#options'][$submitted_item] = $submitted_item;
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['chosen_placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('chosen_placeholder'),
      '#description' => $this->t('Overrides the default placeholder. Leave blank to use the global option.'),
    ];

    $element['no_results_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No results text'),
      '#default_value' => $this->getSetting('no_results_text'),
      '#description' => $this->t('Overrides the default no results text. Leave blank to use the global option.'),
    ];

    $element['search_contains'] = [
      '#type' => 'select',
      '#title' => $this->t('Search also in the middle of words'),
      '#options' => $this->getSearchContainsOptions(),
      '#default_value' => $this->getSetting('search_contains'),
      '#description' => $this->t('Overrides the global search setting for this widget.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $search_contains_options = $this->getSearchContainsOptions();

    $summary[] = $this->t('Placeholder: @value', [
      '@value' => $this->getSetting('chosen_placeholder') ?: $this->t('Default'),
    ]);
    $summary[] = $this->t('No results text: @value', [
      '@value' => $this->getSetting('no_results_text') ?: $this->t('Default'),
    ]);
    $summary[] = $this->t('Search contains: @value', [
      '@value' => $search_contains_options[(int) $this->getSetting('search_contains')],
    ]);

    return $summary;
  }

  /**
   * Returns allowed values for the search_contains setting.
   *
   * @return array
   *   The allowed values.
   */
  protected function getSearchContainsOptions() {
    return [
      0 => $this->t('Default'),
      1 => $this->t('Enabled'),
      2 => $this->t('Disabled'),
    ];
  }

  /**
   * Checks whether this field can safely use Chosen option creation.
   *
   * This intentionally requires Drupal's own entity-reference auto_create
   * setting. That prevents side effects for normal list fields, ordinary entity
   * reference selects, and existing Chosen-enabled selects.
   *
   * @return bool
   *   TRUE if this is an auto-create taxonomy term entity-reference field.
   */
  protected function supportsTaxonomyTermAutoCreate() {
    if ($this->fieldDefinition->getType() !== 'entity_reference') {
      return FALSE;
    }

    if ($this->fieldDefinition->getSetting('target_type') !== 'taxonomy_term') {
      return FALSE;
    }

    $handler_settings = $this->fieldDefinition->getSetting('handler_settings') ?: [];

    if (empty($handler_settings['auto_create'])) {
      return FALSE;
    }

    return (bool) $this->getAutoCreateBundle();
  }

  /**
   * Gets the vocabulary that should receive auto-created terms.
   *
   * @return string|null
   *   The vocabulary machine name, or NULL when it cannot be determined safely.
   */
  protected function getAutoCreateBundle() {
    $handler_settings = $this->fieldDefinition->getSetting('handler_settings') ?: [];

    if (!empty($handler_settings['auto_create_bundle'])) {
      return $handler_settings['auto_create_bundle'];
    }

    $target_bundles = array_filter($handler_settings['target_bundles'] ?? []);

    if (count($target_bundles) === 1) {
      return reset($target_bundles);
    }

    return NULL;
  }

  /**
   * Converts newly submitted taxonomy term labels to term IDs.
   *
   * @param array $values
   *   Submitted widget values before the parent widget massages them.
   *
   * @return array
   *   Submitted values with new labels replaced by taxonomy term IDs.
   */
  protected function convertNewTaxonomyTermLabelsToIds(array $values) {
    foreach ($values as $key => $value) {
      if (is_array($value)) {
        $values[$key] = $this->convertNewTaxonomyTermLabelsToIds($value);
      }
      else {
        $values[$key] = $this->convertNewTaxonomyTermLabelToId($value);
      }
    }

    return $values;
  }

  /**
   * Converts a single submitted taxonomy term label to a term ID.
   *
   * Existing numeric values are preserved when they identify an existing term in
   * the auto-create vocabulary. Non-existing numeric values are treated as term
   * labels, so a user can still create a tag such as "2026".
   *
   * @param mixed $value
   *   A submitted select value.
   *
   * @return mixed
   *   A taxonomy term ID, or the original empty/_none value.
   */
  protected function convertNewTaxonomyTermLabelToId($value) {
    if ($value === '' || $value === NULL || $value === '_none') {
      return $value;
    }

    $vid = $this->getAutoCreateBundle();

    if (!$vid) {
      return $value;
    }

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    if (is_numeric($value)) {
      $term = $term_storage->load($value);

      if ($term && $term->bundle() === $vid) {
        return $value;
      }
    }

    $label = trim((string) $value);

    if ($label === '') {
      return $value;
    }

    $existing_terms = $term_storage->loadByProperties([
      'vid' => $vid,
      'name' => $label,
    ]);

    if ($existing_term = reset($existing_terms)) {
      return $existing_term->id();
    }

    $access_control = \Drupal::entityTypeManager()->getAccessControlHandler('taxonomy_term');

    if (!$access_control->createAccess($vid)) {
      return $value;
    }

    $term = $term_storage->create([
      'vid' => $vid,
      'name' => $label,
    ]);
    $term->save();

    return $term->id();
  }

}
