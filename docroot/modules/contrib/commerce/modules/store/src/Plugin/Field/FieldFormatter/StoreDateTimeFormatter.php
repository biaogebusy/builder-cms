<?php

namespace Drupal\commerce_store\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\commerce_store\Entity\Store;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_store_datetime' formatter.
 *
 * Used for displaying date/time values in the store timezone,
 * as opposed to the user's timezone.
 *
 * @see \Drupal\commerce_store\Plugin\Field\FieldWidget\StoreDateTimeWidget
 */
#[FieldFormatter(
  id: "commerce_store_datetime",
  label: new TranslatableMarkup("Default (Store timezone)"),
  field_types: ["datetime"],
)]
class StoreDateTimeFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected CurrentStoreInterface $currentStore;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentStore = $container->get('commerce_store.current_store');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'date_format' => 'medium',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $date = new DrupalDateTime('now', $this->getTimezone());
    $date_format_storage = $this->entityTypeManager->getStorage('date_format');
    /** @var \Drupal\Core\Datetime\DateFormatInterface[] $date_formats */
    $date_formats = $date_format_storage->loadMultiple();
    $options = [];
    foreach ($date_formats as $type => $date_format) {
      $example = $date->format($date_format->getPattern());
      $options[$type] = $date_format->label() . ' (' . $example . ')';
    }

    $form['date_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Date format'),
      '#description' => $this->t('Choose a format for displaying the date. Be sure to set a format appropriate for the field, i.e. omitting time for a field that only has a date.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('date_format'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $date_format = $this->getDateFormat();
    $date = new DrupalDateTime('now', $this->getTimezone());
    // Uses the same summary format as DateTimeDefaultFormatter.
    $summary[] = $this->t('Format: @date_format', [
      '@date_format' => $date->format($date_format->getPattern()),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $date_pattern = $this->getDateFormat()->getPattern();
    $timezone = $this->getTimezone();
    $store = $this->currentStore->getStore();

    $elements = [];
    foreach ($items as $delta => $item) {
      if ($item->value) {
        $date = new DrupalDateTime($item->value, $timezone);

        $elements[$delta] = [
          '#theme' => 'time',
          '#text' => $date->format($date_pattern),
          '#html' => FALSE,
          '#attributes' => [
            'datetime' => $date->format('Y-m-d\TH:i:sP'),
          ],
          '#cache' => [
            'contexts' => ['store'],
          ],
        ];
        if ($store) {
          // Make sure the render cache is cleared when the store is updated.
          $cacheability = new CacheableMetadata();
          $cacheability->addCacheableDependency($store);
          $cacheability->applyTo($elements[$delta]);
        }

        if (!empty($item->_attributes)) {
          $elements[$delta]['#attributes'] += $item->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and should not be rendered in the field template.
          unset($item->_attributes);
        }
      }
    }

    return $elements;
  }

  /**
   * Gets the configured date format.
   *
   * @return \Drupal\Core\Datetime\DateFormatInterface
   *   The date format.
   */
  protected function getDateFormat() {
    $date_format_storage = $this->entityTypeManager->getStorage('date_format');
    /** @var \Drupal\Core\Datetime\DateFormatInterface $date_format */
    $date_format = $date_format_storage->load($this->getSetting('date_format'));
    if (!$date_format) {
      // Guard against missing/deleted date formats.
      $date_format = $date_format_storage->load('fallback');
    }

    return $date_format;
  }

  /**
   * Gets the timezone used for date formatting.
   *
   * This is the timezone of the current store, with a fallback to the
   * site timezone, in case the site doesn't have any stores yet.
   *
   * @return string
   *   The timezone.
   */
  protected function getTimezone() {
    $store = $this->currentStore->getStore();
    if ($store) {
      $timezone = $store->getTimezone();
    }
    else {
      $site_timezone = Store::getSiteTimezone();
      $timezone = reset($site_timezone);
    }

    return $timezone;
  }

}
