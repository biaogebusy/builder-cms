<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\entity_share_server\Event\ChannelListEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller to generate list of channels URLs.
 */
class EntryPoint extends ControllerBase {

  /**
   * The channel manipulator.
   *
   * @var \Drupal\entity_share_server\Service\ChannelManipulatorInterface
   */
  protected $channelManipulator;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->channelManipulator = $container->get('entity_share_server.channel_manipulator');
    $instance->resourceTypeRepository = $container->get('jsonapi.resource_type.repository');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * Controller to list all the resources.
   */
  public function index() {
    $self = Url::fromRoute('entity_share_server.resource_list')
      ->setOption('absolute', TRUE)
      ->toString();
    $urls = [
      'self' => $self,
    ];
    $data = [
      'channels' => [],
      'field_mappings' => $this->getFieldMappings(),
    ];
    $languages = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface[] $channels */
    $channels = $this->entityTypeManager()
      ->getStorage('channel')
      ->loadMultiple();

    foreach ($channels as $channel) {
      if (!$this->channelManipulator->userAccessChannel($channel, $this->currentUser())) {
        continue;
      }

      $channel_entity_type = $channel->get('channel_entity_type');
      $channel_bundle = $channel->get('channel_bundle');
      $channel_langcode = $channel->get('channel_langcode');
      $route_name = sprintf('jsonapi.%s--%s.collection', $channel_entity_type, $channel_bundle);
      $url = Url::fromRoute($route_name)
        ->setOption('language', $languages[$channel_langcode])
        ->setOption('absolute', TRUE)
        ->setOption('query', $this->channelManipulator->getQuery($channel));

      // Prepare an URL to get only the UUIDs.
      $url_uuid = clone($url);
      $query = $url_uuid->getOption('query');
      $query = (!is_null($query)) ? $query : [];
      $url_uuid->setOption('query',
        $query + [
          'fields' => [
            $channel_entity_type . '--' . $channel_bundle => 'changed',
          ],
        ]
      );

      $data['channels'][$channel->id()] = [
        'label' => $channel->label(),
        'url' => $url->toString(),
        'url_uuid' => $url_uuid->toString(),
        'channel_entity_type' => $channel_entity_type,
        'channel_bundle' => $channel_bundle,
        'search_configuration' => $this->channelManipulator->getSearchConfiguration($channel),
        'channel_maxsize' => $channel->get('channel_maxsize'),
      ];
    }

    // Collect other channel definitions.
    $event = new ChannelListEvent($data);
    $this->eventDispatcher->dispatch($event, ChannelListEvent::EVENT_NAME);

    return new JsonResponse([
      'data' => $event->getChannelList(),
      'links' => $urls,
    ]);
  }

  /**
   * Get all field mappings so clients are aware of the server configuration.
   *
   * [
   *   'entity_type_id' => [
   *     'bundle' => [
   *       'internal name' => 'public name',
   *     ],
   *   ],
   * ];
   *
   * @return array
   *   An array as explained in the text above.
   */
  protected function getFieldMappings() {
    $mapping = [];
    $definitions = $this->entityTypeManager()->getDefinitions();
    $resource_types = $this->resourceTypeRepository->all();

    foreach ($resource_types as $resource_type) {
      $entity_type_id = $resource_type->getEntityTypeId();

      // Do not expose config entities and user, as we do not manage them.
      if ($entity_type_id == 'user' || $definitions[$entity_type_id]->getGroup() != 'content') {
        continue;
      }

      $bundle = $resource_type->getBundle();
      $resource_type_fields = $resource_type->getFields();
      foreach ($resource_type_fields as $resource_type_field) {
        $mapping[$entity_type_id][$bundle][$resource_type_field->getInternalName()] = $resource_type_field->getPublicName();
      }
    }
    return $mapping;
  }

}
