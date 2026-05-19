<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Oauth2ScopeAdapterInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adapter class for the OAuth2 scope entity.
 */
class Oauth2ScopeEntityAdapter implements Oauth2ScopeAdapterInterface, ContainerInjectionInterface {

  /**
   * The scope storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $scopeStorage;

  /**
   * Oauth2ScopeEntityAdapter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->scopeStorage = $entity_type_manager->getStorage('oauth2_scope');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id) {
    return $this->scopeStorage->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL): array {
    return $this->scopeStorage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByName(string $name): ?Oauth2ScopeInterface {
    $scopes = $this->scopeStorage->loadByProperties(['name' => $name]);
    return !empty($scopes) ? reset($scopes) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleByNames(array $names): array {
    $entity_ids = $this->scopeStorage->getQuery()
      ->accessCheck()
      ->condition('name', $names, 'IN')
      ->execute();
    return $this->scopeStorage->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadChildren(string $parent_id): array {
    $entity_ids = $this->scopeStorage->getQuery()
      ->accessCheck()
      ->condition('parent', $parent_id)
      ->execute();
    return $this->scopeStorage->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByGrantType(string $grant_type): array {
    $scopes = $this->loadMultiple();
    return array_filter($scopes, function (Oauth2ScopeInterface $scope) use ($grant_type) {
      return $scope->isGrantTypeEnabled($grant_type);
    });
  }

}
