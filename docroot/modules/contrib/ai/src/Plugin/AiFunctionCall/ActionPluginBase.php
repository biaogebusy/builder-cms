<?php

namespace Drupal\ai\Plugin\AiFunctionCall;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Action\ActionPluginCollection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Plugin\AiFunctionCall\Derivative\ActionPluginDeriver;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the action plugin function.
 */
#[FunctionCall(
  id: 'action_plugin',
  function_name: 'action_plugin',
  name: new TranslatableMarkup('Action Plugin Wrapper'),
  description: '',
  deriver: ActionPluginDeriver::class
)]
class ActionPluginBase extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * Known non-entity action plugin IDs mapped to the permission they require.
   *
   * These core actions perform no meaningful access check of their own (their
   * access() returns AccessResult::allowed() unconditionally), so they are
   * gated on an explicit permission. Any other action not listed here falls
   * back to its own access() check.
   */
  protected const KNOWN_ACTION_PERMISSIONS = [
    'action_send_email_action' => 'administer actions',
    'action_message_action' => 'administer actions',
    'action_goto_action' => 'administer actions',
  ];

  /**
   * The action manager service.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected ActionManager $actionManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The action plugin.
   *
   * @var \Drupal\Core\Action\ActionPluginCollection
   */
  protected $pluginCollection;

  /**
   * The status of the action execution.
   *
   * @var string|null
   */
  protected $executionStatus;

  /**
   * The error message, if any.
   *
   * @var string|null
   */
  protected $errorMessage;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $instance->actionManager = $container->get('plugin.manager.action');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    try {
      $action_plugin = $this->getPluginCollection()->get($this->getDerivativeId());

      // Gate execution before building params or mutating anything, so a
      // denied action never runs.
      $access = $this->access($action_plugin);
      if (!$access->isAllowed()) {
        $this->executionStatus = 'access_denied';
        $reason = $access instanceof AccessResultReasonInterface ? $access->getReason() : '';
        $this->errorMessage = $reason ?: 'Access denied: you do not have permission to execute this action.';
        return;
      }

      if ($action_plugin instanceof ConfigurableInterface) {
        $params = [];
        $configuration = $action_plugin->getConfiguration();
        // If context keys exist, not in configuration, set to execute.
        foreach ($this->getContextValues() as $key => $value) {
          if (isset($configuration[$key])) {
            $configuration[$key] = $value;
          }
          else {
            $params[$key] = $value;
          }
        }
        $action_plugin->setConfiguration($configuration);
      }
      else {
        $params = $this->getContextValues();
      }
      $params = array_values($params);
      // @todo Count params? or find something better.
      $action_plugin->execute(...$params);

      // Set the execution status to success if no exception occurs.
      $this->executionStatus = 'success';
      $this->errorMessage = NULL;

    }
    catch (\Exception $e) {
      // Set the execution status to failed and store the error message.
      $this->executionStatus = 'failed';
      $this->errorMessage = $e->getMessage();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    // Get the action ID (derivative ID).
    $action_id = $this->getDerivativeId() ?? 'unknown';

    // Default to 'not executed' if the action hasn't run.
    $status = $this->executionStatus ?? 'not executed';
    $output = "Action '$action_id' status: $status";

    // Append the error message if the action failed or was denied.
    if (in_array($status, ['failed', 'access_denied'], TRUE) && !empty($this->errorMessage)) {
      $output .= "\nError: " . $this->errorMessage;
    }

    return $output;
  }

  /**
   * Checks whether the current user may execute the wrapped action.
   *
   * A small set of curated actions whose own access() check is permissive are
   * gated on an explicit permission (see KNOWN_ACTION_PERMISSIONS). Entity
   * actions are delegated to the action plugin's own access check (which
   * resolves to the contextual entity's access). Any other action simply runs
   * its own access() check, exactly as it would be checked anywhere else.
   *
   * @param \Drupal\Core\Action\ActionInterface $action_plugin
   *   The wrapped action plugin.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function access(ActionInterface $action_plugin): AccessResultInterface {
    // Curated actions with no meaningful access check of their own are gated
    // on an explicit permission.
    $id = $this->getDerivativeId();
    if (isset(static::KNOWN_ACTION_PERMISSIONS[$id])) {
      return AccessResult::allowedIfHasPermission($this->currentUser, static::KNOWN_ACTION_PERMISSIONS[$id]);
    }

    $type = $action_plugin->getPluginDefinition()['type'] ?? NULL;

    // Entity actions: 'entity' (generic) or a concrete entity type id. This
    // mirrors the classification used by the deriver.
    if ($type === 'entity' || ($type && $this->entityTypeManager->getDefinition($type, FALSE))) {
      $entity = $this->getEntityFromContext();
      if (!$entity instanceof EntityInterface) {
        return AccessResult::forbidden('Entity action invoked without an entity.');
      }
      return $action_plugin->access($entity, $this->currentUser, TRUE);
    }

    // Any other (unknown, non-entity) action: defer to its own access check,
    // exactly as Drupal would anywhere else.
    return $action_plugin->access(NULL, $this->currentUser, TRUE);
  }

  /**
   * Retrieves the contextual entity an entity action operates on.
   *
   * The derived definition exposes the entity context under the key 'entity'
   * (generic) or 'entity:<entity_type_id>' (typed).
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The contextual entity, or NULL if none is available.
   */
  protected function getEntityFromContext(): ?EntityInterface {
    foreach ($this->getContextValues() as $key => $value) {
      if (($key === 'entity' || str_starts_with($key, 'entity:')) && $value instanceof EntityInterface) {
        return $value;
      }
    }
    return NULL;
  }

  /**
   * Encapsulates the creation of the action's LazyPluginCollection.
   *
   * @return \Drupal\Component\Plugin\LazyPluginCollection
   *   The action's plugin collection.
   */
  protected function getPluginCollection() {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new ActionPluginCollection($this->actionManager, $this->getDerivativeId(), []);
    }
    return $this->pluginCollection;
  }

}
