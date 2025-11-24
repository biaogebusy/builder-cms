<?php

namespace Drupal\commerce_order\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\commerce\AjaxFormTrait;
use Drupal\commerce\EntityHelper;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\OrderItemTypeInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_store\Entity\StoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the metrics for the Store dashboard.
 */
class DashboardMetricsForm extends FormBase {

  use AjaxFormTrait;

  // Determine for how long the data is cached.
  const CACHE_DURATION = 300;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->cache = $container->get('cache.default');
    $instance->connection = $container->get('database');
    $instance->currencyFormatter = $container->get('commerce_price.currency_formatter');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_order_dashboard_metrics_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Workaround for core bug #2897377.
    $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    $form['#theme'] = 'commerce_order_dashboard_metrics_form';
    $cid = $this->buildCacheId($form_state);
    $ajax_callback = [static::class, 'ajaxRefreshForm'];
    // Check if we have the data cached.
    if ($cache = $this->cache->get($cid)) {
      $form = $cache->data;
      $form['refresh']['refreshed'] = [
        '#markup' => $this->t('Refreshed @diff ago', [
          '@diff' => $this->dateFormatter->formatDiff((int) $cache->created, time()),
        ]),
      ];
      $form['refresh']['button'] = [
        '#type' => 'submit',
        '#submit' => [
          [static::class, 'refreshSubmit'],
        ],
        '#name' => 'refresh',
        '#value' => $this->t('Refresh'),
        '#ajax' => [
          'callback' => $ajax_callback,
        ],
        '#attributes' => [
          'class' => ['link'],
        ],
      ];

      return $form;
    }
    $values = $form_state->getValues();
    /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
    $store_storage = $this->entityTypeManager->getStorage('commerce_store');
    $active_store = NULL;
    if (!empty($values['store_id'])) {
      $active_store = $store_storage->load($values['store_id']);
    }

    $first_day_of_week = $this->getFirstDayOfWeek();
    $periods = [
      'day' => new DrupalDateTime('now'),
      'week' => new DrupalDateTime(sprintf('%s this week', $first_day_of_week)),
      'month' => new DrupalDateTime('first day of this month'),
      'year' => new DrupalDateTime('first day of january this year'),
    ];
    // In case the first day of the week is sunday, the "week" date time object
    // can be instantiated for the wrong week.
    if ($periods['week'] > $periods['day']) {
      $periods['week'] = new DrupalDateTime(sprintf('%s last week', $first_day_of_week));
    }
    $periods = array_map(function (DrupalDateTime $date) use ($active_store) {
      $date->setTime(0, 0, 0);
      if ($active_store instanceof StoreInterface) {
        $timezone = $active_store->getTimezone();
        if (!empty($timezone)) {
          $date->setTimezone(new \DateTimeZone($timezone));
        }
      }
      return $date;
    }, $periods);
    $now_formatted = $this->dateFormatter->format(time(), 'short');
    foreach ($periods as $key => $date) {
      // Skip disabled periods.
      if (!Settings::get('commerce_dashboard_show_sales_this_' . $key, TRUE)) {
        continue;
      }
      $form['periods'][$key] = [
        '#type' => 'submit',
        '#value' => match($key) {
          'day' => $this->t('Today'),
          'week' => $this->t('This week'),
          'month' => $this->t('This month'),
          'year' => $this->t('This year'),
        },
        '#ajax' => [
          'callback' => $ajax_callback,
        ],
        '#attributes' => [
          'class' => [
            'button--small',
            'button--primary',
          ],
          'title' => $this->t('From @from to @to (@timezone)', [
            '@from' => $this->dateFormatter->format($date->getTimestamp(), 'short'),
            '@to' => $now_formatted,
            '@timezone' => $date->getTimezone()->getName(),
          ]),
        ],
        '#submit' => [
          [static::class, 'switchPeriodSubmit'],
        ],
        '#period' => $key,
      ];
    }
    // All periods are disabled, stop here.
    if (!isset($form['periods'])) {
      return $form;
    }
    // Use the first period available by default, if no period is set.
    if (!$form_state->has('period')) {
      $form_state->set('period', key($form['periods']));
    }
    $active_period = $form_state->get('period');
    $form['periods'][$active_period]['#disabled'] = TRUE;
    $current_period_timestamp = $periods[$active_period]->getTimestamp();
    $form_state->set('current_period_timestamp', $current_period_timestamp);
    if (Settings::get('commerce_dashboard_show_store_selector', TRUE)) {
      $store_ids = $store_storage->getQuery()->accessCheck(TRUE)->execute();
      $stores_count = count($store_ids);
      // If there's more than one store, display a store selector if there are
      // less than 20 stores.
      if ($stores_count > 1) {
        if ($stores_count < 20) {
          $stores = $store_storage->loadMultiple($store_ids);
          $form['filters']['store_id'] = [
            '#type' => 'select',
            '#title' => $this->t('Store'),
            '#title_display' => 'invisible',
            '#options' => EntityHelper::extractLabels($stores),
            '#empty_option' => $this->t('All stores'),
            '#ajax' => [
              'callback' => $ajax_callback,
            ],
            '#attributes' => [
              'class' => ['form-element--extrasmall'],
            ],
          ];
        }
        else {
          $form['filters']['store_id'] = [
            '#markup' => $this->t('All stores'),
          ];
        }
      }
    }

    $form['filters']['compare'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Compare to prior period'),
      '#default_value' => $values['compare'] ?? FALSE,
      '#ajax' => [
        'callback' => $ajax_callback,
      ],
      '#access' => Settings::get('commerce_dashboard_show_prior_period_comparison', TRUE),
    ];
    $results = $this->getOrderMetricsForPeriod($current_period_timestamp, $active_store?->id());
    $count_placed_orders = 0;
    // Sum the number of orders placed for all currencies.
    foreach ($results as $result) {
      $count_placed_orders += $result['count_orders'];
    }
    $comparison_enabled = !empty($values['compare']);
    // If the prior period comparison is checked.
    if ($comparison_enabled) {
      $prior_period = match ($active_period) {
        'day' => 'yesterday',
        'week' => sprintf('%s last week', $first_day_of_week),
        'month' => 'first day of last month',
        'year' => 'first day of last year',
      };
      $prior_period = new DrupalDateTime($prior_period, $periods['day']->getTimezone());
      $prior_period->setTime(0, 0, 0);
      // If the period is 'week', we need to get the appropriate prior week.
      if ($active_period === 'week' && $prior_period >= $periods[$active_period]) {
        $prior_period->sub(new \DateInterval('P1W'));
      }
      $prior_period_timestamp = $prior_period->getTimestamp();
      $form_state->set('prior_period_timestamp', $prior_period_timestamp);
      // We use the "BETWEEN" operator which is inclusive, we deduct 1 second
      // from the current period timestamp, so we get orders placed until
      // 23:59:59 the previous day instead of midnight.
      $period_range = [$prior_period_timestamp, ($periods[$active_period]->getTimestamp() - 1)];
      $prior_period_metrics = $this->getOrderMetricsForPeriod($period_range, $active_store?->id());
      $prior_period_orders_count = 0;
      if ($prior_period_metrics) {
        foreach ($prior_period_metrics as $result) {
          $prior_period_orders_count += (int) $result['count_orders'];
        }
      }
      // Append the previous placed orders count diff to the placed orders
      // count.
      $placed_orders_diff = $count_placed_orders - $prior_period_orders_count;
      if ($placed_orders_diff !== 0 && $prior_period_orders_count !== 0) {
        $placed_orders_metric_classes[] = $placed_orders_diff > 0 ? 'metrics-item__value--up' : 'metrics-item__value--down';
        $count_placed_orders = sprintf('%d (%+d)', $count_placed_orders, $placed_orders_diff);
      }
      else {
        $count_placed_orders = sprintf('%d (-)', $count_placed_orders);
      }
    }
    if ($this->moduleHandler->moduleExists('commerce_cart')) {
      $current_period_carts_count = $this->getCartsCountForPeriod($current_period_timestamp, $active_store?->id());
      if ($comparison_enabled) {
        $prior_period_carts_count = $this->getCartsCountForPeriod($period_range, $active_store?->id());
        $carts_diff = $current_period_carts_count - $prior_period_carts_count;
        if ($carts_diff !== 0 && $prior_period_carts_count !== 0) {
          $carts_metric_classes[] = $carts_diff > 0 ? 'metrics-item__value--up' : 'metrics-item__value--down';
          $current_period_carts_count = sprintf('%d (%+d)', $current_period_carts_count, $carts_diff);
        }
        else {
          $current_period_carts_count = sprintf('%d (-)', $current_period_carts_count);
        }
      }
      $form['metrics']['new_carts'] = [
        '#theme' => 'commerce_dashboard_metrics_item',
        '#title' => $this->t('New carts'),
        '#values' => [$current_period_carts_count],
        '#attributes' => [
          'class' => ['metrics-item--carts'],
        ],
        '#metric_value_attributes' => [
          'class' => $carts_metric_classes ?? [],
        ],
      ];
    }
    $form['metrics']['placed_orders'] = [
      '#theme' => 'commerce_dashboard_metrics_item',
      '#title' => $this->t('Placed orders'),
      '#values' => [$count_placed_orders],
      '#attributes' => [
        'class' => ['metrics-item--orders'],
      ],
      '#metric_value_attributes' => [
        'class' => $placed_orders_metric_classes ?? [],
      ],
    ];
    $form['metrics']['gross_sales'] = [
      '#theme' => 'commerce_dashboard_metrics_item',
      '#title' => $this->t('Gross sales'),
      '#values' => [],
      '#attributes' => [
        'class' => ['metrics-item--gross'],
      ],
    ];
    $form['metrics']['average_order'] = [
      '#theme' => 'commerce_dashboard_metrics_item',
      '#title' => $this->t('Average order'),
      '#values' => [],
      '#attributes' => [
        'class' => ['metrics-item--avr-orders'],
      ],
    ];
    // For each currency returned, display the gross sales.
    foreach ($results as $currency_code => $result) {
      if (!empty($result['gross_total'])) {
        $gross_total_for_currency = $this->currencyFormatter->format($result['gross_total'], $result['currency_code'], [
          'maximum_fraction_digits' => 2,
        ]);
        if ($comparison_enabled) {
          $prior_period_gross_total = $prior_period_metrics[$currency_code]['gross_total'] ?? '0';
          if ($prior_period_gross_total !== '0') {
            $gross_variation = Calculator::divide(Calculator::subtract($result['gross_total'], $prior_period_gross_total), $prior_period_gross_total);
            $gross_variation = Calculator::multiply('100', $gross_variation);
          }
          if (isset($gross_variation)) {
            $value_class = Calculator::compare($gross_variation, '0') === 1 ? 'metrics-item__value--up' : 'metrics-item__value--down';
            $form['metrics']['gross_sales']['#metric_value_attributes']['class'][] = $value_class;
            $gross_total_for_currency = sprintf('%s (%+d%%)', $gross_total_for_currency, $gross_variation);
          }
          else {
            $gross_total_for_currency = $this->t('@gross_total (-)', [
              '@gross_total' => $gross_total_for_currency,
            ]);
          }
        }
        $form['metrics']['gross_sales']['#values'][] = $gross_total_for_currency;
      }
      if (!empty($result['average'])) {
        $average_for_currency = $this->currencyFormatter->format($result['average'], $result['currency_code'], [
          'maximum_fraction_digits' => 2,
        ]);
        if ($comparison_enabled) {
          $prior_period_average = $prior_period_metrics[$currency_code]['average'] ?? '0';
          if ($prior_period_average !== '0') {
            $average_variation = Calculator::divide(Calculator::subtract($result['average'], $prior_period_average), $prior_period_average);
            $average_variation = Calculator::multiply('100', $average_variation);
          }
          if (isset($average_variation)) {
            $value_class = Calculator::compare($average_variation, '0') === 1 ? 'metrics-item__value--up' : 'metrics-item__value--down';
            $form['metrics']['average_order']['#metric_value_attributes']['class'][] = $value_class;
            $average_for_currency = sprintf('%s (%+d%%)', $average_for_currency, $average_variation);
          }
          else {
            $average_for_currency = $this->t('@average (-)', [
              '@average' => $average_for_currency,
            ]);
          }
        }
        $form['metrics']['average_order']['#values'][] = $average_for_currency;
      }
    }

    // If the product module is enabled, build the "best-selling products"
    // table.
    if (Settings::get('commerce_dashboard_show_product_report', TRUE) &&
      $this->moduleHandler->moduleExists('commerce_product')) {
      $best_selling_product_ids = $this->getBestSellingProductIds($current_period_timestamp, $active_store?->id());
      $form['best_selling_products'] = [
        '#type' => 'table',
        '#empty' => $this->t('No products sold during this time.'),
        '#header' => [
          [
            'data' => $this->t('Best selling products'),
            'colspan' => 2,
          ],
        ],
        '#rows' => [],
      ];
      if ($best_selling_product_ids) {
        $products = $this->entityTypeManager->getStorage('commerce_product')->loadMultiple(array_keys($best_selling_product_ids));
        foreach ($products as $product) {
          $form['best_selling_products']['#rows'][] = [
            Link::fromTextAndUrl($product->label(), $product->toUrl()),
            Calculator::trim($best_selling_product_ids[$product->id()]),
          ];
        }
      }
    }
    if (Settings::get('commerce_dashboard_show_promotion_report', TRUE) &&
      $this->moduleHandler->moduleExists('commerce_promotion')) {
      $most_used_promotion_ids = $this->getMostUsedPromotionIds($current_period_timestamp, $active_store?->id());
      $form['most_used_promotions'] = [
        '#type' => 'table',
        '#empty' => $this->t('No promotions used during this time.'),
        '#header' => [
          [
            'data' => $this->t('Most used promotions'),
            'colspan' => 2,
          ],
        ],
        '#rows' => [],
      ];
      if ($most_used_promotion_ids) {
        $promotions = $this->entityTypeManager->getStorage('commerce_promotion')->loadMultiple(array_keys($most_used_promotion_ids));
        foreach ($promotions as $promotion) {
          $form['most_used_promotions']['#rows'][] = [
            $promotion->label(),
            $most_used_promotion_ids[$promotion->id()],
          ];
        }
      }
    }
    // Check if one of the tables has more rows than the other, add empty rows
    // to have a matching number of lines.
    if (isset($form['most_used_promotions'], $form['best_selling_products'])) {
      $max_rows = max(count($form['most_used_promotions']['#rows']), count($form['best_selling_products']['#rows']));
      // If there's a single row in one of the tables, the "#empty" text wil be
      // displayed, so no need for an empty row in this case.
      if ($max_rows > 1) {
        foreach (['most_used_promotions', 'best_selling_products'] as $report) {
          if (empty($form[$report]['#rows'])) {
            continue;
          }
          while (count($form[$report]['#rows']) < $max_rows) {
            $form[$report]['#rows'][] = ['-', '-'];
          }
        }
      }
    }
    // Store the results for 5 minutes.
    $expire = time() + static::CACHE_DURATION;
    $this->cache->set($cid, $form, $expire, ['commerce_order_dashboard_metrics_form']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Submit callback for switching period.
   */
  public static function switchPeriodSubmit(array $form, FormStateInterface $form_state) {
    $period = $form_state->getTriggeringElement()['#period'];
    $form_state->set('period', $period);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for the refresh button.
   */
  public static function refreshSubmit(array $form, FormStateInterface $form_state) {
    // Invalidate the "commerce_order_dashboard_metrics_form" cache tag so
    // the cache is invalidated for all periods / filters combination.
    Cache::invalidateTags(['commerce_order_dashboard_metrics_form']);
    $form_state->setRebuild();
  }

  /**
   * Gets the carts count for the given period.
   *
   * Note that the carts count includes carts created for the given period as
   * well as order placed within that period.
   *
   * @param int|array $period
   *   The period condition (either a timestamp or a range).
   * @param int|null $store_id
   *   (optional) The store ID.
   *
   * @return int
   *   The carts count for the given period.
   */
  protected function getCartsCountForPeriod(int|array $period, ?int $store_id = NULL): int {
    $carts_query = $this->connection->select('commerce_order');
    $period_operator = is_array($period) ? 'BETWEEN' : '>=';
    $or_condition = $carts_query->orConditionGroup();
    $placed_condition_group = $carts_query->andConditionGroup()
      ->condition('cart', 0)
      ->condition('placed', $period, $period_operator);
    $cart_condition_group = $carts_query->andConditionGroup()
      ->condition('cart', 1)
      ->condition('created', $period, $period_operator);
    $or_condition
      ->condition($cart_condition_group)
      ->condition($placed_condition_group);
    $carts_query->condition($or_condition);
    // Filter by store if specified.
    if (!empty($store_id)) {
      $carts_query->condition('store_id', $store_id);
    }

    return $carts_query->countQuery()->execute()->fetchField();
  }

  /**
   * Gets the order "metrics" for the given period.
   *
   * This returns the number of placed orders, the average order value and
   * the total ordered value for the given period, grouped by currency code.
   *
   * @param int|array $period
   *   The period condition (either a timestamp or a range).
   * @param int|null $store_id
   *   (optional) The store ID.
   *
   * @return array
   *   A multidimensional array for each currency code returned by the query.
   *   The keys returned for each currency returned are:
   *    - currency_code: The currency code.
   *    - average: The average order value.
   *    - gross_total: The gross total.
   *    - gross_total: The gross total.
   */
  protected function getOrderMetricsForPeriod(int|array $period, ?int $store_id = NULL): array {
    $query = $this->connection->select('commerce_order', 'co');
    $query->addField('co', 'total_price__currency_code', 'currency_code');
    $query->addExpression('COUNT(co.order_id)', 'count_orders');
    $query->addExpression('AVG(co.total_price__number)', 'average');
    $query->addExpression('SUM(co.total_price__number)', 'gross_total');
    // Add the store filter if specified.
    if (!empty($store_id)) {
      $query->condition('store_id', $store_id);
    }
    $period_operator = is_array($period) ? 'BETWEEN' : '>=';
    $query
      ->condition('co.state', ['draft', 'canceled'], 'NOT IN')
      ->condition('co.placed', $period, $period_operator)
      ->groupBy('currency_code');

    return $query->execute()->fetchAllAssoc('currency_code', \PDO::FETCH_ASSOC);
  }

  /**
   * Gets the best-selling product IDS for the given period.
   *
   * @param int $period_timestamp
   *   The period timestamp.
   * @param int|null $store_id
   *   (optional) The store ID.
   *
   * @return array
   *   An array keyed by product ID whose values are the product sold counts.
   */
  protected function getBestSellingProductIds(int $period_timestamp, ?int $store_id = NULL): array {
    // Get the order item types referencing product variations.
    $qualifying_order_item_types = array_filter(OrderItemType::loadMultiple(), function (OrderItemTypeInterface $order_item_type) {
      return $order_item_type->getPurchasableEntityTypeId() === 'commerce_product_variation';
    });
    if (!$qualifying_order_item_types) {
      return [];
    }
    $query = $this->connection->select('commerce_order_item', 'coi');
    $query->addField('cpv', 'entity_id', 'product_id');
    $query->addExpression('SUM(coi.quantity)', 'count_sold');
    $query->innerJoin('commerce_order__order_items', 'commerce_order__order_items', 'commerce_order__order_items.order_items_target_id = coi.order_item_id');
    // Exclude canceled orders.
    $query->innerJoin('commerce_order', 'co', 'co.order_id = commerce_order__order_items.entity_id AND co.state != :state', [
      ':state' => 'canceled',
    ]);
    $query->innerJoin('commerce_product__variations', 'cpv', 'cpv.variations_target_id = coi.purchased_entity');
    if ($store_id) {
      $query->condition('co.store_id', $store_id, '=');
    }
    $query
      ->condition('co.placed', $period_timestamp, '>=')
      ->condition('coi.type', array_keys($qualifying_order_item_types), 'IN')
      ->groupBy('cpv.entity_id')
      ->orderBy('count_sold', 'DESC')
      ->range(0, 5);

    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Gets the most used promotion IDS for the given period/store.
   *
   * @param int $period_timestamp
   *   The period timestamp.
   * @param int|null $store_id
   *   (optional) The store ID.
   *
   * @return array
   *   An array keyed by promotion ID whose values are the promotion usage.
   */
  protected function getMostUsedPromotionIds(int $period_timestamp, ?int $store_id = NULL): array {
    $query = $this->connection->select('commerce_promotion_usage', 'cpu');
    $query->addField('cpu', 'promotion_id');
    $query->addExpression('COUNT(cpu.usage_id)', 'usage_count');
    $query->innerJoin('commerce_order', 'co', 'co.order_id = cpu.order_id');
    $query->condition('co.placed', $period_timestamp, '>=');
    if ($store_id) {
      $query->condition('co.store_id', $store_id);
    }
    $query
      ->groupBy('cpu.promotion_id')
      ->orderBy('usage_count', 'DESC')
      ->range(0, 5);

    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Builds the cache ID.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The cache ID.
   */
  protected function buildCacheId(FormStateInterface $form_state): string {
    $cid = "commerce_order_dashboard_metrics_form:{$form_state->get('period')}";
    $values = $form_state->getValues();
    if (!empty($values['store_id'])) {
      $cid .= ':' . $values['store_id'];
    }
    if (!empty($values['compare'])) {
      $cid .= ':compare';
    }

    return $cid;
  }

  /**
   * Gets the first day of the week configured.
   *
   * @return string
   *   The first day of the week.
   */
  protected function getFirstDayOfWeek(): string {
    return match ($this->config('system.date')->get('first_day')) {
      0 => 'sunday',
      2 => 'tuesday',
      3 => 'wednesday',
      4 => 'thursday',
      5 => 'friday',
      6 => 'saturday',
      default => 'monday',
    };
  }

}
