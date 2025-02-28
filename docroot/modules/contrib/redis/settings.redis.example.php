<?php

use Drupal\Core\Installer\InstallerKernel;

// Either copy the content of this file into an existing settings.php file or
// create as e.g. settings.redis.php and include it, then customize. See
// README.md and client specific README files for more information.

// Adjust extension check or remove, prevents configuring Redis before
// Drupal is installed or on an environment where the used client is not
// available.
if (!InstallerKernel::installationAttempted() && extension_loaded('redis')) {

  // Customize host and port.
  // $settings['redis.connection']['host'] = '127.0.0.1';
  // $settings['redis.connection']['port'] = 6379;

  // Customize used interface.
  // $settings['redis.connection']['interface'] = 'PhpRedis';

  // Set Redis as the default backend for any cache bin not otherwise specified.
  $settings['cache']['default'] = 'cache.backend.redis';

  // Per-bin configuration examples, bypass the default ChainedFastBackend.
  // *Only* use this when using Relay (see README.Relay.md) or when APCu is not
  // available.
  // $settings['cache']['bins']['config'] = 'cache.backend.redis';
  // $settings['cache']['bins']['discovery'] = 'cache.backend.redis';
  // $settings['cache']['bins']['bootstrap'] = 'cache.backend.redis';

  // Use compression for cache entries longer than the specified limit.
  $settings['redis_compress_length'] = 100;

  // Customize the prefix, a reliable but long fallback is used if not defined.
  // $settings['cache_prefix'] = 'prefix';

  // Apply changes to the container configuration to better leverage Redis.
  // This includes using Redis for the lock and flood control systems, as well
  // as the cache tag checksum. Alternatively, copy the contents of that file
  // to your project-specific services.yml file, modify as appropriate, and
  // remove this line.
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';

  // Allow the services to work before the Redis module itself is enabled.
  $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';

  // Manually add the classloader path, this is required for the container cache
  // bin definition below and allows to use it without the redis module being
  // enabled.
  $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');

  // Use redis for container cache.
  // The container cache is used to load the container definition itself, and
  // thus any configuration stored in the container itself is not available
  // yet. These lines force the container cache to use Redis rather than the
  // default SQL cache.
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
}
