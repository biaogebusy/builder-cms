<?php

namespace Drupal\blazy\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\blazy\Internals\Internals;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterEntityTrait;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterTrait;
use Drupal\blazy\Internals\Field;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for entity reference formatters without field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityMediaBase
 */
abstract class BlazyEntityVanillaBase extends EntityReferenceFormatterBase {

  // Since 2.9 Blazy adapts to sub-module self::viewElements() to DRY so they
  // can remove their own FormatterViewTrait later thanks to similarities.
  use BlazyFormatterTrait {
    pluginSettings as traitPluginSettings;
  }

  use BlazyFormatterEntityTrait;
  use BlazyElementTrait;

  /**
   * The module namespace.
   *
   * @var string
   * @see https://www.php.net/manual/en/reserved.keywords.php
   */
  protected static $namespace = 'blazy';

  /**
   * The item property to store image or media: content, slide, box, etc.
   *
   * @var string
   */
  protected static $itemId = 'slide';

  /**
   * The item prefix for captions, e.g.: blazy__caption, slide__caption, etc.
   *
   * @var string
   */
  protected static $itemPrefix = 'slide';

  /**
   * The caption property to store captions.
   *
   * @var string
   */
  protected static $captionId = 'caption';

  /**
   * Tne navigation ID.
   *
   * @var string
   */
  protected static $navId = 'thumb';

  /**
   * The fake field type identifier for service DI, e.g: entity, image, text.
   *
   * @var string
   */
  protected static $fieldType = 'entity';

  /**
   * Whether displaying a single item by index, or not.
   *
   * @var bool
   */
  protected static $byDelta = FALSE;

  /**
   * Whether using the SVG.
   *
   * @var bool
   */
  protected static $useSvg = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return static::injectServices($instance, $container, static::$fieldType);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $definition = $this->getScopedDefinition($form);

    $this->admin()->buildSettingsForm($element, $definition);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entities = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($entities)) {
      return [];
    }

    return $this->commonViewElements($items, $langcode, $entities);
  }

  /**
   * Provides any entity contents.
   *
   * @param array $build
   *   The build array being passed.
   * @param array $entities
   *   The entities array.
   * @param string $langcode
   *   The langcode.
   */
  protected function buildElements(array &$build, array $entities, $langcode): void {
    foreach ($this->getElements($build, $entities, $langcode) as $element) {
      if ($element) {
        $build['items'][] = $element;

        $this->withOverride($build, $element);
      }
    }
  }

  /**
   * Generates elements.
   *
   * @param array $data
   *   The data array being passed.
   * @param array $entities
   *   The entities array.
   * @param string $langcode
   *   The langcode.
   *
   * @return \Generator
   *   The \Generator items.
   */
  private function getElements(array $data, array $entities, $langcode): \Generator {
    // @todo deprecate and remove the helper at/ by 3.x post migrations:
    $this->formatter->hashtag($data);

    // Do not reference here, else causes duplicates.
    /** @var array $settings */
    $settings = $data['#settings'];
    $blazies  = Internals::getBlazies($settings);
    $limit    = $this->getViewLimit($settings);
    $by_delta = $settings['by_delta'] ?? -1;
    $total    = $blazies->total();
    $valid    = $by_delta > -1 && $by_delta < $total;

    // Returns a single item by delta if so-configured.
    if ($valid && $entity = ($entities[$by_delta] ?? NULL)) {
      Internals::updateCountByDelta($settings);
      $data['#settings'] = $settings;

      yield $this->getElement($data, $entity, $by_delta);
    }
    else {
      // Else a regular loop.
      foreach ($entities as $delta => $entity) {
        static $depth = 0;
        $depth++;
        $element = [];

        // Protect ourselves from recursive rendering.
        if ($depth > 20) {
          $this->loggerFactory->get('entity')
            ->error('Recursive rendering detected when rendering entity @entity_type @entity_id. Aborting rendering.', [
              '@entity_type' => $entity->getEntityTypeId(),
              '@entity_id' => $entity->id(),
            ]);
          yield $element;
        }
        else {
          // If a Views display, bail out if more than Views delta_limit.
          // @todo figure out why Views delta_limit doesn't stop us here.
          if ($limit > 0 && $delta > $limit - 1) {
            yield $element;
          }
          else {
            yield $this->getElement($data, $entity, $delta);
          }
        }

        $depth = 0;
      }
    }
  }

  /**
   * Returns available bundles.
   *
   * @return array
   *   The available bundles.
   */
  protected function getAvailableBundles(): array {
    $field = $this->fieldDefinition;
    return Field::getAvailableBundles($field);
  }

  /**
   * Returns the individual element.
   *
   * @param array $data
   *   The data array being passed.
   * @param object $entity
   *   The entity.
   * @param int $delta
   *   The element delta.
   *
   * @return array
   *   The element array.
   */
  protected function getElement(array $data, $entity, $delta): array {
    $current            = $data;
    $current['#delta']  = $delta;
    $current['#entity'] = $entity;
    $current['#parent'] = $data['#entity'] ?? NULL;

    // @todo refine yield item here at 3.x.
    if ($element = $this->withElement($current)) {
      $item = $element[$delta] ?? $element;

      // Add the entity to cache dependencies so to clear when updated.
      $this->formatter->renderer()
        ->addCacheableDependency($item, $entity);

      return $element;
    }
    return [];
  }

  /**
   * Returns fields as options. Passing empty array will return them all.
   *
   * @param array $names
   *   The field names array being passed.
   * @param string|null $entity_type
   *   The entity type.
   * @param string|null $target_type
   *   The target type.
   * @param bool $exclude
   *   Whether to exclude.
   *
   * @return array
   *   The available fields as options.
   */
  protected function getFieldOptions(
    array $names = [],
    $entity_type = NULL,
    $target_type = NULL,
    $exclude = TRUE,
  ): array {
    $entity_type = $entity_type ?: $this->getFieldSetting('target_type');
    $bundles     = $this->getAvailableBundles();

    return $this->getFieldOptionsWithBundles($bundles, $names, $entity_type, $target_type, $exclude);
  }

  /**
   * Returns plugin scopes to limit the features.
   *
   * @return array
   *   The plugin scopes.
   */
  protected function getPluginScopes(): array {
    $multiple = $this->isMultiple();
    return [
      'by_delta'         => $multiple && static::$byDelta,
      'no_layouts'       => TRUE,
      'no_image_style'   => TRUE,
      'responsive_image' => FALSE,
      'target_bundles'   => $this->getAvailableBundles(),
      // Refers to form item Vanilla.
      'vanilla'          => FALSE,
      'view_mode'        => $this->viewMode,
      'multiple'         => $this->isMultiple(),
      'grid_form'        => $multiple,
      'style'            => $multiple,
    ];
  }

  /**
   * Modifies plugin settings.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   * @param array $settings
   *   The settings being modified.
   */
  protected function pluginSettings(&$blazies, array &$settings): void {
    $this->traitPluginSettings($blazies, $settings);
  }

  /**
   * Provides detailed elements.
   *
   * @param array $build
   *   The build array being passed.
   *
   * @return array
   *   The element with detailed output.
   */
  protected function withElementDetail(array $build): array {
    return [];
  }

  /**
   * Provides vanilla elements.
   *
   * @param array $build
   *   The build array being passed.
   *
   * @return array
   *   The element with vanilla output.
   */
  protected function withElementVanilla(array $build): array {
    if ($element = $this->blazyEntity->view($build)) {
      return $this->withHashtag($build, $element);
    }
    return [];
  }

  /**
   * Provides item elements.
   *
   * @param array $build
   *   The build array being passed.
   *
   * @return array
   *   The element with detailed or vanilla output.
   */
  private function withElement(array $build): array {
    $settings = &$build['#settings'];
    $langcode = $build['#langcode'];
    $blazies  = $settings['blazies']->reset($settings);
    $delta    = $build['#delta'];
    $entity   = $build['#entity'];
    $bundle   = $entity->bundle();
    $current  = $delta . '-' . $entity->id();

    $blazies->set('bundles.' . $bundle, $bundle, TRUE)
      ->set('language.code', $langcode)
      ->set('delta', $delta)
      ->set('item.current', $current);

    // Sub-modules always flag `vanilla` as required, -- configurable, or not.
    if (empty($settings['vanilla'])) {
      return $this->withElementDetail($build);
    }

    return $this->withElementVanilla($build);
  }

  /**
   * Provides overrides for BC.
   *
   * @param array $build
   *   The build array being modified.
   * @param array $element
   *   The build array being passed.
   */
  private function withOverride(array &$build, array $element): void {
    foreach (['delta', 'entity', 'settings'] as $key) {
      $default = $key == 'settings' ? [] : NULL;
      $build["#$key"] = $element["#$key"] ?? $build["#$key"] ?? $default;
    }

    $delta    = $build['#delta'];
    $entity   = $build['#entity'];
    $settings = $build['#settings'];

    if (method_exists($this, 'withElementOverride')) {
      $this->withElementOverride($build, $element);
    }
    else {
      $blazies = Internals::getBlazies($settings);
      if ($blazies->is('nav')) {
        if (method_exists($this, 'withElementThumbnail')) {
          $this->withElementThumbnail($build, $element);
        }
        // @todo deprecate and remove at/ by 3.x only after sub-modules:
        elseif (method_exists($this, 'buildElementThumbnail')) {
          $this->buildElementThumbnail($build, $element, $entity, $delta);
        }
      }
    }
  }

  /**
   * Returns scoped definitions.
   *
   * @param array $form
   *   The form array being passed.
   *
   * @return array
   *   The form definition.
   */
  protected function getScopedDefinition(array $form): array {
    $definition = $this->getScopedFormElements();

    $definition['_views'] = isset($form['field_api_classes']);
    $definition['field_api_classes'] = $form['field_api_classes']['#default_value'] ?? FALSE;

    // @todo deprecate and remove after sub-modules.
    $definition['view_mode'] = $this->viewMode;
    $definition['plugin_id'] = $this->getPluginId();
    $definition['target_type'] = $this->getFieldSetting('target_type');

    return $definition;
  }

}
