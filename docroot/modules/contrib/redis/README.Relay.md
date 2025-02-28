Relay cache backend
======================

Integration with the PHP extension Relay, an alternative to PhpRedis.

Note: Version 0.6 or newer is required of the Relay extension.

Get Relay
------------

You can download this library at:

https://relay.so/docs/installation

It is recommended to set the eviction policy to lru in php.ini

    relay.eviction_policy = lru

Use the Relay interface (this happens by if PhpRedis and Predis are not available).

    $settings['redis.connection']['interface'] = 'Relay';

In-Memory configuration
-------------

Relay includes an in-memory cache that is similar to the ChainedFastBackend that is used in Drupal by default, but far
more efficient. When using Relay, it is recommended to explicitly set the cache backend to redis for the cache bins that
by default use ChainedFastBackend:

    $settings['cache']['default'] = 'cache.backend.redis';
    $settings['cache']['bins']['config'] = 'cache.backend.redis';
    $settings['cache']['bins']['discovery'] = 'cache.backend.redis';
    $settings['cache']['bins']['bootstrap'] = 'cache.backend.redis';

Whether the in-memory cache is used can be configured per bin, the default configuration is to use it for the config,
discovery and boostrap (those that use ChainedFastBackend by default) and container:

    $settings['redis_relay_memory_bins'] = ['container', 'bootstrap', 'config', 'discovery'];

The default is only used when no configuration is set, if this is customized all bins that should use the in-memory
cache must be set explicitly.

Use the Sentinel high availability mode
---------------------------------------

Redis can provide a master/slave mode with sentinels server monitoring them.
More information about setting it : https://redis.io/topics/sentinel.

This mode needs the following settings:

Modify the host as follow:
    // Sentinels instances list with hostname:port format.
    $settings['redis.connection']['host']      = ['1.2.3.4:5000','1.2.3.5:5000','1.2.3.6:5000'];

Add the new instance setting:

    // Redis instance name.
    $settings['redis.connection']['instance']  = 'instance_name';

Connect to a remote host and database
-------------------------------------

See README.md file.

For this particular implementation, host settings are overridden by the
UNIX socket parameter.
