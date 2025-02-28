<?php

namespace Drupal\core_context;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\ctools\ContextMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes contexts stored in an entity's third-party settings.
 */
final class SettingsContextHandler implements EntityContextHandlerInterface {

  use CacheableContextTrait;

  /**
   * The context mapper service.
   *
   * @var \Drupal\ctools\ContextMapperInterface
   */
  private $contextMapper;

  /**
   * SettingsContextHandler constructor.
   *
   * @param \Drupal\ctools\ContextMapperInterface $context_mapper
   *   The context mapper service.
   */
  public function __construct(ContextMapperInterface $context_mapper) {
    $this->contextMapper = $context_mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('ctools.context_mapper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(EntityInterface $entity) {
    assert($entity instanceof ThirdPartySettingsInterface);

    $contexts = $entity->getThirdPartySetting('core_context', 'contexts', []);
    $contexts = $this->contextMapper->getContextValues($contexts);
    return $this->applyCaching($contexts, $entity);
  }

}
