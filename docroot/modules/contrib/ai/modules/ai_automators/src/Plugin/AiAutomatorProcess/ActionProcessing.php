<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorProcess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\Attribute\AiAutomatorProcessRule;
use Drupal\ai_automators\Exceptions\AiAutomatorRequestErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorRuleNotFoundException;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorDirectProcessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action processor for making automators available as action plugins.
 *
 * This worker type is skipped during normal entity saves and is only executed
 * explicitly via the RunAutomatorAction plugin, e.g. through Views bulk
 * operations.
 */
#[AiAutomatorProcessRule(
  id: 'action',
  title: new TranslatableMarkup('Action'),
  description: new TranslatableMarkup('Makes the automator available as an action, e.g. for use with Views bulk operations. Does not run during entity save.'),
)]
class ActionProcessing implements AiAutomatorDirectProcessInterface, ContainerFactoryPluginInterface {

  /**
   * Constructor.
   */
  final public function __construct(
    protected AiAutomatorRuleRunner $aiRunner,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected MessengerInterface $messenger,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai_automator.rule_runner'),
      $container->get('logger.factory'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    try {
      return $this->aiRunner->generateResponse($entity, $fieldDefinition, $automatorConfig);
    }
    catch (AiAutomatorRuleNotFoundException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A rule was not found, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiAutomatorRequestErrorException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A request error happened, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiAutomatorResponseErrorException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A response was not correct, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_automator')->warning('A general error happened while trying to interpolate, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    $this->messenger->addWarning($e->getMessage());
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(EntityInterface $entity) {
    // No preprocessing needed for actions.
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {
    // No postprocessing needed for actions.
  }

  /**
   * {@inheritDoc}
   */
  public function processorIsAllowed(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function shouldProcessDirectly(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig): bool {
    // Never process during entity save — only via explicit action execution.
    return FALSE;
  }

}
