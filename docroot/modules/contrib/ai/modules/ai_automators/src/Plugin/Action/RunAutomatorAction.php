<?php

namespace Drupal\ai_automators\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\AiFieldRules;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs an AI automator on an entity as an action plugin.
 */
#[Action(
  id: 'ai_automators_run',
  action_label: new TranslatableMarkup('Run AI Automator'),
  deriver: RunAutomatorActionDeriver::class,
)]
class RunAutomatorAction extends EntityActionBase {

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    protected AiAutomatorRuleRunner $ruleRunner,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AiFieldRules $fieldRules,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('ai_automator.rule_runner'),
      $container->get('logger.factory'),
      $container->get('entity_field.manager'),
      $container->get('ai_automator.field_rules'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity) {
      throw new \RuntimeException('RunAutomatorAction::execute() called without an entity.');
    }

    $automator_id = $this->getDerivativeId();
    /** @var \Drupal\ai_automators\AiAutomatorInterface|null $automator */
    $automator = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load($automator_id);

    if (!$automator) {
      throw new \RuntimeException(sprintf('Automator config "%s" not found for action execution.', $automator_id));
    }

    // Build automator config from plugin_config (strip automator_ prefix).
    $automatorConfig = [
      'field_name' => $automator->get('field_name'),
    ];
    foreach ($automator->get('plugin_config') as $key => $setting) {
      $automatorConfig[substr($key, 10)] = $setting;
    }

    // Get field definition.
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions(
      $automator->get('entity_type'),
      $automator->get('bundle')
    );
    $fieldName = $automator->get('field_name');
    if (!isset($fieldDefinitions[$fieldName])) {
      throw new \RuntimeException(sprintf('Field definition not found for automator "%s".', $automator_id));
    }
    $fieldDefinition = $fieldDefinitions[$fieldName];

    // Check if the field already has a value (respect edit_mode setting).
    $value = $entity->get($fieldName)->getValue();

    // Find the automator rule to check emptiness.
    $rule = $this->fieldRules->findRule($automatorConfig['rule']);
    if ($rule) {
      $value = $rule->checkIfEmpty($value, $automatorConfig);
    }

    if (!empty($value) && !empty($value[0]) && empty($automatorConfig['edit_mode'])) {
      // Field has a value and edit_mode is off — skip.
      $this->messenger()->addStatus($this->t('Skipped %label: field already has a value.', [
        '%label' => $entity->label(),
      ]));
      return;
    }

    try {
      $this->ruleRunner->generateResponse($entity, $fieldDefinition, $automatorConfig);
      // Save the entity with automator hook disabled to prevent recursion.
      ai_automators_entity_can_save_toggle(FALSE);
      try {
        $entity->save();
      }
      finally {
        ai_automators_entity_can_save_toggle(TRUE);
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_automator')->warning('Action failed for entity %id: %message', [
        '%id' => $entity->id(),
        '%message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Deny access for entities that don't match the automator's bundle.
    /** @var \Drupal\ai_automators\AiAutomatorInterface|null $automator */
    $automator = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->load($this->getDerivativeId());
    if ($automator && $object->bundle() !== $automator->get('bundle')) {
      $result = AccessResult::forbidden('Entity bundle does not match automator target.')
        ->addCacheableDependency($automator);
      return $return_as_object ? $result : $result->isAllowed();
    }
    $access = $object->access('update', $account, TRUE);
    if ($automator) {
      $access->addCacheableDependency($automator);
    }
    return $return_as_object ? $access : $access->isAllowed();
  }

}
