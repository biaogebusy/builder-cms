<?php

namespace Drupal\simple_oauth_static_scope\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;

/**
 * Provides a plugin manager for static OAuth2 scopes.
 *
 * Modules can define scopes in an MODULE_NAME.oauth2_scopes.yml file.
 * Each scope has the following structure:
 * @code
 *  "scope:name":
 *    description: STRING (required)
 *    umbrella: BOOLEAN (required)
 *    grant_types: (required)
 *      GRANT_TYPE_PLUGIN_ID: (required: only known grant types)
 *        status: BOOLEAN (required)
 *        description: STRING
 *    parent: STRING
 *    granularity: STRING (required: if umbrella is FALSE, values: permission or role)
 *    permission: STRING (required: if umbrella is FALSE and granularity set to permission)
 *    role: STRING (required: if umbrella is FALSE and granularity set to role)
 * @endcode
 */
class Oauth2ScopeManager extends DefaultPluginManager implements Oauth2ScopeManagerInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'class' => Oauth2Scope::class,
  ];

  /**
   * The plugin instances keyed by plugin id's.
   *
   * @var array
   */
  protected array $instances = [];

  /**
   * Plugin id's mapped by parent.
   *
   * @var array
   */
  protected array $parentMap = [];

  public function __construct(
    protected $moduleHandler,
    CacheBackendInterface $cacheBackend,
    LanguageManagerInterface $languageManager,
    protected PluginManagerInterface $granularityManager,
    protected Oauth2GrantManagerInterface $grantManager,
  ) {
    // Skip calling the parent constructor, since that assumes annotation-based
    // discovery.
    $this->factory = new ContainerFactory($this, Oauth2ScopePluginInterface::class);
    $this->alterInfo('oauth2_scope_info');
    $this->setCacheBackend($cacheBackend, 'oauth2_scope_plugins:' . $languageManager->getCurrentLanguage()->getId(), ['oauth2_scope_plugins']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $yaml_discovery = new YamlDiscovery('oauth2_scopes', $this->moduleHandler->getModuleDirectories());
      $yaml_discovery->addTranslatableProperty('description');
      $this->discovery = $yaml_discovery;
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions(): array {
    $definitions = parent::findDefinitions();
    $this->validateParent($definitions);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions(): ?array {
    $definitions = parent::getDefinitions();
    ksort($definitions);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);

    // Plugin id validation.
    if (preg_match('/[^a-z0-9_:]+/', $plugin_id)) {
      throw new PluginException(sprintf('OAuth2 scope plugin id "%s" must only contain lowercase letters, numbers, underscores, and colons.', $plugin_id));
    }

    // Required properties.
    foreach (['description', 'grant_types'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('OAuth2 scope plugin "%s" must define the "%s" property.', $plugin_id, $required_property));
      }
    }
    if (!isset($definition['umbrella'])) {
      throw new PluginException(sprintf('OAuth2 scope plugin "%s" must define the "umbrella" property.', $plugin_id));
    }
    if (!$definition['umbrella']) {
      if (isset($definition['granularity'])) {
        @trigger_error(sprintf('Using the "granularity" property for OAuth2 scope plugin "%s" is deprecated in simple_oauth:6.0.0 and is removed from simple_oauth:7.0.0. Use the "granularity_id" property instead. See https://www.drupal.org/node/3492888', $plugin_id), E_USER_DEPRECATED);
        $definition['granularity_id'] = $definition['granularity'];
        unset($definition['granularity']);
      }
      if (empty($definition['granularity_id'])) {
        throw new PluginException(sprintf('OAuth2 scope plugin "%s" must define the "granularity" property when umbrella is set to FALSE.', $plugin_id));
      }
      if (!$this->granularityManager->hasDefinition($definition['granularity_id'])) {
        throw new PluginException(sprintf('OAuth2 scope plugin "%s" invalid granularity value is set.', $plugin_id));
      }
      $granularity = $this->granularityManager->createInstance($definition['granularity_id']);

      $definition += ['granularity_configuration' => []];
      if (($definition['granularity_id'] === Oauth2ScopeInterface::GRANULARITY_PERMISSION) && isset($definition['permission'])) {
        @trigger_error(sprintf('Using the top-level "permission" property for OAuth2 scope plugin "%s" is deprecated in simple_oauth:6.0.0 and is removed from simple_oauth:7.0.0. Place a "permission" sub-property inside of a new top-level "granularity_configuration" property instead. See https://www.drupal.org/node/3492888', $plugin_id), E_USER_DEPRECATED);
        $definition['granularity_configuration']['permission'] = $definition['permission'];
        unset($definition['permission']);
      }
      if (($definition['granularity_id'] === Oauth2ScopeInterface::GRANULARITY_ROLE) && isset($definition['role'])) {
        @trigger_error(sprintf('Using the top-level "role" property for OAuth2 scope plugin "%s" is deprecated in simple_oauth:6.0.0 and is removed from simple_oauth:7.0.0. Place a "role" sub-property inside of a new top-level "granularity_configuration" property instead. See https://www.drupal.org/node/3492888', $plugin_id), E_USER_DEPRECATED);
        $definition['granularity_configuration']['role'] = $definition['role'];
        unset($definition['role']);
      }
      try {
        $granularity->validateConfiguration($definition['granularity_configuration']);
      }
      catch (PluginException $exception) {
        throw new PluginException(sprintf('OAuth2 scope plugin "%s" invalid granularity configuration for the granularity "%s": %s', $plugin_id, $definition['granularity_id'], $exception->getMessage()), $exception->getCode(), $exception);
      }

      if (!empty($definition['parent']) && $definition['parent'] === $plugin_id) {
        throw new PluginException(sprintf('OAuth2 scope plugin "%s" parent reference to itself is not allowed.', $plugin_id));
      }
    }

    $grant_plugin_definitions = $this->grantManager->getDefinitions();
    foreach ($definition['grant_types'] as $grant_type_key => $grant_type) {
      // Grant type plugin needs to exist.
      if (!array_key_exists($grant_type_key, $grant_plugin_definitions)) {
        throw new PluginException(sprintf('OAuth2 scope plugin "%s" grant type "%s" is undefined.', $plugin_id, $grant_type_key));
      }
      if (empty($grant_type['status'])) {
        throw new PluginException(sprintf('OAuth2 scope plugin "%s" grant type "%s" must define the "status" property.', $plugin_id, $grant_type_key));
      }
      // Make grant type description translatable.
      if (isset($grant_type['description'])) {
        // phpcs:ignore
        $definition['grant_types'][$grant_type_key]['description'] = $this->t($grant_type['description'], [], ['context' => 'grant_type']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    $plugin_id = $options['id'];

    if (!isset($this->instances[$plugin_id])) {
      $this->instances[$plugin_id] = $this->createInstance($plugin_id);
    }

    return $this->instances[$plugin_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(?array $ids = NULL): array {
    $instances = [];

    if (empty($ids)) {
      $definitions = $this->getDefinitions();
      $ids = !empty($definitions) ? array_keys($definitions) : [];
    }

    foreach ($ids as $plugin_id) {
      if (!isset($this->instances[$plugin_id])) {
        try {
          $this->instances[$plugin_id] = $this->createInstance($plugin_id);
        }
        catch (PluginNotFoundException $e) {
          continue;
        }
      }
      $instances[$plugin_id] = $this->instances[$plugin_id];
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  public function getChildrenInstances(string $parent_id): array {
    if (!isset($this->parentMap[$parent_id])) {
      $definitions = $this->getDefinitions();
      if (!empty($definitions)) {
        foreach ($definitions as $plugin_id => $definition) {
          if (isset($definition['parent']) && $definition['parent'] === $parent_id) {
            $this->parentMap[$parent_id][] = $plugin_id;
          }
        }
      }
    }

    $children_instances = [];
    if (isset($this->parentMap[$parent_id])) {
      foreach ($this->parentMap[$parent_id] as $child_id) {
        $children_instances[$child_id] = $this->getInstance(['id' => $child_id]);
      }
    }

    return $children_instances;
  }

  /**
   * Validates the parent property.
   *
   * @param array $definitions
   *   The plugin definitions.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function validateParent(array $definitions): void {
    $hierarchy = [];

    // Collect parent references.
    foreach ($definitions as $plugin_id => $definition) {
      if (!empty($definition['parent'])) {
        $hierarchy[$plugin_id] = $definition['parent'];
      }
    }

    // Recursive parent reference is not allowed.
    foreach ($definitions as $plugin_id => $definition) {
      if (
        !empty($definition['parent']) &&
        !empty($hierarchy[$definition['parent']]) &&
        $hierarchy[$definition['parent']] === $plugin_id
      ) {
        throw new PluginException(sprintf('OAuth2 scope plugin "%s" has a recursive parent reference.', $plugin_id));
      }
    }
  }

}
