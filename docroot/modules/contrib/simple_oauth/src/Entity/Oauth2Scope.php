<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\simple_oauth\Plugin\ScopeGranularityInterface;

/**
 * Defines the OAuth2 Scope entity.
 *
 * @ingroup simple_oauth
 *
 * @ConfigEntityType(
 *   id = "oauth2_scope",
 *   label = @Translation("scope"),
 *   label_collection = @Translation("Scopes"),
 *   label_singular = @Translation("Scope"),
 *   label_plural = @Translation("Scopes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count scope",
 *     plural = "@count scopes",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "access" = "Drupal\simple_oauth\Entity\Access\Oauth2ScopeAccessControlHandler",
 *     "list_builder" = "Drupal\simple_oauth\Entity\Oauth2ScopeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\simple_oauth\Entity\Form\Oauth2ScopeForm",
 *       "add" = "Drupal\simple_oauth\Entity\Form\Oauth2ScopeForm",
 *       "edit" = "Drupal\simple_oauth\Entity\Form\Oauth2ScopeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     }
 *   },
 *   config_prefix = "oauth2_scope",
 *   admin_permission = "administer oauth2 scopes",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name"
 *   },
 *   links = {
 *     "delete-form" = "/admin/config/people/simple_oauth/oauth2_scope/dynamic/{oauth2_scope}/delete",
 *     "edit-form" = "/admin/config/people/simple_oauth/oauth2_scope/dynamic/{oauth2_scope}",
 *     "collection" = "/admin/config/people/simple_oauth/oauth2_scope/dynamic",
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "description",
 *     "grant_types",
 *     "umbrella",
 *     "parent",
 *     "granularity_id",
 *     "granularity_configuration",
 *   },
 *   list_cache_tags = { "oauth2_scope" },
 * )
 */
class Oauth2Scope extends ConfigEntityBase implements Oauth2ScopeEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * The entity id.
   *
   * @var string
   */
  protected string $id;

  /**
   * The name of this scope.
   *
   * @var string
   */
  protected string $name = '';

  /**
   * The description of this scope.
   *
   * @var string
   */
  protected string $description = '';

  /**
   * The grant types where this scope is available.
   *
   * @var array
   */
  protected array $grant_types = [];

  /**
   * Umbrella flag; which groups scopes.
   *
   * @var bool
   */
  protected bool $umbrella = FALSE;

  /**
   * The parent scope.
   *
   * @var string|null
   */
  protected ?string $parent = NULL;

  /**
   * Plugin ID of the scope granularity plugin.
   *
   * @var string|null
   */
  protected ?string $granularity_id = NULL;

  /**
   * Plugin configuration of the scope granularity plugin.
   *
   * @var array|null
   */
  protected ?array $granularity_configuration = NULL;

  /**
   * The scope granularity plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface|null
   */
  protected ?PluginManagerInterface $granularityPluginManager;

  /**
   * The plugin collection for the granularity plugin.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   */
  protected ?DefaultSingleLazyPluginCollection $granularityCollection = NULL;

  /**
   * Returns the scope granularity plugin manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The scope granularity plugin manager.
   */
  protected function getGranularityPluginManager(): PluginManagerInterface {
    if (!isset($this->granularityPluginManager)) {
      $this->granularityPluginManager = \Drupal::service('plugin.manager.scope_granularity');
    }
    return $this->granularityPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $values = []) {
    $values['id'] = self::scopeToMachineName($values['name']);
    return parent::create($values);
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    if (!empty($this->name)) {
      return self::scopeToMachineName($this->name);
    }
    return parent::id();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $this->id = self::scopeToMachineName($this->name);
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantTypes(): array {
    return $this->grant_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantTypeDescription(string $grant_type): ?string {
    $grant_types = $this->getGrantTypes();
    return $grant_types[$grant_type]['description'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isGrantTypeEnabled(string $grant_type): bool {
    $grant_types = $this->getGrantTypes();
    return isset($grant_types[$grant_type]['status']) && $grant_types[$grant_type]['status'];
  }

  /**
   * {@inheritdoc}
   */
  public function isUmbrella(): bool {
    return $this->umbrella;
  }

  /**
   * {@inheritdoc}
   */
  public function getParent(): ?string {
    return !$this->isUmbrella() ? $this->parent : NULL;
  }

  /**
   * Gets the granularity plugin collection.
   *
   * @return ?\Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   *   The granularity plugin collection or NULL if this is an umbrella scope.
   */
  protected function getGranularityCollection(): ?DefaultSingleLazyPluginCollection {
    if (!isset($this->granularity_id)) {
      return NULL;
    }

    if (!$this->granularityCollection) {
      $this->granularityCollection = new DefaultSingleLazyPluginCollection(
        $this->getGranularityPluginManager(),
        $this->granularity_id,
        $this->granularity_configuration ?: [],
      );
    }
    return $this->granularityCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getGranularity(): ?ScopeGranularityInterface {
    if (!isset($this->granularity_id)) {
      return NULL;
    }

    $granularityCollection = $this->getGranularityCollection();
    assert($granularityCollection instanceof DefaultSingleLazyPluginCollection);
    return $granularityCollection->get($this->granularity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    $granularity = $this->getGranularity();
    assert($granularity instanceof ScopeGranularityInterface);
    return $granularity->getPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    $collections = [];

    $granularityCollection = $this->getGranularityCollection();
    if ($granularityCollection) {
      $collections['granularity_configuration'] = $granularityCollection;
    }

    return $collections;
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    // Sort by name.
    return strcmp($a->label(), $b->label());
  }

  /**
   * Converts a scope name to a valid Drupal machine name.
   *
   * E.g. user:auth:login is transformed to user_auth_login.
   *
   * @param string $scope
   *   The name of the scope to transform.
   *
   * @return string
   *   A valid Drupal machine name.
   */
  public static function scopeToMachineName(string $scope): string {
    // Replace any non-lowercase letter, number or underscore character by an
    // underscore.
    return preg_replace('/[^a-z0-9_]/', '_', mb_strtolower($scope));
  }

}
