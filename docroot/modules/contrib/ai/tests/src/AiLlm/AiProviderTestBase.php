<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\AiLlm;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for AI tests that run against real providers.
 */
abstract class AiProviderTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'key',
  ];

  /**
   * The default models to target for the test.
   *
   * @var string[]
   */
  public static array $targetModels = [];

  /**
   * Get the target models, allowing env override via AI_PHPUNIT_TARGET_MODELS.
   *
   * @return string[]
   *   The models (provider__model).
   */
  final protected static function getModels(): array {
    if ($models = getenv('AI_PHPUNIT_TARGET_MODELS')) {
      return explode(',', $models);
    }
    return static::$targetModels;
  }

  /**
   * Get the provider.
   *
   * If there is no authentication, or the provider is not usable, the test will
   * be marked as skipped.
   *
   * @param string $provider_id
   *   The provider ID.
   * @param string $model
   *   The model being used.
   *
   * @return \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy
   *   The instantiated provider with authentication configured.
   *
   * @todo Allow providers to provide a test setup callback. See
   *   https://www.drupal.org/project/ai/issues/3494192.
   */
  final protected function getProvider(string $provider_id, string $model): ProviderProxy|AiProviderInterface {
    // Get any model and provider auth configuration.
    $model_auth = getenv('AI_PHPUNIT_AUTH_' . strtoupper($provider_id) . '_' . strtoupper($model));
    $provider_auth = getenv('AI_PHPUNIT_AUTH_' . strtoupper($provider_id));

    // Attempt to decode json, falling back to a simple _auth string. We merge
    // the provider and model auth, with model overriding provider.
    $auth = array_merge(
      json_decode($provider_auth ?: 'null', TRUE) ?:
        array_filter(['_key' => $provider_auth]),
      json_decode($model_auth ?: 'null', TRUE) ?:
        array_filter(['_key' => $model_auth]),
    );

    // If we have no auth, we have to skip the test.
    if (empty($auth)) {
      $this->markTestSkipped("Provider {$provider_id} has no authentication.");
    }

    // If no modules have been provider, make an educated guess.
    $auth['_modules'] ??= ['ai_provider_' . $provider_id];
    $this->enableModules($auth['_modules']);
    $manager = $this->container->get('ai.provider');

    // Instantiate the plugin and check it's usable.
    /** @var \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider */
    $provider = $manager->createInstance($provider_id);
    $provider->setAuthentication($auth['_key']);
    if (!$provider->isUsable()) {
      $this->markTestSkipped("Provider {$provider_id} is not usable.");
    }

    return $provider;
  }

}
