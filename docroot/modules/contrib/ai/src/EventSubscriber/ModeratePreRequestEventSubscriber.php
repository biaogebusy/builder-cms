<?php

namespace Drupal\ai\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event that is triggered after a response is generated.
 *
 * @package Drupal\ai\EventSubscriber
 */
class ModeratePreRequestEventSubscriber implements EventSubscriberInterface {

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructor.
   */
  public function __construct(AiProviderPluginManager $ai, ConfigFactoryInterface $config_factory) {
    $this->aiProvider = $ai;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The pre generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'moderatePreRequest',
    ];
  }

  /**
   * Check if we should stop a request due to OpenAI moderation.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event to log.
   *
   * @throws \Drupal\ai\Exception\AiUnsafePromptException
   */
  public function moderatePreRequest(PreGenerateResponseEvent $event): void {
    // Check the config if we should moderate the provider and type.
    $config = $this->getConfig()->get('moderations') ?? [];
    $configs = $this->matchConfigs($config, $event);

    foreach ($configs as $config) {
      if (!isset($config['models'])) {
        continue;
      }
      foreach ($config['models'] as $model) {
        [$provider_id, $model_id] = explode('__', $model);
        try {
          $provider = $this->aiProvider->createInstance($provider_id);
        }
        catch (\Exception $e) {
          throw new AiUnsafePromptException($provider_id . ' moderation is wanted on a request of type ' . $event->getOperationType() . ' for the provider ' . $event->getProviderId() . ', but it is not installed.');
        }

        // Get the input and json_encode it since it might be complex.
        $input = '';
        if ($event->getInput() instanceof InputInterface) {
          $input = $event->getInput()->toString();
        }
        else {
          // If its raw data, lets json encode it into a string.
          $input = json_encode($event->getInput());
        }

        // Test it against the provider and fail if its not safe.
        if ($provider->moderation($input, $model_id)->getNormalized()->isFlagged()) {
          throw new AiUnsafePromptException($provider_id . ' moderation endpoint flagged and stopped this prompt.');
        }
      }
    }
  }

  /**
   * Match providers.
   *
   * @param array $configs
   *   All the configs.
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event.
   *
   * @return array
   *   The configs left.
   */
  protected function matchConfigs(array $configs, PreGenerateResponseEvent $event): array {
    $new_configs = [];
    foreach ($configs as $key => $config) {
      if ($event->getProviderId() !== $config['provider']) {
        continue;
      }
      // Cleanup the tags, explode on comma and trim, remove empty.
      $tags = array_filter(array_map('trim', explode(',', $config['tags'])));

      // If it has tags and they don't match, skip.
      if (count($tags) && !array_intersect($event->getTags(), $tags)) {
        continue;
      }
      $new_configs[$key] = $config;
    }

    return $new_configs;
  }

  /**
   * Get the config.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config.
   */
  protected function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai.external_moderation');
  }

}
