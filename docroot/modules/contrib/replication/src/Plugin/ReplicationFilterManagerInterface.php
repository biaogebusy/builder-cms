<?php

namespace Drupal\replication\Plugin;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Manages ReplicationFilter plugin implementations.
 *
 * @see \Drupal\replication\Annotation\ReplicationFilter
 * @see \Drupal\replication\Plugin\ReplicationFilterInterface
 * @see \Drupal\replication\Plugin\ReplicationFilter\ReplicationFilterBase
 * @see plugin_api
 */
interface ReplicationFilterManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface {

}
