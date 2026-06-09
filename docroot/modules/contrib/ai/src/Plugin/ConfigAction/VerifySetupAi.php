<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This verifies that the wanted AI provider is set up.
 *
 * Note that this is a workaround for how recipes are currently set up. This
 * action does not actually modify any configuration, but rather verifies that
 * an AI provider exists or that a default operation type model is set.
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'verifySetupAi',
  admin_label: new TranslatableMarkup('Verify that the wanted AI provider is set up'),
  entity_types: ['*'],
)]
final class VerifySetupAi implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly AiProviderPluginManager $aiProviderPluginManager,
    private readonly AiVdbProviderPluginManager $aiVdbProviderPluginManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.vdb_provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // Assert array.
    assert(is_array($value));
    // Make sure something is tested.
    $tested = FALSE;

    // Check if the user wants to look for a setup provider.
    if (!empty($value['provider_is_setup'])) {
      $tested = TRUE;
      // The value should be a numeric array.
      assert(is_array($value['provider_is_setup']) && array_is_list($value['provider_is_setup']));
      // Iterate through all the providers.
      foreach ($value['provider_is_setup'] as $provider_id) {
        // Check if the provider exists.
        if (!$this->aiProviderPluginManager->hasDefinition($provider_id)) {
          throw new \InvalidArgumentException("The AI provider '$provider_id' does not exist, so this recipe will not work.");
        }
        // Check if the provider is set up.
        $provider = $this->aiProviderPluginManager->createInstance($provider_id);
        if (!$provider->isUsable()) {
          throw new \InvalidArgumentException("The AI provider '$provider_id' is not set up, so this recipe will not work.");
        }
      }
    }

    // Check for a provider for an operation type.
    if (!empty($value['operation_type_has_provider'])) {
      $tested = TRUE;
      // The value should be a numeric array.
      assert(is_array($value['operation_type_has_provider']) && array_is_list($value['operation_type_has_provider']));
      // Iterate through all the operation types.
      foreach ($value['operation_type_has_provider'] as $operation_type) {
        // Check if the operation type has a provider.
        if (!$this->aiProviderPluginManager->hasProvidersForOperationType($operation_type)) {
          throw new \InvalidArgumentException("The operation type '$operation_type' does not have a provider, so this recipe will not work.");
        }
      }
    }

    // Check if a default operation type model is set.
    if (!empty($value['operation_type_has_default_model'])) {
      $tested = TRUE;
      // The value should be a numeric array.
      assert(is_array($value['operation_type_has_default_model']) && array_is_list($value['operation_type_has_default_model']));
      // Iterate through all the operation types.
      foreach ($value['operation_type_has_default_model'] as $operation_type) {
        // Check if the operation type has a default model.
        if (!$this->aiProviderPluginManager->getDefaultProviderForOperationType($operation_type)) {
          throw new \InvalidArgumentException("The operation type '$operation_type' does not have a default model, so this recipe will not work.");
        }
      }
    }

    // Check if a VDB provider is set up.
    if (!empty($value['vdb_provider_is_setup'])) {
      $tested = TRUE;
      // The value should be a numeric array.
      assert(is_array($value['vdb_provider_is_setup']) && array_is_list($value['vdb_provider_is_setup']));
      // Iterate through all the VDB providers.
      foreach ($value['vdb_provider_is_setup'] as $vdb_provider_id) {
        // Check if the VDB provider exists.
        if (!$this->aiVdbProviderPluginManager->hasDefinition($vdb_provider_id)) {
          throw new \InvalidArgumentException("The VDB provider '$vdb_provider_id' does not exist, so this recipe will not work.");
        }
        // Check if the VDB provider is set up.
        $vdb_provider = $this->aiVdbProviderPluginManager->createInstance($vdb_provider_id);
        if (!$vdb_provider->isSetup()) {
          throw new \InvalidArgumentException("The VDB provider '$vdb_provider_id' is not set up, so this recipe will not work.");
        }
      }
    }

    // If nothing was tested, throw an error.
    if (!$tested) {
      throw new \InvalidArgumentException('No AI provider or operation type was tested, so this recipe will not work.');
    }
  }

}
