<?php

namespace Drupal\memcache\Driver;

use Drupal\memcache\Connection\MemcacheConnection;
use Drupal\memcache\Connection\MemcachedConnection;
use Drupal\memcache\MemcacheSettings;
use Drupal\memcache\MemcacheException;

/**
 * Factory class for creation of Memcache objects.
 */
class MemcacheDriverFactory {

  /**
   * The settings object.
   *
   * @var \Drupal\memcache\MemcacheSettings
   */
  protected $settings;

  /**
   * The connection class reference.
   *
   * @var string
   */
  protected $connectionClass;

  /**
   * The driver class reference.
   *
   * @var string
   */
  protected $driverClass;

  /**
   * Whether to connect to memcache using a persistent connection.
   *
   * @var bool
   */
  protected $persistent;

  /**
   * An array of Memcache connections keyed by bin.
   *
   * @var \Drupal\memcache\Connection\MemcacheConnectionInterface[]
   */
  protected $connections = [];

  /**
   * An array of configured servers.
   *
   * @var array
   */
  protected $servers = [];

  /**
   * An array of configured bins.
   *
   * @var string[]
   */
  protected $bins = [];

  /**
   * An array of failed connections to configured servers keyed by server name.
   *
   * @var bool[]
   */
  protected $failedConnectionCache = [];

  /**
   * Constructs a MemcacheDriverFactory object.
   *
   * @param \Drupal\memcache\MemcacheSettings $settings
   *   The settings object.
   */
  public function __construct(MemcacheSettings $settings) {
    $this->settings = $settings;

    $this->initialize();
  }

  /**
   * Returns a Memcache object based on settings and the bin requested.
   *
   * @param string $bin
   *   The bin which is to be used.
   * @param bool $flush
   *   Rebuild the bin/server/cache mapping.
   *
   * @return \Drupal\memcache\DrupalMemcacheInterface|bool
   *   A Memcache object.
   */
  public function get($bin = NULL, $flush = FALSE) {
    if ($flush) {
      $this->flush();
    }

    if (empty($this->connections) || empty($this->connections[$bin])) {
      // If there is no cluster for this bin in $bins, cluster is
      // 'default'.
      $cluster = empty($this->bins[$bin]) ? 'default' : $this->bins[$bin];

      // If this bin isn't in our $bins configuration array, and the
      // 'default' cluster is already initialized, map the bin to 'default'
      // because we always map the 'default' bin to the 'default' cluster.
      if (empty($this->bins[$bin]) && !empty($this->connections['default'])) {
        $this->connections[$bin] = &$this->connections['default'];
      }
      else {
        // Create a new Memcache object. Each cluster gets its own Memcache
        // object.
        /** @var \Drupal\memcache\Connection\MemcacheConnectionInterface $memcache */
        $memcache = new $this->connectionClass($this->settings);

        // A variable to track whether we've connected to the first server.
        $init = FALSE;

        // Link all the servers to this cluster.
        foreach ($this->servers as $s => $c) {
          if ($c == $cluster && !isset($this->failedConnectionCache[$s])) {
            if ($memcache->addServer($s, $this->persistent) && !$init) {
              $init = TRUE;
            }

            if (!$init) {
              $this->failedConnectionCache[$s] = FALSE;
            }
          }
        }

        if ($init) {
          // Map the current bin with the new Memcache object.
          $this->connections[$bin] = $memcache;

          // Now that all the servers have been mapped to this cluster, look for
          // other bins that belong to the cluster and map them too.
          foreach ($this->bins as $b => $c) {
            if (($c == $cluster) && ($b != $bin)) {
              // Map this bin and cluster by reference.
              $this->connections[$b] = &$this->connections[$bin];
            }
          }
        }
        else {
          throw new MemcacheException('Memcache instance could not be initialized. Check memcache is running and reachable');
        }
      }
    }

    return empty($this->connections[$bin]) ? FALSE : new $this->driverClass($this->settings, $this->connections[$bin]->getMemcache(), $bin);
  }

  /**
   * Initializes memcache settings.
   */
  protected function initialize() {
    // If an extension is specified in settings.php, use that when available.
    $preferred = $this->settings->get('extension', NULL);

    if (isset($preferred) && class_exists($preferred)) {
      $extension = $preferred;
    }
    // If no extension is set, default to Memcached.
    elseif (class_exists('Memcached')) {
      $extension = \Memcached::class;
    }
    elseif (class_exists('Memcache')) {
      $extension = \Memcache::class;
    }
    else {
      throw new MemcacheException('No Memcache extension found');
    }

    // @todo Make driver class configurable?
    $this->connectionClass = MemcachedConnection::class;
    $this->driverClass = MemcachedDriver::class;

    if ($extension === \Memcache::class) {
      $this->connectionClass = MemcacheConnection::class;
      $this->driverClass = MemcacheDriver::class;
    }

    // Values from settings.php.
    $this->servers = $this->settings->get('servers', ['127.0.0.1:11211' => 'default']);
    $this->bins = $this->settings->get('bins', ['default' => 'default']);

    // Indicate whether to connect to memcache using a persistent connection.
    // Note: this only affects the Memcache PECL extension, and does not affect
    // the Memcached PECL extension.  For a detailed explanation see:
    // http://drupal.org/node/822316#comment-4427676
    $this->persistent = $this->settings->get('persistent', FALSE);
  }

  /**
   * Flushes the memcache bin/server/cache mappings and closes connections.
   */
  protected function flush() {
    foreach ($this->connections as $cluster) {
      $cluster->close();
    }

    $this->connections = [];
  }

}
