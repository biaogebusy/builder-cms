<?php

namespace Drupal\ai_translate\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Controller\ControllerResolver;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\content_translation\Controller\ContentTranslationController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overridden class for entity translation controllers.
 */
class ContentTranslationControllerOverride extends ContentTranslationController {

  /**
   * Initializes a content translation controller.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   A content translation manager instance.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\ai\AiProviderPluginManager $providerManager
   *   The AI provider manager.
   * @param \Drupal\Core\Controller\ControllerResolver $controllerResolver
   *   The controller resolver.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    ContentTranslationManagerInterface $manager,
    EntityFieldManagerInterface $entity_field_manager,
    protected readonly AiProviderPluginManager $providerManager,
    protected readonly ControllerResolver $controllerResolver,
    ?TimeInterface $time = NULL,
  ) {
    parent::__construct($manager, $entity_field_manager, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_translation.manager'),
      $container->get('entity_field.manager'),
      $container->get('ai.provider'),
      $container->get('controller_resolver'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    $build = NULL;

    $parent_controller_id = $route_match->getRouteObject()->getDefault('_parent_controller');

    // Let the original controller build the form it wants to.
    if ($parent_controller = $this->controllerResolver->getControllerFromDefinition($parent_controller_id, $route_match->getRouteObject()->getPath())) {
      $build = call_user_func([$parent_controller[0], $parent_controller[1]], $route_match, $entity_type_id);
    }

    if (!$build) {

      // If anything is unexpected, just use our parent controller to generate
      // the build.
      $build = parent::overview($route_match, $entity_type_id);
    }

    $this->alterForm($build, $route_match, $entity_type_id);

    return $build;
  }

  /**
   * Helper to alter whatever page exists at our route.
   *
   * @param array $form
   *   The built form.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param mixed $entity_type_id
   *   The entity type ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function alterForm(array &$form, RouteMatchInterface $route_match, mixed $entity_type_id = NULL): void {
    _ai_translate_check_default_provider_and_model();
    $overview = NULL;

    foreach (Element::children($form) as $child) {
      if (!$overview) {
        if (isset($form[$child]['#theme']) && $form[$child]['#theme'] == 'table') {
          $overview = &$form[$child];
        }
        elseif (isset($form[$child]['#type']) && $form[$child]['#type'] == 'tableselect') {
          $overview = &$form[$child];
        }
      }
    }

    if (!$overview) {
      return;
    }

    $languages = $this->languageManager()->getLanguages();

    $entity = $route_match->getParameter($entity_type_id);
    $entity_id = $entity->id();
    /** @var Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $default_revision = $storage->load($entity_id);
    $entity_type = $entity->getEntityType();

    $use_latest_revisions = $entity_type->isRevisionable() && ContentTranslationManager::isPendingRevisionSupportEnabled($entity_type_id, $entity->bundle());
    $lang_from = $entity->getUntranslated()->language()->getId();

    $config = $this->config('ai_translate.settings');
    $key = 0;

    // Process translation in 2 passes.
    // In the first pass, build translation links with access check.
    // In the second pass, add translation links to the overview.
    // Do the second pass only if there is at least one link to translate.
    $hasTranslationLinks = FALSE;
    $aiTranslationLinks = [];

    // Pass 1.
    foreach ($languages as $langcode => $language) {
      if (isset($overview['#rows'][$key])) {
        $row = &$overview['#rows'][$key];
        $key++;
      }
      elseif (isset($overview['#options'][$langcode])) {
        $row = &$overview['#options'][$langcode];
      }
      else {
        $row = NULL;
      }

      // If we don't have a row, we cannot do anything.
      if ($row) {
        // Get the latest revision.
        // This logic comes from web/core/modules/content_translation/src/Controller/ContentTranslationController.php.
        if ($use_latest_revisions) {
          $entity = $default_revision;
          $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $langcode);
          if ($latest_revision_id) {
            /** @var \Drupal\Core\Entity\ContentEntityInterface $latest_revision */
            $latest_revision = $storage->loadRevision($latest_revision_id);
            // Make sure we do not list removed translations, i.e.
            // translations that have been part of a default revision but no
            // longer are.
            if (!$latest_revision->wasDefaultRevision() || $default_revision->hasTranslation($langcode)) {
              $entity = $latest_revision;
            }
          }
        }

        $ai_model = FALSE;
        $additional = '';
        if ($lang_from !== $langcode && !$entity->hasTranslation($langcode)) {
          $model = $config->get('language_settings')[$langcode]['model'] ?? '';
          $parts = explode('__', $model);
          if ($model == "" || empty($parts[0])) {
            $default_model = $this->providerManager->getSimpleDefaultProviderOptions('translate_text');
            if ($default_model !== "") {
              $parts1 = explode('__', $default_model);
              $ai_model = $parts1[1];
            }
          }
          else {
            $ai_model = $parts[1];
          }
          if ($ai_model) {
            $additional = Link::createFromRoute($this->t('Translate using @ai', ['@ai' => $ai_model]),
              'ai_translate.translate_content', [
                'entity_type' => $entity_type_id,
                'entity_id' => $entity_id,
                'lang_from' => $lang_from,
                'lang_to' => $langcode,
              ]
            );
            if ($additional->getUrl()->access()) {
              $additional = $additional->toString();
              $hasTranslationLinks = TRUE;
            }
            else {
              $additional = '';
            }
          }
        }
        else {
          $additional = $this->t('n/a');
        }
        $aiTranslationLinks[$langcode] = $additional;
      }
    }
    // Pass 2. Add a column to the table header, and translation link(s).
    if ($hasTranslationLinks) {
      // Inject our additional column into the header.
      array_splice($overview['#header'], -1, 0,
        [$this->t('AI Translations')]);
      $key = 0;
      foreach ($languages as $langcode => $language) {
        if (isset($overview['#rows'][$key])) {
          $row = &$overview['#rows'][$key];
          $key++;
        }
        elseif (isset($overview['#options'][$langcode])) {
          $row = &$overview['#options'][$langcode];
        }
        else {
          continue;
        }
        array_splice($row, -1, 0, [$aiTranslationLinks[$langcode]]);
      }
    }
  }

}
