<?php

namespace Drupal\easy_breadcrumb;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\Controller\TitleResolver as ControllerTitleResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Resolves page titles for controllers based on various criteria.
 */
class TitleResolver extends ControllerTitleResolver {

  /**
   * The field storage config storage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Breadcrumb config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new EntityDisplayRebuilder.
   *
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver
   *   The controller resolver.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
   *   The argument resolver.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(ControllerResolverInterface $controller_resolver, TranslationInterface $string_translation, ArgumentResolverInterface $argument_resolver, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, LanguageManagerInterface $languageManager) {
    parent::__construct($controller_resolver, $string_translation, $argument_resolver);
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get(EasyBreadcrumbConstants::MODULE_SETTINGS);
    $this->languageManager = $languageManager;
  }

  /**
   * Returns a static or dynamic title for the route.
   *
   * If the returned title can contain HTML that should not be escaped it should
   * return a render array, for example:
   * @code
   * ['#markup' => 'title', '#allowed_tags' => ['em']]
   * @endcode
   * If the method returns a string and it is not marked safe then it will be
   * auto-escaped.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object passed to the title callback.
   *
   * @return array|string|\Stringable|null
   *   The title for the route.
   *   The title for the route. NULL should be returned if the method can
   *   determine that the title will evaluate to an empty string.
   */
  public function getAlternateTitle(Request $request) {
    $url = Url::fromUri("internal:" . $request->getRequestUri());
    $alternative_title_field = $this->config->get(EasyBreadcrumbConstants::ALTERNATIVE_TITLE_FIELD);
    // If an alternative title field is set, load the entity if present and
    // use that field.
    if ($alternative_title_field) {
      $entity = NULL;
      try {
        $route_parts = explode(".", $url->getRouteName());
        $params = $url->getRouteParameters();
        if (!empty($route_parts[0]) && $route_parts[0] === 'entity' && count($route_parts) >= 3 && $route_parts[2] === 'canonical') {
          $entity_type = $route_parts[1];
          if (isset($params[$entity_type])) {
            $entity = $this->entityTypeManager->getStorage($entity_type)->load($params[$entity_type]);
          }
        }
      }
      catch (\UnexpectedValueException $e) {
        // Do nothing for now.
      }
      if ($entity !== NULL) {
        $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
        if ($entity instanceof TranslatableInterface && $entity->hasTranslation($current_langcode)) {
          $entity = $entity->getTranslation($current_langcode);
        }
        if ($entity instanceof FieldableEntityInterface && $entity->hasField($alternative_title_field) && !$entity->get($alternative_title_field)
          ->isEmpty()) {
          return Xss::filter($entity->get($alternative_title_field)->value);
        }
      }
    }

    return '';
  }

}
