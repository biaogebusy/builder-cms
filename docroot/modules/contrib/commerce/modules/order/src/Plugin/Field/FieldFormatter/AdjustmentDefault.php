<?php

namespace Drupal\commerce_order\Plugin\Field\FieldFormatter;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_order\AdjustmentTypeManager;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_adjustment_default' formatter.
 */
#[FieldFormatter(
  id: "commerce_adjustment_default",
  label: new TranslatableMarkup("Adjustment Default"),
  field_types: ["commerce_adjustment"],
)]
class AdjustmentDefault extends FormatterBase {

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected CurrencyFormatterInterface $currencyFormatter;

  /**
   * The adjustment type manager.
   *
   * @var \Drupal\commerce_order\AdjustmentTypeManager
   */
  protected AdjustmentTypeManager $adjustmentTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currencyFormatter = $container->get('commerce_price.currency_formatter');
    $instance->adjustmentTypeManager = $container->get('plugin.manager.commerce_adjustment_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = [];

    // Fall back to field settings by default.
    $settings['adjustment_types'] = [];
    $settings['currency_display'] = 'symbol';
    $settings['strip_trailing_zeroes'] = FALSE;
    $settings['adjustment_label'] = TRUE;
    $settings['separator'] = ', ';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['adjustment_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Adjustment types'),
      '#options' => $this->getAdjustmentTypes(),
      '#default_value' => $this->getSetting('adjustment_types'),
    ];
    $form['adjustment_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the adjustment label.'),
      '#default_value' => $this->getSetting('adjustment_label'),
    ];
    $form['strip_trailing_zeroes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip trailing zeroes after the decimal point.'),
      '#default_value' => $this->getSetting('strip_trailing_zeroes'),
    ];
    $form['currency_display'] = [
      '#type' => 'radios',
      '#title' => $this->t('Currency display'),
      '#options' => [
        'symbol' => $this->t('Symbol (e.g. "$")'),
        'code' => $this->t('Currency code (e.g. "USD")'),
        'none' => $this->t('None'),
      ],
      '#default_value' => $this->getSetting('currency_display'),
    ];
    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator'),
      '#default_value' => $this->getSetting('separator'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }
    $adjustments = $this->prepareAdjustments($items);
    foreach ($adjustments as $key => $adjustment) {
      $adjustments[$key] = implode(': ', $adjustment);
    }
    $elements[] = [
      '#type' => 'inline_template',
      '#template' => '{{ items | safe_join(separator) }}',
      '#context' => ['separator' => $this->getSetting('separator'), 'items' => $adjustments],
    ];
    return $elements;
  }

  /**
   * Prepare the adjustments.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The items.
   *
   * @return array
   *   The array of adjustments.
   */
  protected function prepareAdjustments(FieldItemListInterface $items): array {
    $adjustments = [];
    $adjustment_types = array_filter($this->getSetting('adjustment_types')) ?: array_keys($this->getAdjustmentTypes());
    /** @var \Drupal\commerce_order\Plugin\Field\FieldType\AdjustmentItem[] $items */
    foreach ($items as $delta => $item) {
      /** @var \Drupal\commerce_order\Adjustment $adjustment */
      $adjustment = $item->getValue()['value'];
      if (!in_array($adjustment->getType(), $adjustment_types, TRUE)) {
        continue;
      }
      if ($this->getSetting('adjustment_label')) {
        $adjustments[$delta] = [
          $adjustment->getLabel(),
          $this->currencyFormatter->format($adjustment->getAmount()
            ->getNumber(), $adjustment->getAmount()
            ->getCurrencyCode(), $this->getFormattingOptions()),
        ];
      }
      else {
        $adjustments[$delta] = [
          $this->currencyFormatter->format($adjustment->getAmount()
            ->getNumber(), $adjustment->getAmount()
            ->getCurrencyCode(), $this->getFormattingOptions()),
        ];
      }
    }
    return $adjustments;
  }

  /**
   * Gets the formatting options for the currency formatter.
   *
   * @return array
   *   The formatting options.
   */
  protected function getFormattingOptions(): array {
    $options = [
      'currency_display' => $this->getSetting('currency_display'),
    ];
    if ($this->getSetting('strip_trailing_zeroes')) {
      $options['minimum_fraction_digits'] = 0;
    }
    return $options;
  }

  /**
   * Get the configured adjustment types.
   *
   * @return array
   *   The adjustment types.
   */
  protected function getAdjustmentTypes(): array {
    $options = array_map(static function ($definition) {
      return $definition['label'];
    }, $this->adjustmentTypeManager->getDefinitions());
    return $options;
  }

}
