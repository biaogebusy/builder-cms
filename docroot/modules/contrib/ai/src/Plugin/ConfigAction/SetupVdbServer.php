<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets up a vdb server, using default embeddings model.
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setupVdbServerWithDefaults',
  admin_label: new TranslatableMarkup('Setup an VDB Server'),
  entity_types: ['search_api.server.*'],
)]
final class SetupVdbServer implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigActionPluginInterface $simpleConfigUpdate,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AiProviderPluginManager $aiProviderPluginManager,
    private readonly AiVdbProviderPluginManager $aiVdbProviderPluginManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('plugin.manager.config_action')->createInstance('simpleConfigUpdate'),
      $container->get(EntityTypeManagerInterface::class),
      $container->get('ai.provider'),
      $container->get('ai.vdb_provider'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // Basic validation that it's a vdb server.
    assert(isset($value['id']));
    assert(isset($value['name']));
    assert(isset($value['backend_config']['embedding_strategy']));
    assert(isset($value['backend_config']['embedding_strategy_configuration']));

    // Handle default database setting if not provided.
    if (!isset($value['backend_config']['database'])) {
      $config = $this->configFactory->get('ai.settings');
      $default_vdb_provider = $config->get('default_vdb_provider');
      if (empty($default_vdb_provider)) {
        throw new \Exception('No default VDB provider is set and database backend is not specified.');
      }
      $value['backend_config']['database'] = $default_vdb_provider;
    }

    // Handle default database_name setting if not provided.
    if (!isset($value['backend_config']['database_settings'])) {
      $value['backend_config']['database_settings'] = [];
    }
    if (!isset($value['backend_config']['database_settings']['database_name'])) {
      try {
        $vdb_provider = $this->aiVdbProviderPluginManager->createInstance($value['backend_config']['database']);
        $value['backend_config']['database_settings']['database_name'] = $vdb_provider->getDefaultDatabase();
      }
      catch (\Exception $e) {
        throw new \Exception('Could not get default database name from VDB provider: ' . $e->getMessage());
      }
    }
    // Make sure a default embeddings model is set and can be loaded.
    $defaults = $this->aiProviderPluginManager->getDefaultProviderForOperationType('embeddings');
    if (empty($defaults)) {
      throw new \Exception('No default embeddings model is set.');
    }
    try {
      $provider = $this->aiProviderPluginManager->createInstance($defaults['provider_id']);
    }
    catch (\Exception $e) {
      throw new \Exception('The default embeddings model is not supported.');
    }

    // Load the provider.
    $vector_size = NULL;
    try {
      $provider = $this->aiProviderPluginManager->createInstance($defaults['provider_id']);
      $vector_size = $provider->embeddingsVectorSize($defaults['model_id']);
    }
    catch (\Exception $e) {
      throw new \Exception('The provider ' . $defaults['provider'] . ' is not supported.');
    }
    // Set the embeddings engine & configuration.
    $value['backend_config']['embeddings_engine'] = $defaults['provider_id'] . '__' . $defaults['model_id'];
    $value['backend_config']['embeddings_engine_configuration']['dimensions'] = $vector_size;
    $value['backend_config']['embeddings_engine_configuration']['set_dimensions'] = FALSE;
    // Save the configuration.
    try {
      $this->entityTypeManager->getStorage('search_api_server')
        ->create($value)
        ->save();
    }
    catch (\Exception $e) {
      throw new \Exception('Could not save the configuration.');
    }
  }

}
