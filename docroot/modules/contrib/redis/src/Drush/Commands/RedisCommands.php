<?php

namespace Drupal\redis\Drush\Commands;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Drupal\redis\Cache\RedisCacheTagsChecksum;
use Drupal\redis\ClientFactory;
use Drupal\redis\ClientInterface;
use Drupal\redis\RedisPrefixTrait;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drush commandfile for redis module.
 */
final class RedisCommands extends DrushCommands {

  use AutowireTrait;
  use RedisPrefixTrait;

  /**
   * The redis client.
   */
  protected ?ClientInterface $redis = NULL;

  /**
   * Constructs a RedisCommands object.
   */
  public function __construct(
    #[Autowire(service: 'redis.factory')]
    protected readonly ClientFactory $clientFactory,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
    protected CacheTagsChecksumInterface $cacheTagsChecksum,
    #[Autowire(param: 'cache_bins')]
    protected array $cacheBins,
    #[Autowire(param: 'renderer.config')]
    protected array $rendererConfig
  ) {
    if ($this->clientFactory->hasClient()) {
      $this->redis = $this->clientFactory->getClient();
    }
    parent::__construct();
  }

  public static function create(ContainerInterface $container) {
    if (\Drupal::hasContainer()) {
      $container = \Drupal::getContainer();
    }
    return new static($container->get('redis.factory'), $container->get('date.formatter'), $container->get('datetime.time'), $container->get('cache_tags.invalidator.checksum'), $container->getParameter('cache_bins'), $container->getParameter('renderer.config'));
  }

  /**
   * Redis report.
   */
  #[CLI\Command(name: 'redis:report')]
  #[CLI\Option(name: 'max-cachetags', description: 'Maximum amount of cache tags to process')]
  #[CLI\Option(name: 'results', description: 'Amount of cache tags and render cache entries with most variations to display')]
  #[CLI\Option(name: 'quick', description: 'Only show basic INFO-based stats and recommendations')]
  public function report($options = ['max-cachetags' => 50000, 'results' => 20, 'quick' => FALSE]): void {
    if ($this->redis === NULL) {
      $this->logger()->error('No Redis client connected. Verify cache settings.');
      return;
    }

    $info = $this->redis->info();

    if (!empty($info['maxmemory'])) {
      $memory_value = dt('@used_memory / @max_memory (@used_percentage%), maxmemory policy: @policy', [
        '@used_memory' => $info['used_memory_human'],
        '@max_memory' => ByteSizeMarkup::create($info['maxmemory']),
        '@used_percentage' => (int) ($info['used_memory'] / $info['maxmemory'] * 100),
        '@policy' => $info['maxmemory_policy'] ?? '',
      ]);
    }
    else {
      $memory_value = dt('@used_memory / unlimited, maxmemory policy: @policy', [
        '@used_memory' => $info['used_memory_human'],
        '@policy' => $info['maxmemory_policy'] ?? '',
      ]);
    }

    $data = [
      'client' => $this->clientFactory->getClientName(),
      'version' => $info['valkey_version'] ? 'Valkey ' . $info['valkey_version'] : $info['redis_version'],
      'connected_clients' => $info['connected_clients'],
      'keys' => $info['db_size'],
      'memory' => $memory_value,
      'uptime' => $this->dateFormatter->formatInterval($info['uptime_in_seconds']),
      'read_write' => isset($info['total_net_output_bytes']) ? dt('@read read (@percent_read%), @write written (@percent_write%), @commands commands in @connections connections.', [
        '@read' => ByteSizeMarkup::create($info['total_net_output_bytes']),
        '@percent_read' => round(100 / ($info['total_net_output_bytes'] + $info['total_net_input_bytes']) * ($info['total_net_output_bytes'])),
        '@write' => ByteSizeMarkup::create($info['total_net_input_bytes']),
        '@percent_write' => round(100 / ($info['total_net_output_bytes'] + $info['total_net_input_bytes']) * ($info['total_net_input_bytes'])),
        '@commands' => $info['total_commands_processed'],
        '@connections' => $info['total_connections_received'],
      ]) : dt('These metrics are not available, please check your Redis server configuration.'),
    ];

    if (!empty($info['redis_mode'])) {
      $data['mode'] = Unicode::ucfirst($info['redis_mode']);
    }

    $result = new PropertyList($data);

    /** @var \Consolidation\OutputFormatters\FormatterManager $formatter_manager */
    $formatter_manager = Drush::service('formatterManager');

    $opts = [
      FormatterOptions::LIST_DELIMITER => ': ',
      FormatterOptions::TABLE_STYLE => 'compact',
      FormatterOptions::TERMINAL_WIDTH => self::getTerminalWidth(),
    ];
    $compact_formatter_options = new FormatterOptions([], $opts);
    $formatter_manager->write($this->output(), 'table', $result, $compact_formatter_options);

    if ($info['maxmemory_policy'] == 'noeviction') {
      $eviction_url = Url::fromUri('https://valkey.io/topics/lru-cache', [
        'fragment' => 'eviction-policies',
        'attributes' => [
          'target' => '_blank',
        ],
      ]);
      $this->io()->writeln('');
      $this->logger()->warning(dt('It is recommended to configure the maxmemory policy to e.g. volatile-lru, see eviction documentation: @documentation_url.', [
        '@documentation_url' => $eviction_url->toString(),
      ]));
    }
    elseif (str_starts_with($info['maxmemory_policy'], 'allkeys')) {
      $readme_url = Url::fromUri('https://project.pages.drupalcode.org/redis/', [
        'attributes' => [
          'target' => '_blank',
        ],
      ]);
      $eviction_url = Url::fromUri('https://valkey.io/topics/lru-cache', [
        'fragment' => 'eviction-policies',
        'attributes' => [
          'target' => '_blank',
        ],
      ]);
      $this->io()->writeln('');
      $this->logger()->warning(dt('Using an allkeys eviction policy may drop cache tag checksums or other persistent data, consider using a volatile eviction policy, see the module documentation (:readme_url) and eviction documentation (:documentation_url).', [
        ':documentation_url' => $eviction_url->toString(),
        ':readme_url' => $readme_url->toString(),
      ]));
    }

    if ($options['quick']) {
      return;
    }

    $start = microtime(TRUE);

    $progress = $this->io()->progress('Calculating cache statistics', $info['db_size']);
    $progress->start();

    $current_time = $this->time->getRequestTime();

    $prefix_length = strlen($this->getPrefix()) + 1;

    $bin_statistics = [];
    foreach ($this->cacheBins as $bin) {
      $bin_statistics[$bin] = [
        'bin' => $bin,
        'count' => 0,
        'size' => 0,
        'expired' => 0,
        'invalidated' => 0,
      ];
    }

    $required_cached_contexts = array_flip($this->rendererConfig['required_cache_contexts']);

    $cache_tags = [];
    $cache_tags_max = FALSE;
    foreach ($this->redis->scan($this->getPrefix() . ':cachetags:*') as $key) {
      $second_colon_pos = mb_strpos($key, ':', $prefix_length);
      if ($second_colon_pos !== FALSE) {
        $cid = mb_substr($key, $second_colon_pos + 1);
        if (count($cache_tags) < $options['max-cachetags']) {
          $cache_tags[$cid] = [
            'tag' => $cid,
            'invalidations' => $this->redis->get($key),
            'usages' => 0,
          ];
        }
        else {
          $cache_tags_max = TRUE;
        }
      }
    }


    $render_cache_variations = [];

    $i = 0;
    foreach ($this->redis->scan($this->getPrefix() . '*') as $key) {
      $i++;
      $second_colon_pos = mb_strpos($key, ':', $prefix_length);
      if ($second_colon_pos !== FALSE) {
        $bin = mb_substr($key, $prefix_length, $second_colon_pos - $prefix_length);

        $cid = mb_substr($key, $second_colon_pos + 1);
        if (!isset($bin_statistics[$bin])) {
          continue;
        }
        $bin_statistics[$bin]['count']++;

        $item = $this->redis->hGetAll($key);

        if (!empty($item['expire']) && $item['expire'] != -1) {
          if ($item['expire'] - $current_time) {
            $bin_statistics[$bin]['expired']++;
          }
        }

        // Count invalidated items, directly or through tags.
        if (isset($item['valid']) && !$item['valid']) {
          $bin_statistics[$bin]['invalidated']++;
        }
        elseif (!empty($item['tags'])) {
          $tags = explode(' ', $item['tags']);
          if (!$this->cacheTagsChecksum->isValid($item['checksum'], $tags)) {
            $bin_statistics[$bin]['invalidated']++;
          }

          // Keep track of a usage count by tag.
          foreach ($tags as $tag) {
            if (isset($cache_tags[$tag])) {
              $cache_tags[$tag]['usages']++;
            }
          }
        }

        $bin_statistics[$bin]['size'] += $this->redis->rawCommand('MEMORY', 'USAGE', $key, 'SAMPLES', 0);

        if ($bin == 'render') {
          $first_context = mb_strpos($cid, '[');
          if ($first_context) {
            $cache_key_only = mb_substr($cid, 0, $first_context - 1);
            if (!isset($render_cache_variations[$cache_key_only])) {
              $render_cache_variations[$cache_key_only] = [
                'key' => $cache_key_only,
                'count' => 1,
                'contexts' => [],
              ];
            }
            else {
              $render_cache_variations[$cache_key_only]['count']++;
            }

            if (preg_match_all('/\[([a-z0-9:_.]+)]=([^:]*)/', $cid, $matches)) {
              foreach ($matches[1] as $context) {
                if (!isset($required_cached_contexts[$context])) {
                  $render_cache_variations[$cache_key_only]['contexts'][$context] = $context;
                }
              }
            }
          }
        }
      }

      if ($i % 100 == 0) {
        $progress->advance(100);
      }
    }

    $bin_statistics = array_filter($bin_statistics, fn ($bin_statistic) => $bin_statistic['count'] > 0);

    // Calculate impact as the sum of inalidations and usages.
    foreach ($cache_tags as $cid => $tag_info) {
      $cache_tags[$cid]['impact'] = $tag_info['invalidations'] * $tag_info['usages'];
    }

    uasort($render_cache_variations, fn ($a, $b) => $b['count'] <=> $a['count']);
    uasort($cache_tags, fn ($a, $b) => $b['impact'] <=> $a['impact']);
    uasort($bin_statistics, fn ($a, $b) => $b['size'] <=> $a['size']);

    // Format bin statistics.
    foreach ($bin_statistics as &$bin_statistic) {
      $bin_statistic['size'] = ByteSizeMarkup::create($bin_statistic['size']);
      if ($bin_statistic['expired']) {
        $bin_statistic['expired'] = $bin_statistic['expired'] . '(' . round(100 / $bin_statistic['count'] * $bin_statistic['expired'], 2) . '%)';
      }
      if ($bin_statistic['invalidated']) {
        $bin_statistic['invalidated'] = $bin_statistic['invalidated'] . ' (' . round(100 / $bin_statistic['count'] * $bin_statistic['invalidated'], 2) . '%)';
      }
    }

    $this->io()->writeln('Bin statistics:');

    $opts = [
      FormatterOptions::TERMINAL_WIDTH => self::getTerminalWidth(),
    ];
    $table_formatter_options = new FormatterOptions([], $opts);

    $table = new RowsOfFields($bin_statistics);
    $formatter_manager->write($this->output(), 'table', $table, $table_formatter_options);

    foreach ($render_cache_variations as &$render_cache_variation) {
      $render_cache_variation['contexts'] = implode(', ', $render_cache_variation['contexts']);
    }

    $this->io()->writeln('');
    $this->io()->writeln('');
    $this->io()->writeln('Render cache items with many variations');
    $table = new RowsOfFields(array_slice($render_cache_variations, 0, $options['results']));
    $formatter_manager->write($this->output(), 'table', $table, $table_formatter_options);

    $this->io()->writeln('');
    $this->io()->writeln('');
    $this->io()->writeln('Most frequently invalidated cache tags:');
    $cache_tags_table = new RowsOfFields(array_slice($cache_tags, 0, $options['results']));
    $formatter_manager->write($this->output(), 'table', $cache_tags_table, $table_formatter_options);

    if (!$cache_tags && !$this->cacheTagsChecksum instanceof RedisCacheTagsChecksum) {
      $this->logger()->warning(dt('No cache tags found, make sure that the redis cache tag checksum service is used. See example.services.yml on root of this module.'));
    }

    if ($cache_tags_max) {
      $this->logger()->warning(dt('Cache tag count incomplete, only counted @count cache tags. Some cache tags may not be counted, consider increasing with --max-cachetags.', [
        '@count' => count($cache_tags),
      ]));
    }

    $end = microtime(TRUE);

    $this->io()->writeln('');
    $this->logger()->notice(dt('Processed @count keys in @time seconds and @memory peak memory usage.', [
      '@count' => $i,
      '@time' => round(($end - $start), 4),
      '@memory' => ByteSizeMarkup::create(memory_get_peak_usage(TRUE)),
    ]));
  }

  /**
   * Redis tag usage report.
   */
  #[CLI\Command(name: 'redis:tag-usage')]
  #[CLI\Help('List cache items that use a given tag')]
  #[CLI\Argument(name: 'tag', description: 'Name of the tag to search for')]
  #[CLI\Option(name: 'max', description: 'Maximum amount results to display')]
  #[CLI\Option(name: 'bins', description: 'Limit the search to the given bins, comma separated')]
  #[CLI\FieldLabels(labels: [
    'bin' => 'Bin',
    'cid' => 'Cid',
    'invalidated' => 'invalidated',
  ])]
  public function tagUsage($tag, $options = ['max' => 1000, 'bins' => '', 'format' => 'table']) {
    $bins = [];
    if ($options['bins']) {
      $bins = array_filter(array_map('trim', explode(',', $options['bins'])));
    }

    if ($this->redis === NULL) {
      $this->logger()->error('No Redis client connected. Verify cache settings.');
      return -1;
    }

    $start = microtime(TRUE);

    $bin_statistics = [];
    foreach ($this->cacheBins as $bin) {
      $bin_statistics[$bin] = [
        'bin' => $bin,
        'count' => 0,
        'size' => 0,
        'expired' => 0,
        'invalidated' => 0,
      ];
    }

    $progress = $this->io()->progress('Find items with the given cache tag', $this->redis->dbSize());
    $progress->start();

    $prefix_length = strlen($this->getPrefix()) + 1;

    $i = 0;
    $usages = [];
    foreach ($this->redis->scan($this->getPrefix() . '*') as $key) {
      $i++;
      $second_colon_pos = mb_strpos($key, ':', $prefix_length);
      if ($second_colon_pos !== FALSE) {
        $bin = mb_substr($key, $prefix_length, $second_colon_pos - $prefix_length);

        $cid = mb_substr($key, $second_colon_pos + 1);
        if (!isset($bin_statistics[$bin])) {
          continue;
        }

        if ($bins && !in_array($bin, $bins)) {
          continue;
        }

        $tags = $this->redis->hget($key, 'tags');
        if ($tags) {
          $tags = explode(' ', $tags);
          if (in_array($tag, $tags)) {
            $usages[$cid] = [
              'bin' => $bin,
              'cid' => $cid,
              'invalidated' => 0,
            ];
            $checksum = $this->redis->hget($key, 'checksum');
            if (!$this->cacheTagsChecksum->isValid($checksum, $tags)) {
              $usages[$cid]['invalidated'] = 1;
            }

            if (count($usages) >= $options['max']) {
              break;
            }
          }
        }
      }

      if ($i % 100 == 0) {
        $progress->advance(100);
      }
    }

    $end = microtime(TRUE);

    Drush::logger()->notice(dt('Processed @count keys in @time seconds and @memory peak memory usage, found @results results.', [
      '@count' => $i,
      '@time' => round(($end - $start), 4),
      '@memory' => ByteSizeMarkup::create(memory_get_peak_usage(TRUE)),
      '@results' => count($usages)
    ]));

    return new RowsOfFields($usages);
  }

  public static function getTerminalWidth(): int
  {
    $term = new Terminal();
    return $term->getWidth();
  }

}
