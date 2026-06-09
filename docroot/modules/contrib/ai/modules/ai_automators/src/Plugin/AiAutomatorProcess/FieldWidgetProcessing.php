<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorProcess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
 * The field widget processor.
 */
#[AiAutomatorProcessRule(
  id: 'field_widget_actions',
  title: new TranslatableMarkup('Field Widget'),
  description: new TranslatableMarkup('Processes the widget when the user takes action. This will not save the entity, only add values to the form.'),
)]
class FieldWidgetProcessing implements AiAutomatorDirectProcessInterface, ContainerFactoryPluginInterface {

  /**
   * Constructor.
   */
  final public function __construct(
    protected AiAutomatorRuleRunner $aiRunner,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected MessengerInterface $messenger,
    protected ModuleHandlerInterface $moduleHandler,
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
      $container->get('module_handler')
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
    // We do not need to do anything since we are not saving the entity.
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {
    // We do not need to do anything since we are not saving the entity.
  }

  /**
   * {@inheritDoc}
   */
  public function processorIsAllowed(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Only is available if the Form Widget Actions module is enabled.
    return $this->moduleHandler->moduleExists('field_widget_actions');
  }

  /**
   * {@inheritDoc}
   */
  public function shouldProcessDirectly(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig): bool {
    // This processor always processes directly.
    return TRUE;
  }

}
