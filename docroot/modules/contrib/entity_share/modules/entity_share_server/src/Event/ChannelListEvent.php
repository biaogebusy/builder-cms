<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allows to alter the list of channels provided by the website.
 *
 * @package Drupal\entity_share_server\Event
 */
class ChannelListEvent extends Event {

  const EVENT_NAME = 'entity_share_server.channel_list';

  /**
   * List of channels provided by entity share server.
   *
   * @var array
   */
  protected $channelList;

  /**
   * ChannelListEvent constructor.
   *
   * @param array $channelList
   *   The channel list.
   */
  public function __construct(array $channelList) {
    $this->channelList = $channelList;
  }

  /**
   * Get all channels.
   *
   * @return array
   *   The channel list.
   */
  public function getChannelList() {
    return $this->channelList;
  }

  /**
   * Set new channel list.
   *
   * @param array $channelList
   *   The channel list.
   *
   * @return $this
   */
  public function setChannelList(array $channelList) {
    $this->channelList = $channelList;
    return $this;
  }

  /**
   * Add new channel.
   *
   * @param string $channel_name
   *   Channel name.
   * @param array $channel_definition
   *   Channel definition.
   */
  public function addChannel($channel_name, array $channel_definition) {
    if (!isset($this->channelList['channels'])) {
      $this->channelList['channels'] = [];
    }
    if (!isset($this->channelList['channels'][$channel_name])) {
      $this->channelList['channels'][$channel_name] = $channel_definition;
    }
  }

}
