# Drupal Redis documentation

Integration of the [Drupal Redis module](https://www.drupal.org/project/redis) with the [Redis](https://redis.io/) key-value store as well as compatible alternatives/forks such as Valkey. It provides cache, lock, flood and queue backends and a cache performance report.

Note: If reading the README.md file directly, read the [generated online version of this](https://project.pages.drupalcode.org/redis/) which is a formatted, linked and searchable.

## Requirements

This module requires the [Redis](https://redis.io/) key-value store.

## Quick Start for DDEV

Here's a short introductions to get started in DDEV. Refer to the
documentation of your hosting platform on how to integrate it, see also [Additional links](#additional-information).

First install Redis itself, then the Drupal Redis module, and configure it through `settings.php`.

### 1. Install Redis in DDEV

Install the [DDEV Redis add-on](https://github.com/ddev/ddev-redis) and
restart DDEV. A new Redis service is created. DDEV-specific settings are
set up, which we won't use:

    $ ddev get ddev/ddev-redis
    $ ddev restart

### 2. Install the Drupal Redis module

Now that Redis is installed and ready, install the Drupal [Redis](https://www.drupal.org/project/redis) module.

### 3. Redis settings

DDEV includes a new `settings.ddev.redis.php` file in `settings.php` ("`IS_DDEV_PROJECT`") but it is recommended to use the provided example config as a starting point and adapt the next paragraph.

You'll need most settings also for a non-DDEV setup on your server, so instead
copy [settings.redis.example.php](https://git.drupalcode.org/project/redis/-/blob/2.x/settings.redis.example.php)
to `settings.redis.php`, and include it with the below in `settings.php`.
For local DDEV setup, override the hostname:

    # Redis settings file
    if (file_exists($app_root . '/' . $site_path . '/settings.redis.php')) {
      include $app_root . '/' . $site_path . '/settings.redis.php';
      if (getenv('IS_DDEV_PROJECT') == 'true') {
        $settings['redis.connection']['host'] = 'redis';
      }
    }

Depending on the setup, this can also be set up and customized in the respective `settings.locaal.php` in each environment instead.

### 4. Verify installation

Drupal should now store its cache in the Redis database.
Check by visiting a cached page, then check number of keys on the last line, with this command:

    $ ddev redis-cli INFO
    [...]
    # Keyspace
    db0:keys=1517,expires=1514,avg_ttl=30565038115,subexpiry=0

Congratulations, you are now caching with Redis!

## Install Redis and PhpRedis on Debian-flavor server

There are 3 different possible [Redis client integrations](#redis-clients), this example is using [PhpRedis](https://github.com/phpredis/phpredis).

Install the Redis package (which creates a new service) and the PhpRedis
extension on the server, and enable it:

    $ sudo apt install redis-server php-redis
    $ sudo phpenmod redis

Check if it is running:

    $ sudo service redis status

Make it start automatically after every boot:

    $ sudo systemctl enable redis-server

For a production environment, ensure that you optimize redis for use
as a cache, see [Redis memory management](#redis-memory-management)

## Redis Report

You can see Drupal specific info via the built-in Redis report in Drupal under
`/admin/reports/redis`, for these values:

- Warnings
- Total cache tag invalidations
- Client in use, e.g. PhpRedis
- Redis Version
- Mode
- Connected clients
- Number of keys
- Memory settings: `maxmemory` value and eviction policy, e.g. `allkeys-lfu`
- Uptime
- Read/Write
- Keys per cache bin
- Render cache entries with most variations

## Redis clients

This package provides support for three different Redis clients.

  * PhpRedis
  * Predis
  * Relay (See configuration recommendations for in-memory cache)

By default, the first available client will be used in that order, to configure
it explicitly, use

    $settings['redis.connection']['interface'] = 'PhpRedis';

Each supported client has its own README client specific installation and
configuration options.

## Drupal Redis settings

### Common configuration

See settings.redis.example.php for a quick start and recommended configuration.

Customize the default host and port:

    $settings['redis.connection']['host'] = '127.0.0.1';
    $settings['redis.connection']['port'] = 6379;

Use Redis for all caches:

    $settings['cache']['default'] = 'cache.backend.redis';

Configure usage for a specific bin

    $settings['cache']['bins']['render'] = 'cache.backend.redis';

The example.services.yml from the module will replace the cache tags checksum
service, flood and the lock backends (check the file for the current list).
Either include it directly or copy the desired service definitions into a site
specific services.yml file for more control.

    $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';

It is recommended to enable the redis module, to use the report feature, but
the redis.services.yml can also be included explicitly.

    $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';

Compressing the data stored in redis can massively reduce the needed storage.

To enable, set the minimal length after which the cached data should be
compressed:

    $settings['redis_compress_length'] = 100;

By default, compression level 1 is used, which provides considerable storage
optimization with minimal CPU overhead. It can be changed, but this is
discouraged as it likely results in minimal storage optimizations at a higher
CPU cost.

    $settings['redis_compress_level'] = 6;

Redis can also be used for the container cache bin, the bootstrap container
needs to be configured for that.

    $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');

    $settings['bootstrap_container_definition'] = [
      'parameters' => [],
      'services' => [
        'redis.factory' => [
          'class' => 'Drupal\redis\ClientFactory',
        ],
        'cache.backend.redis' => [
          'class' => 'Drupal\redis\Cache\CacheBackendFactory',
          'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
        ],
        'cache.container' => [
          'class' => '\Drupal\redis\Cache\PhpRedis',
          'factory' => ['@cache.backend.redis', 'get'],
          'arguments' => ['container'],
        ],
        'cache_tags_provider.container' => [
          'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
          'arguments' => ['@redis.factory'],
        ],
        'serialization.phpserialize' => [
          'class' => 'Drupal\Component\Serialization\PhpSerialize',
        ],
      ],
    ];

### Use persistent connections

This mode needs the following setting:

    $settings['redis.connection']['persistent'] = TRUE;

### Using a specific database

Per default, Redis ships the database "0". All default connections will be use
this one if nothing is specified.

Depending on you OS or OS distribution, you might have numerous database. To
use one in particular, just add to your settings.php file:

    $settings['redis.connection']['base']      = 12;

### Connection to a password protected instance

If you are using a password protected instance, specify the password this way:

    $settings['redis.connection']['password'] = "mypassword";

Depending on the backend, using a wrong auth will behave differently:

- Predis will throw an exception and make Drupal fail during early boostrap.

- PhpRedis will make Redis calls silent and creates some PHP warnings, thus
  Drupal will behave as if it was running with a null cache backend (no cache
  at all).

### Prefixing site cache entries (avoiding sites name collision)

If you need to differentiate multiple sites using the same Redis instance and
database, you will need to specify a prefix for your site cache entries.

Cache prefix configuration attempts to use a unified variable across contrib
backends that support this feature. This variable name is 'cache_prefix'.

This variable is polymorphic, the simplest version is to provide a raw string
that will be the default prefix for all cache bins:

    $settings['cache_prefix'] = 'mysite_';

Alternatively, to provide the same functionality, you can provide the variable
as an array:

    $settings['cache_prefix']['default'] = 'mysite_';

This allows you to provide different prefix depending on the bin name. Common
usage is that each key inside the 'cache_prefix' array is a bin name, the value
the associated prefix. If the value is FALSE, then no prefix is
used for this bin.

The 'default' meta bin name is provided to define the default prefix for non
specified bins. It behaves like the other names, which means that an explicit
FALSE will order the backend not to provide any prefix for any non specified
bin.

Here is a complex sample:

    // Default behavior for all bins, prefix is 'mysite_'.
    $settings['cache_prefix']['default'] = 'mysite_';

    // Set no prefix explicitly for 'cache' and 'cache_bootstrap' bins.
    $settings['cache_prefix']['cache'] = FALSE;
    $settings['cache_prefix']['cache_bootstrap'] = FALSE;

    // Set another prefix for 'cache_menu' bin.
    $settings['cache_prefix']['cache_menu'] = 'menumysite_';

If no prefix is set explicitly set, it will fall back to the behavior that is
used for the APCU prefix, which is reasonably safe but quite long. Setting a
explicit prefix is recommended.

### Expiration of cache items

Per default the TTL for permanent items will set to one year. This allows
to make a distinction between temporary cache entries and information that may
need to be more permanently.

See [Eviction policy](#eviction-policy) for more information.

By default, the default TTL will always be used over the expiration set for the
item. The specific expiration is instead stored and verified when reading
the cache item.

This is done to respect the expectation that expired cache items can still be
returned when explicitly requested.

It is possible to set a TTL offset as a compromise between supporting the
ability to return expired items for a certain amount of time but also guide
Redis to clear item items that have been expired.

Using this setting, Redis will get the real TTL for a key and
might evict keys by TTL according to its configuration.

    // Expired items can still be explicitly requested for up to
    // one hour.
    $settings['redis_ttl_offset'] = 3600;

    // It is also possible to set the offset to 0, which disables the ability
    // to fetch expired items. This is not recommended.
    $settings['redis_ttl_offset'] = 0;

Note: This behavior is off by default for BC, a default offset might be set in a
future release.

The default TTL can be customized for specific bins.

    // Set max TTL for cached pages to 3 days.
    $settings['redis_perm_ttl_page'] = '3 days';

    // But you can also put a timestamp in there; In this case the
    // value must be a typed integer:
    $settings['redis_perm_ttl_page'] = 2592000; // 30 days.

Time interval string will be parsed using DateInterval::createFromDateString
please refer to its documentation:

    http://www.php.net/manual/en/dateinterval.createfromdatestring.php

Setting a lower TTL may allow Redis to free up old cache entries. Note however
that Drupal will by default use most cache entries indefinitely and setting TTL
too low may negatively affect performance and should be tested carefully. It is
recommended to only consider this for large cache bins such as page, dynamic_page_cache and render.
And instead rely on setting redis_ttl_offset and relying on an appropriate
[eviction policy](#eviction-policy). Using volatile-lfu or similar will likely
result in Redis making better informed decisions on which items to remove than
a very short TTL.

### Cache optimizations

These settings allow to further optimize caching but are not be fully compatible
with the expected behavior of cache backends or have other tradeoffs.

Treat invalidateAll() the same as deleteAll() to avoid two different checks for
each bin.

    $settings['redis_invalidate_all_as_delete'] = TRUE;

Core has deprecated invalidateAll() in
https://www.drupal.org/project/drupal/issues/3498947. This setting will be
removed in the future when Drupal 12.0 is required.

### Using igbinary serialization

By default, Redis uses `serialize()/unserialize()`. The igbinary project provides
a considerable optimization in terms of unserialization speed and size of serialized objects.

To use, first require https://www.drupal.org/project/igbinary with composer.

It is possible to use the serialization API with enabling the module by
requiring the necessary services directly.

    // Directly load the igbinary services without enabling the module.
    $settings['container_yamls'][] = 'modules/contrib/igbinary/igbinary.services.yml';

Then redefine the cache.backend.redis service to use igbinary, by placing the
following in a project specific services.yml, such as sites/default/services.yml.

    # Override the default redis cache backend to use igbinary serialization.
    cache.backend.redis:
      class: Drupal\redis\Cache\CacheBackendFactory
      arguments: [ '@redis.factory', '@cache_tags.invalidator.checksum', '@serialization.igbinary' ]

Finally, adjust the bootstrap_container_definition definition:

    $settings['bootstrap_container_definition'] = [
      'parameters' => [],
      'services' => [
        'redis.factory' => [
          'class' => 'Drupal\redis\ClientFactory',
        ],
        'cache.backend.redis' => [
          'class' => 'Drupal\redis\Cache\CacheBackendFactory',
          'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.igbinary'],
        ],
        'cache.container' => [
          'class' => '\Drupal\redis\Cache\PhpRedis',
          'factory' => ['@cache.backend.redis', 'get'],
          'arguments' => ['container'],
        ],
        'cache_tags_provider.container' => [
          'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
          'arguments' => ['@redis.factory'],
        ],
        'serialization.igbinary' => [
          'class' => 'Drupal\igbinary\Component\Serialization\IgbinarySerialize',
        ],
      ],
    ];

## Redis management

This chapter covers some configuration and optimizations for Redis itself. See
the Redis documentation as well as the [Additional links](#additional-information). section for more detailed explanations.

### Redis commands

You can administer Redis via the Redis CLI, using some of the more than 450 Redis
commands. Here are two examples.

You can check Redis keys by opening the Redis CLI with `redis-cli`, typing
`KEYS *`, and pressing "Enter" (you should see many lines):

    $ redis-cli
    redis:6379> KEYS *
        1) "drupal.redis.11.1.2..9d1624a8107bff73ae7af14c80a44c996 [...]

You can verify if Redis is active by issuing the
[MONITOR command](https://youtu.be/9-7JmKD5jtk?feature=shared&t=643) in the
Redis CLI tool. Browse the site, and you should see a lot of queries. Exit by
pressing `Ctrl+C`:

    $ redis-cli
    redis:6379> MONITOR
    OK
    1761562527.992890 [0 172.19.0.2:47002] "HGETALL" "drupal.redis.11.1.2 [...]

For all Redis commands, see https://redis.io/docs/latest/commands/.

### Eviction policy

Redis is typically configured with a max memory size that it is allowed to use.

What happens when that is full depends on the eviction policy. By default, Redis
will no longer accept new items. which is almost never the desired behavior when
using Redis as a Drupal cache backend, as caches tend to grow continuously. Setting
a different eviction policy is strongly recommended.

The other policies are split in two groups and affect either all keys or only
volatile ones, which are defined by having an expiration/TTL set.

The redis cache backend will always set an expiration on all cache items, except for
cache taq invalidations as well as queue and other similar
backend implementations. It is therefore safe and recommended
to use a volatile policy to avoid evicting cache tag invalidations or queue
items.

A good policy for typical scenarios is volatile-lfu, but sites may want to do
their own testing and research on this. Sites with a large amount of items with
shorter TTL or optimized permanent TTLs for certain bins may benefit from using
volatile-ttl, but only if those items aren't also very frequently used.

It is recommended to store data that must be persisted like queue items in a
separate redis instance.

See https://valkey.io/topics/lru-cache/ for a detailed explanation.

A typical set-up for a server with 16GB RAM, using maximum ~80% of memory,
evicting least recently used keys:

    maxmemory 12500mb
    maxmemory-policy volatile-lfu

### Emptying Redis storage manually

The Redis storage is not emptied when running `drush cache:rebuild`. The method for removing cached items from the Redis is expiring keys after 1 year, effectively not removing them from the Redis storage. You can lower this, see [Expiration of cache items](#expiration-of-cache-items). Instead, items in all cache bins are flagged as invalidated and inaccessible to Drupal.

You can free up Redis memory with either the `redis-cli flushall` command, or use Drush:

    drush php:eval "\Drupal::service('redis.factory')->getClient()->flushAll()"

See [Drupal cache clear also clearing Redis](https://www.drupal.org/project/redis/issues/3398797) and [Currently Drush Cr or Cache Clear UI does not flush Redis cache](https://www.drupal.org/project/redis/issues/2765895).

### Redis RDB snapshots

When using Redis solely as a cache, it is recommended to disable RDB snapshots. This prevents Redis from saving all cache entries
to the disk on regular intervals.

This means that Redis storage will be empty after a restart and may lead
to data loss when using Redis to store persistent data such as queue items.

#### Disable RDB snapshots

By default, RDB snapshots are enabled, but on a cache-only Redis, they should be disabled.

To disable RDB snapshots, append `save ""` last in the Redis config file `/etc/redis/redis.conf` and restart with `sudo systemctl restart redis`.

See https://www.bestonlinetutorial.com/redis/misconf-redis-is-configured-to-save-rdb-snapshots-redis.html and [Redis persistence](https://redis.io/docs/latest/operate/oss_and_stack/management/persistence/) for more.

#### Remove RDB snapshot files

If the `/var/lib/redis/` folder is getting filled up with Redis snapshot RDB-files á la `temp-71579.rdb`, you can delete them with this:

    rm /var/lib/redis/temp-*

## Additional backends

### Lock Backend

See the provided example.services.yml file on how to override the lock services.

### Queue Backend

This module provides reliable and non-reliable queue implementations. Depending
on which is to be use you need to choose "queue.redis" or "queue.redis_reliable"
as a service name.

When you have configured basic information (host, library, ... - see Quick setup)
add this to your settings.php file:

    # Use for all queues unless otherwise specified for a specific queue.
    $settings['queue_default'] = 'queue.redis';

    # Or if you want to use reliable queue implementation.
    $settings['queue_default'] = 'queue.redis_reliable';

    # Use this to only use Redis for a specific queue (aggregator_feeds in this case).
    $settings['queue_service_aggregator_feeds'] = 'queue.redis';

    # Or if you want to use reliable queue implementation.
    $settings['queue_service_aggregator_feeds'] = 'queue.redis_reliable';

## Testing

The tests respect the following two environment variables to customize the redis
host and used interface.

  * REDIS_HOST
  * REDIS_INTERFACE

These can for example be configured through phpunit.xml

    <env name="REDIS_HOST" value="redis"/>
    <env name="REDIS_INTERFACE" value="Relay"/>

## Additional information

Sources and extra information about installation and configuration,
for example Redis performance tuning.

- [Elevating Drupal's Capabilities with Redis for Advanced Data Management](https://cyberschorsch.dev/drupal/elevating-drupals-capabilities-redis-advanced-data-management)
- [Redis Server and Php Extension Installation on Ubuntu 22.04 LTS](https://blog.oloma.dev/redis-server-and-php-extension-installation-on-ubuntu-22-04-lts-for-olobase-8c88dacb23ec)
- [How to speed up Drupal with the right caches: OPcache, APCu, and a shared backend (Redis or Memcached)](https://davidloor.com/en/blog/drupal-caching-opcache-apcu-redis-memcached-guide)
- [Using Redis with Drupal on Upsun](https://fixed.docs.upsun.com/guides/drupal/redis.html)
