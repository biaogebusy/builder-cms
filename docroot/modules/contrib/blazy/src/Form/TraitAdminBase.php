<?php

namespace Drupal\blazy\Form;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\Traits\PluginScopesTrait;
use Drupal\blazy\Utility\Path;

/**
 * A blazy admin Trait to declutter, and focus more on form elements.
 */
trait TraitAdminBase {

  use PluginScopesTrait;
  use TraitDescriptions;
  use TraitAdminOptions;

  /**
   * The typed config manager service.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * {@inheritdoc}
   */
  public function getEntityDisplayRepository() {
    return $this->entityDisplayRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypedConfig() {
    return $this->typedConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * {@inheritdoc}
   */
  public function isAdminCss(): bool {
    $admin_css = $this->blazyManager->config('admin_css', 'blazy.settings') ?: FALSE;
    // Disable the admin css in the off canvas menu, to avoid conflicts with
    // the active frontend theme.
    if ($admin_css && $request = Path::requestStack()) {
      $current = $request->getCurrentRequest();
      $uri = $current->getRequestUri();
      $wrapper_format = $current->query->get('_wrapper_format');

      if ($wrapper_format === "drupal_dialog.off_canvas"
        || strpos($uri, '/views/nojs') !== FALSE) {
        $admin_css = FALSE;
      }
    }
    return $admin_css;
  }

  /**
   * {@inheritdoc}
   */
  public function toOptions(array $data): array {
    return $this->blazyManager->toOptions($data);
  }

  /**
   * {@inheritdoc}
   */
  public function toScopes(array &$definition): BlazySettings {
    // Looks like unit test failed with manager methods given a Trait.
    $definition += Blazy::init();

    $scopes = $definition['scopes'] ?? $this->toPluginScopes();
    if (!$scopes->get('initializer')) {
      $definition['scopes'] = $scopes = $this->getScopes($definition);
      $scopes->set('initializer', get_called_class());
    }
    return $scopes;
  }

  /**
   * Check scopes, a failsafe till sub-modules migrated.
   *
   * Temporary re-definitions during migration after BlazyFormatterTrait
   * ::getScopedFormElements() for sensible checks.
   *
   * @todo remove most after sub-module migrations.
   */
  protected function checkScopes(&$scopes, array &$definition): void {
    if ($scopes->was('scoped')) {
      return;
    }

    $definition['plugin_id'] = $definition['plugin_id'] ?? 'x';
    $settings = $definition['settings'] ?? [];
    $blazies = $definition['blazies'];
    $lightboxes = $this->blazyManager->getLightboxes();
    $is_responsive = function_exists('responsive_image_get_image_dimensions');
    $namespace = $blazies->get('namespace') ?: ($definition['namespace'] ?? '');
    $plugin_id = $blazies->get('field.plugin_id') ?: $definition['plugin_id'];
    $target_type = $blazies->get('field.target_type') ?: ($definition['target_type'] ?? '');
    $entity_type = $blazies->get('field.entity_type') ?: ($definition['entity_type'] ?? '');
    $view_mode = $blazies->get('field.view_mode') ?: ($definition['view_mode'] ?? '');
    $switch = !$scopes->is('no_lightboxes') && isset($settings['media_switch']);

    $bools = [
      'background',
      'caches',
      'grid_required',
      'grid_simple',
      'multimedia',
      'nav',
      'no_box_captions',
      'no_grid_header',
      'no_image_style',
      'no_layouts',
      'no_lightboxes',
      'no_loading',
      'no_preload',
      'no_thumb_effects',
      'responsive_image',
      'style',
      'thumbnail_style',
      'vanilla',
      '_views',
    ];

    foreach ($bools as $bool) {
      $value = $scopes->is($bool) || !empty($definition[$bool]);
      $scopes->set('is.' . $bool, $value);
    }

    // Redefine for easy calls later due to sub-modules not migrated yet.
    // @todo remove after sub-modules migrations, and simplify all these at 3.x.
    $responsive = $is_responsive && $scopes->is('responsive_image');
    $sliders = in_array($namespace, ['slick', 'splide']);
    $scopes->set('data.lightboxes', $lightboxes)
      ->set('is.fieldable', $target_type && $entity_type)
      ->set('is.lightbox', count($lightboxes) > 0)
      ->set('is.responsive_image', $responsive)
      ->set('is.slider', $scopes->is('slider') ?: $sliders)
      ->set('is.switch', $switch)
      ->set('namespace', $namespace)
      // @todo remove dups for $blazies object.
      ->set('entity.type', $entity_type)
      ->set('plugin_id', $plugin_id)
      ->set('target_type', $target_type)
      ->set('view_mode', $view_mode);

    $data = [
      'deprecations',
      'captions',
      'classes',
      'fullwidth',
      'images',
      'layouts',
      'libraries',
      'links',
      'optionsets',
      'overlays',
      'skins',
      'thumbnails',
      'thumbnail_effect',
      'thumb_captions',
      'titles',
    ];

    $captions = [
      'alt' => $this->t('Alt'),
      'title' => $this->t('Title'),
    ];

    foreach (['captions', 'thumb_captions'] as $key) {
      $check = $definition[$key] ?? NULL;
      if ($check == 'default') {
        $scopes->set('data.' . $key, $captions);
      }
    }

    foreach ($data as $key) {
      $value = $scopes->data($key) ?: ($definition[$key] ?? NULL);
      // Respects empty arrays so the option is visible to raise awareness.
      if (is_array($value)) {
        $scopes->set('data.' . $key, $value);
      }
    }

    // Merge deprecated settings.
    $scopes->set('data.deprecations', BlazyDefault::deprecatedSettings(), TRUE);

    $forms = [
      'grid',
      'fieldable',
      'image_style',
      'media_switch',
    ];

    foreach ($forms as $key) {
      $value = $scopes->form($key) ?: !empty($definition[$key . '_form']);
      if (is_bool($value)) {
        $scopes->set('form.' . $key, $value);
      }
    }

    // Ensures merged once.
    if (!$scopes->is('scopes_merged') && $definition['scopes']) {
      $definition['scopes'] = $definition['scopes']->merge($scopes->storage());
      $scopes->set('is.scopes_merged', TRUE);
    }

    $scopes->set('was.scoped', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array &$form, array $definition): void {
  }

  /**
   * {@inheritdoc}
   */
  public function fieldableForm(array &$form, array $definition): void {
  }

  /**
   * {@inheritdoc}
   */
  public function imageStyleForm(array &$form, array $definition): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $definition): array {
    return [];
  }

  /**
   * Returns the plugin scopes.
   */
  protected function getScopes(array &$definition): BlazySettings {
    return $this->toPluginScopes($definition);
  }

  /**
   * Returns form opening classes.
   */
  protected function getOpeningClasses($scopes): array {
    $namespace = $scopes->get('namespace', 'blazy');
    $classes = [];

    $items = ['blazy', 'slick', $namespace, 'half'];

    if ($scopes->is('_views')) {
      $items[] = 'views';
    }
    if ($scopes->is('vanilla')) {
      $items[] = 'vanilla';
    }
    if ($scopes->is('grid_required')) {
      $items[] = 'grid-required';
    }
    if ($plugin_id = $scopes->get('plugin_id')) {
      $items[] = 'plugin-' . str_replace('_', '-', $plugin_id);
    }
    if ($field_type = $scopes->get('field.type')) {
      $items[] = str_replace('_', '-', $field_type);
    }

    foreach ($items as $class) {
      $classes[] = 'form--' . $class;
    }

    $classes[] = 'b-tooltip';
    $classes[] = 'b-tooltip--lg';

    return $classes;
  }

  /**
   * Initialize the grid.
   */
  protected function initGrid($total, $classes): array {
    $options = [
      'count'   => $total,
      'classes' => $classes,
    ];

    $grids   = $this->blazyManager->initGrid($options);
    $attrs   = $grids['attributes'];
    $classes = implode(' ', $attrs['class']);

    return [
      'classes'  => $classes,
      'settings' => $grids['settings'],
    ];
  }

}
