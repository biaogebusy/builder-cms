<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorProcess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\ai_automators\Attribute\AiAutomatorProcessRule;
use Drupal\ai_automators\Exceptions\AiAutomatorRequestErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorRuleNotFoundException;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The direct processor.
 */
#[AiAutomatorProcessRule(
  id: 'direct',
  title: new TranslatableMarkup('Direct'),
  description: new TranslatableMarkup('Processes and saves the value directly.'),
)]
class DirectSaveProcessing implements AiAutomatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * Direct Saving.
   */
  protected AiAutomatorRuleRunner $aiRunner;

  /**
   * The Drupal logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The Drupal messenger service.
   */
  protected MessengerInterface $messenger;

  /**
   * Constructor.
   */
  final public function __construct(AiAutomatorRuleRunner $aiRunner, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger) {
    $this->aiRunner = $aiRunner;
    $this->loggerFactory = $logger;
    $this->messenger = $messenger;
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
      $this->loggerFactory->get('ai_automator')->warning('A general error happened why trying to interpolate, message %message', [
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
    $entity->ai_automator_status = AiAutomatorStatusField::STATUS_PROCESSING;
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {
    $entity->ai_automator_status = AiAutomatorStatusField::STATUS_FINISHED;
  }

  /**
   * {@inheritDoc}
   */
  public function processorIsAllowed(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

}
