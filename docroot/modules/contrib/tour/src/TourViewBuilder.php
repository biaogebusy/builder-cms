<?php

namespace Drupal\tour;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Tour view builder.
 *
 * Note: Does not invoke any alter hooks. In other view
 * builders, the view alter hooks are run later in the process.
 */
class TourViewBuilder extends EntityViewBuilder {

  /**
   * Constructs a new TourViewBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme registry.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    protected RendererInterface $renderer,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('renderer'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultiple(array $entities = [], $view_mode = 'full', $langcode = NULL): array {
    /** @var \Drupal\tour\TourInterface[] $entities */
    $tour = [];
    $cache_tags = [];
    $total_tips = 0;
    $all_tips = [];
    foreach ($entities as $entity_id => $entity) {
      $all_tips = array_merge($all_tips, $entity->getTips());
      $tour[$entity_id] = $entity->getTips();
      $total_tips += count($tour[$entity_id]);
      $cache_tags = Cache::mergeTags($cache_tags, $entity->getCacheTags());
    }

    uasort($all_tips, function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    $items = [];
    $counter = 0;
    foreach ($all_tips as $tip) {
      $classes = [
        'tip-type-' . Html::getClass($tip->getPluginId()),
        'tip-' . Html::getClass($tip->id()),
      ];

      $selector = $tip->getSelector() ? $tip->getSelector() : NULL;
      $location = $tip->getLocation();

      $body_render_array = $tip->getBody();
      $body = (string) $this->renderer->renderInIsolation($body_render_array);
      $output = [
        'body' => $body,
        'title' => $tip->getLabel(),
      ];

      if ($output) {
        $items[] = [
          'id' => $tip->id(),
          'selector' => $selector,
          'type' => $tip->getPluginId(),
          'counter' => $this->t('@tour_item of @total', [
            '@tour_item' => $counter + 1,
            '@total' => $total_tips,
          ]),
          'attachTo' => [
            'element' => $selector,
            'on' => $location ?? 'bottom-start',
          ],
          // Shepherd expects classes to be provided as a string.
          'classes' => implode(' ', $classes),
        ] + $output;
      }
      $counter++;
    }

    // If there is at least one tour item, build the tour.
    if ($items) {
      end($items);
      $key = key($items);
      $items[$key]['cancelText'] = t('End tour');
    }

    $build = [
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];

    // If at least one tour was built, attach tips and the tour library.
    if ($items) {
      $build['#attached']['drupalSettings']['tourShepherdConfig'] = [
        'defaultStepOptions' => [
          'classes' => 'drupal-tour',
          'cancelIcon' => [
            'enabled' => TRUE,
            'label' => $this->t('Close'),
          ],
          'modalOverlayOpeningPadding' => 3,
          'scrollTo' => [
            'behavior' => 'smooth',
            'block' => 'center',
          ],
          'popperOptions' => [
            'modifiers' => [
              // Prevent overlap with the element being highlighted.
              [
                'name' => 'offset',
                'options' => [
                  'offset' => [-10, 20],
                ],
              ],
              // Pad the arrows, so they don't hit the edge of rounded corners.
              [
                'name' => 'arrow',
                'options' => [
                  'padding' => 12,
                ],
              ],
              // Disable Shepherd's focusAfterRender modifier, which results in
              // the tour item container being focused on any scroll or resize
              // event.
              [
                'name' => 'focusAfterRender',
                'enabled' => FALSE,
              ],

            ],
          ],
        ],
        'useModalOverlay' => TRUE,
      ];
      // This property is used for storing the tour items. It may change without
      // notice and should not be extended or modified in contrib.
      // see: https://www.drupal.org/project/drupal/issues/3214593
      $build['#attached']['drupalSettings']['_tour_internal'] = $items;
      $build['#attached']['library'][] = 'tour/tour';

      // Add recap configuration if enabled.
      $config = $this->configFactory->get('tour.settings');
      if ($config->get('enable_recap')) {
        $recap_urls = [];
        foreach ($entities as $entity) {
          $recap_urls[$entity->id()] = Url::fromRoute('tour.recap', ['tour' => $entity->id()])->toString();
        }
        $build['#attached']['drupalSettings']['tourRecap'] = [
          'enabled' => TRUE,
          'urls' => $recap_urls,
          'buttonText' => $this->t('View steps'),
        ];
        $build['#cache']['tags'][] = 'config:tour.settings';
      }
    }

    // Allow modules to alter the tour build array.
    $this->moduleHandler()->alter('tour_build', $build, $entities);

    return $build;
  }

}
