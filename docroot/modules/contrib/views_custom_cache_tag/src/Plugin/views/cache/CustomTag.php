<?php

namespace Drupal\views_custom_cache_tag\Plugin\views\cache;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\views\Plugin\views\cache\Tag;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple caching of query results for Views displays.
 *
 * @ingroup views_cache_plugins
 *
 * @ViewsCache(
 *   id = "custom_tag",
 *   title = @Translation("Custom Tag based"),
 *   help = @Translation("Tag based caching of data. Caches will persist until any related cache tags are invalidated.")
 * )
 */
class CustomTag extends Tag {

  use MessengerTrait;

  /**
   * Overrides Drupal\views\Plugin\Plugin::$usesOptions.
   *
   * @var bool
   */
  protected $usesOptions = TRUE;

  /**
   * Constructs a CustomTag cache plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *    The time service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $lifespan = $this->getLifespan();
    if ($lifespan === Cache::PERMANENT) {
      return $this->t('Custom Tag');
    }
    elseif ($this->options['custom_tag_output_lifespan'] === 'custom') {
      return $this->t('Custom tag (until %time)', ['%time' => $this->options['custom_tag_output_lifespan_expression']]);
    }
    return $this->t('Custom tag (%ttl sec lifespan)', ['%ttl' => $lifespan]);
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['custom_tag'] = ['default' => ''];
    $options['custom_tag_output_lifespan'] = ['default' => Cache::PERMANENT];
    $options['custom_tag_output_lifespan_expression'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['custom_tag'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom tag list'),
      '#description' => $this->t('Custom tag list, separated by new lines. Caching based on custom cache tag must be manually cleared using custom code. You can use Twig (to explode a multi-value contextual filter into multiple tags for example) as long as the result delivers each tag in a separate line.'),
      '#default_value' => $this->options['custom_tag'],
    ];

    // Setup the tokens for fields.
    $optgroup_arguments = (string) $this->t('Arguments');

    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $options[$optgroup_arguments]["{{ arguments.$arg }}"] = $this->t('@argument title', ['@argument' => $handler->adminLabel()]);
      $options[$optgroup_arguments]["{{ raw_arguments.$arg }}"] = $this->t('@argument input', ['@argument' => $handler->adminLabel()]);
    }

    // We have some options, so make a list.
    if (!empty($options)) {
      $output['description'] = [
        '#markup' => '<p>' . $this->t("The following replacement tokens are available for cache tags.") . '</p>',
      ];
      foreach (array_keys($options) as $type) {
        if (!empty($options[$type])) {
          $items = [];
          foreach ($options[$type] as $key => $value) {
            $items[] = $key . ' == ' . $value;
          }
          $item_list = [
            '#theme' => 'item_list',
            '#items' => $items,
          ];
          $output['list'] = $item_list;
        }
      }
      $form['tokens'] = $output;
    }

    $options = [60, 300, 1800, 3600, 21600, 518400];
    $options = array_map([$this->dateFormatter, 'formatInterval'], array_combine($options, $options));
    $options = [Cache::PERMANENT => $this->t('Unlimited (tag-based only)')] + $options + ['custom' => $this->t('Custom')];

    $form['custom_tag_output_lifespan'] = [
      '#type' => 'select',
      '#title' => $this->t('Rendered output'),
      '#description' => $this->t('The length of time rendered HTML output should be cached.'),
      '#options' => $options,
      '#default_value' => $this->options['custom_tag_output_lifespan'],
    ];
    $form['custom_tag_output_lifespan_expression'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom expression'),
      '#size' => '25',
      '#maxlength' => '30',
      // @todo Add examples.
      '#description' => $this->t('Evaluated with strtotime() and converted to a TTL relative to now.'),
      '#default_value' => $this->options['custom_tag_output_lifespan_expression'],
      '#states' => [
        'visible' => [
          ':input[name="cache_options[custom_tag_output_lifespan]"]' => ['value' => 'custom'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $cache_options = $form_state->getValue('cache_options');
    if ($cache_options['custom_tag_output_lifespan'] === 'custom') {
      if (empty(trim($cache_options['custom_tag_output_lifespan_expression']))) {
        $form_state->setError($form['custom_tag_output_lifespan_expression'], $this->t('If custom is selected a time expression must be provided.'));
      }
      $target = strtotime($cache_options['custom_tag_output_lifespan_expression']);
      // @todo Target might be in the past?
      if ($target === FALSE || ($target <= $this->time->getRequestTime())) {
        $form_state->setError($form['custom_tag_output_lifespan_expression'], $this->t('Custom time is not valid.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();

    // Remove the the list cache tags for the entity types listed in this view.
    // @see CachePluginBase::getCacheTags().
    $entity_information = $this->view->getQuery()->getEntityTableInfo();
    if (!empty($entity_information)) {
      // Add the list cache tags for each entity type used by this view.
      foreach ($entity_information as $metadata) {
        $remove = \Drupal::entityTypeManager()->getDefinition($metadata['entity_type'])->getListCacheTags();
        $tags = array_diff($tags, $remove);
      }
    }

    $custom_tags = $this->view->getStyle()->tokenizeValue($this->options['custom_tag'], 0);
    $custom_tags = preg_split('/\r\n|[\r\n]/', $custom_tags);
    $custom_tags = array_map('trim', $custom_tags);
    $custom_tags = array_filter($custom_tags);
    return Cache::mergeTags($custom_tags, $tags);
  }

  /**
   * Get lifespan.
   *
   * @return int
   */
  protected function getLifespan(): int {
    if ($this->options['custom_tag_output_lifespan'] === 'custom') {
      $expression = $this->options['custom_tag_output_lifespan_expression'];
      $target = strtotime($expression);

      // @todo Handle negative values here as well?
      return $target - $this->time->getRequestTime();
    }

    return (int) $this->options['custom_tag_output_lifespan'];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheExpire($type) {
    $lifespan = $this->getLifespan();
    if ($lifespan === Cache::PERMANENT) {
      return FALSE;
    }
    return $this->time->getRequestTime() - $lifespan;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheGet($type) {
    $result = parent::cacheGet($type);

    // This can be used to debug/test the views cache result.
    if ($type == 'results' && !$result && \Drupal::state()->get('views_custom_cache_tag.execute_debug', FALSE)) {
      $this->messenger()->addMessage('Executing view ' . $this->view->storage->id() . ':' . $this->view->current_display . ':' . implode(',', $this->view->args) . ' (' . implode(',', $this->view->getCacheTags()) . ')');
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function cacheSetMaxAge($type) {
    $lifespan = $this->getLifespan();
    if ($lifespan === Cache::PERMANENT) {
      return Cache::PERMANENT;
    }
    return $lifespan;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCacheMaxAge() {
    // The max age, unless overridden by some other piece of the rendered code
    // is determined by the output time setting.
    return $this->cacheSetMaxAge('output');
  }

}
