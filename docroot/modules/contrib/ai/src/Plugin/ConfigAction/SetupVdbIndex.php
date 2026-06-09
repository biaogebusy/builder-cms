<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets up a vdb index.
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setupVdbIndex',
  admin_label: new TranslatableMarkup('Setup a VDB Index'),
  entity_types: ['search_api.index.*'],
)]
final class SetupVdbIndex implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // Basic validation that its a vdb index.
    assert(isset($value['id']));
    assert(isset($value['name']));
    assert(isset($value['field_settings']));
    assert(isset($value['server']));

    // Save the configuration.
    try {
      $this->entityTypeManager->getStorage('search_api_index')
        ->create($value)
        ->save();
    }
    catch (\Exception $e) {
      throw new \Exception('Could not save the search index.');
    }
  }

}
