<?php

namespace Drupal\shs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\shs\Cache\ShsCacheableJsonResponse;
use Drupal\shs\Cache\ShsTermCacheDependency;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for getting taxonomy terms.
 */
class ShsController extends ControllerBase {

  /**
   * The dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * Construct a new ShsController object.
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container);
  }

  /**
   * Load term data.
   *
   * @param string $identifier
   *   Name of field to load the data for.
   * @param string $bundle
   *   Bundle (vocabulary) identifier to limit the return list to a specific
   *   bundle.
   * @param int $entity_id
   *   Id of parent term to load all children for. Defaults to 0.
   *
   * @return CacheableJsonResponse
   *   Cacheable Json response.
   */
  public function getTermData($identifier, $bundle, $entity_id = 0) {
    $context = [
      'identifier' => $identifier,
      'bundle' => $bundle,
      'parent' => $entity_id,
    ];
    $response = new ShsCacheableJsonResponse($context);

    $cache_tags = [];
    $result = [];

    $langcode_current = $this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');

    $translation_enabled = FALSE;
    if ($this->moduleHandler()->moduleExists('content_translation')) {
      /** @var \Drupal\content_translation\ContentTranslationManagerInterface $translation_manager */
      $translation_manager = $this->container->get('content_translation.manager');
      // If translation is enabled for the vocabulary, we need to load the full
      // term objects to get the translation for the current language.
      $translation_enabled = $translation_manager->isEnabled('taxonomy_term', $bundle);
    }

    $access_unpublished = $this->currentUser()->hasPermission('administer taxonomy') || $this->currentUser()->hasPermission('view unpublished terms in ' . $bundle);
    $cache_tags[] = 'access_unpublished:' . $access_unpublished;

    /** @var \Drupal\taxonomy\TermInterface[] $terms */
    $terms = $storage->loadTree($bundle, $entity_id, 1, $translation_enabled);

    foreach ($terms as $term) {
      $langcode = $langcode_current;
      if ($translation_enabled && ($term instanceof TranslatableInterface) && $term->hasTranslation($langcode)) {
        $term = $term->getTranslation($langcode);
      }
      else {
        $langcode = $term->default_langcode;
      }

      $tid = $translation_enabled ? $term->id() : $term->tid;
      $published = $translation_enabled ? $term->isPublished() : $term->status;

      if (!$published && !$access_unpublished) {
        continue;
      }

      $result[] = (object) [
        'tid' => $tid,
        'name' => $translation_enabled ? $term->getName() : $term->name,
        'description__value' => $translation_enabled ? $term->getDescription() : $term->description__value,
        'langcode' => $langcode,
        'hasChildren' => shs_term_has_children($tid),
      ];
      $cache_tags[] = sprintf('taxonomy_term:%d', $tid);
    }

    $response->addCacheableDependency(new ShsTermCacheDependency($cache_tags));
    $response->setData($result, TRUE);

    return $response;
  }

}
