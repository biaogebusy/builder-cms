<?php

namespace Drupal\layout_builder_st\Element;

use Drupal\block_content\BlockContentInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\layout_builder\Element\LayoutBuilder as CoreLayoutBuilder;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_st\TranslationsHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extended LayoutBuilder element to remove actions for translations.
 */
final class LayoutBuilder extends CoreLayoutBuilder {

  use DependencySerializationTrait;
  use TranslationsHelperTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    mixed ...$arguments,
  ) {
    parent::__construct(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function layout(SectionStorageInterface $section_storage) {
    $output = parent::layout($section_storage);
    $output['#attached']['library'][] = 'layout_builder_st/contextual';

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildAddSectionLink(SectionStorageInterface $section_storage, $delta) {
    $link = parent::buildAddSectionLink($section_storage, $delta);
    $this->setTranslationAccess($link, $section_storage);
    return $link;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildAdministrativeSection(SectionStorageInterface $section_storage, $delta) {
    $section_build = parent::buildAdministrativeSection($section_storage, $delta);
    $this->setTranslationAccess($section_build['remove'], $section_storage);
    $this->setTranslationAccess($section_build['configure'], $section_storage);
    if (static::isTranslation($section_storage)) {
      foreach (Element::children($section_build['layout-builder__section']) as $region) {
        $region_build = &$section_build['layout-builder__section'][$region];
        if (!empty($region_build['layout_builder_add_block'])) {
          $this->setTranslationAccess($region_build['layout_builder_add_block'], $section_storage);
        }
        foreach (Element::children($region_build) as $uuid) {
          if (substr_count($uuid, '-') !== 4) {
            continue;
          }
          // Remove class that enables drag and drop.
          // @todo Can we remove drag and drop in JS?
          if (($key = array_search('js-layout-builder-block', $region_build[$uuid]['#attributes']['class'])) !== FALSE) {
            unset($region_build[$uuid]['#attributes']['class'][$key]);
          }
          if ($contextual_link_element = $this->createContextualLinkElement($section_storage, $delta, $region, $uuid)) {
            $region_build[$uuid]['#contextual_links'] = $contextual_link_element;
          }
          else {
            unset($region_build[$uuid]['#contextual_links']);
          }
        }
      }
    }

    return $section_build;
  }

  /**
   * Set the #access property based section storage translation.
   */
  private function setTranslationAccess(array &$build, SectionStorageInterface $section_storage) {
    $build['#access'] = !static::isTranslation($section_storage);
  }

  /**
   * Creates contextual link element for a component.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param string $delta
   *   The section delta.
   * @param string $region
   *   The region.
   * @param string $uuid
   *   The UUID of the component.
   *
   * @return array|null
   *   The contextual link render array or NULL if none.
   */
  private function createContextualLinkElement(SectionStorageInterface $section_storage, $delta, $region, $uuid) {
    $section = $section_storage->getSection($delta);
    $contextual_link_settings = [
      'route_parameters' => [
        'section_storage_type' => $section_storage->getStorageType(),
        'section_storage' => $section_storage->getStorageId(),
        'delta' => $delta,
        'region' => $region,
        'uuid' => $uuid,
      ],
    ];
    if (static::isTranslation($section_storage)) {
      $contextual_group = 'layout_builder_block_translation';
      $component = $section->getComponent($uuid);
      /** @var \Drupal\Core\Language\LanguageInterface $language */
      $language = $section_storage->getTranslationLanguage();
      $contextual_link_settings['route_parameters']['langcode'] = $language->getId();

      /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $plugin */
      $plugin = $component->getPlugin();
      if ($plugin instanceof DerivativeInspectionInterface && $plugin->getBaseId() === 'inline_block') {
        $configuration = $plugin->getConfiguration();
        $block = $this->entityTypeManager->getStorage('block_content')
          ->loadRevision($configuration['block_revision_id']);
        if ($block instanceof BlockContentInterface && $block->isTranslatable()) {
          $contextual_group = 'layout_builder_inline_block_translation';
        }
      }
    }
    else {
      $contextual_group = 'layout_builder_block';
      // Add metadata about the current operations available in
      // contextual links. This will invalidate the client-side cache of
      // links that were cached before the 'move' link was added.
      // @see layout_builder.links.contextual.yml
      $contextual_link_settings['metadata'] = [
        'operations' => 'move:update:remove',
      ];
    }
    return [
      $contextual_group => $contextual_link_settings,
    ];
  }

}
