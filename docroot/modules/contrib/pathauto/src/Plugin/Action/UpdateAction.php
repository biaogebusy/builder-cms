<?php

namespace Drupal\pathauto\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\pathauto\PathautoState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pathauto entity update action.
 */
#[Action(
  id: 'pathauto_update_alias',
  label: new TranslatableMarkup('Update URL alias of an entity'),
)]
class UpdateAction extends ActionBase {

  /**
   * The path auto generator service.
   *
   * @var \Drupal\pathauto\PathautoGeneratorInterface
   */
  protected PathautoGeneratorInterface $pathautoGenerator;

  /**
   * Constructs an UpdateAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\pathauto\PathautoGeneratorInterface|null $pathauto_generator
   *   The pathauto generator.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?PathautoGeneratorInterface $pathauto_generator = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
    $this->pathautoGenerator = $pathauto_generator ?: \Drupal::service('pathauto.generator');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('pathauto.generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $entity->path->pathauto = PathautoState::CREATE;
    $this->pathautoGenerator->updateEntityAlias($entity, 'bulkupdate', ['message' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIfHasPermission($account, 'create url aliases');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
