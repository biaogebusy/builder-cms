<?php

namespace Drupal\redis;

/**
 * Client adapter for specific implementations such as PhpRedis and Predis.
 *
 * All unknown method calls are passed to the underlying client library.
 *
 * Chained calls with multi() or pipeline() are not supported. Instead, client
 * implementations are expected to mirror the phpredis implementation of
 * pipeline, which enables the pipeline mode on the client and will execute the
 * pipeline when calling exec(). This also enables implementations that do not
 * pipelines, such as cluster, to make pipeline() a no-op.
 *
 * @method multi(): void
 * @method pipeline(): void
 * @method exec(): ?array
 *
 * @method get(string $key): string|mixed|false
 * @method set(string $key, mixed $value, mixed $options = null): bool
 * @method del(string $key, ... $keys): false|int
 * @method mget(array $keys): false|list<false|string>
 *
 * @method expire(string $key, int $ttl): bool
 *
 * @method hGetall(string $key): array
 * @method hMset(string $key, array $hash): bool
 * @method hGet(string $key, string $hashKey): string|mixed|false
 * @method hSet(string $key, string $hashKey, mixed $value): bool
 *
 * @method incr(string $key): int
 *
 * @method zAdd(string $key, array|float $score_or_options): false|int
 * @method zCount(string $key, string $start, string $end): false|int
 *
 * @method watch(string $key)
 * @method unwatch(string $key)
 * @method discard(string $key)
 */
interface ClientInterface {

  /**
   * Get underlying library name used.
   *
   * This can be useful for contribution code that may work with only some of
   * the provided clients.
   *
   * @return string
   */
  public function getName();

  /**
   * Pass all unknown calls to the underlying client implementation.
   *
   * @param string $name
   *   The method name.
   * @param array $arguments
   *   The method arguments.
   *
   * @return mixed
   *   The return value depends on the method.
   */
  public function __call(string $name, array $arguments);

  /**
   * Scan and process through all matching keys.
   *
   * @param string $match
   *   The match pattern.
   * @param int $count
   *   How many keys to return in one scan.
   *
   * @return \Generator
   */
  public function scan(string $match, int $count = 1000);

  /**
   * Returns various statistical information from Redis.
   *
   * @return array
   *   Redis info.
   */
  public function info(): array;


  public function addIgnorePattern(string $key): void;

}
