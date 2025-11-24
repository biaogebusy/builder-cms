<?php

declare(strict_types=1);

namespace Drupal\diff\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\diff\Attribute\FieldDiffBuilder;
use Drupal\diff\FieldDiffBuilderBase;

/**
 * Plugin to diff list fields.
 */
#[FieldDiffBuilder(
  id: 'list_field_diff_builder',
  label: new TranslatableMarkup('List Field Diff'),
  field_types: [
    'list_string',
    'list_integer',
    'list_float',
  ],
)]
class ListFieldBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items): mixed {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      // Build the array for comparison only if the field is not empty.
      if (!$field_item->isEmpty()) {
        $possible_options = $field_item->getPossibleOptions();
        $values = $field_item->getValue();
        if ($this->configuration['compare']) {
          $result[$field_key][] = match ($this->configuration['compare']) {
            'both' => $possible_options[$values['value']] . ' (' . $values['value'] . ')',
            'label' => $possible_options[$values['value']],
            default => $values['value'],
          };
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['compare'] = [
      '#type' => 'radios',
      '#title' => $this->t('Comparison method'),
      '#options' => [
        'label' => $this->t('Label'),
        'key' => $this->t('Key'),
        'both' => $this->t('Label (key)'),
      ],
      '#default_value' => $this->configuration['compare'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['compare'] = $form_state->getValue('compare');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $default_configuration = [
      'compare' => 'key',
    ];
    $default_configuration += parent::defaultConfiguration();

    return $default_configuration;
  }

}
