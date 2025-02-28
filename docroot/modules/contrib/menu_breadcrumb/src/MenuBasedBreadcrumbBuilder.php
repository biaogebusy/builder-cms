<?php

namespace Drupal\menu_breadcrumb;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

/**
 * {@inheritdoc}
 */
class MenuBasedBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * The configuration object generator.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The menu active trail interface.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The menu link manager interface.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The admin context generator.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The caching backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheMenu;

  /**
   * The locking backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The Menu Breadcrumbs configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The menu where the current page or taxonomy match has taken place.
   *
   * @var string
   */
  private $menuName;

  /**
   * The menu trail leading to this match.
   *
   * @var string
   */
  private $menuTrail;

  /**
   * Node of current path if taxonomy attached.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $taxonomyAttachment;

  /**
   * Content language code (used in both applies() and build()).
   *
   * @var string
   */
  private $contentLanguage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    MenuActiveTrailInterface $menu_active_trail,
    MenuLinkManagerInterface $menu_link_manager,
    AdminContext $admin_context,
    TitleResolverInterface $title_resolver,
    RequestStack $request_stack,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    CacheBackendInterface $cache_menu,
    LockBackendInterface $lock
  ) {
    $this->configFactory = $config_factory;
    $this->menuActiveTrail = $menu_active_trail;
    $this->menuLinkManager = $menu_link_manager;
    $this->adminContext = $admin_context;
    $this->titleResolver = $title_resolver;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheMenu = $cache_menu;
    $this->lock = $lock;
    $this->config = $this->configFactory->get('menu_breadcrumb.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // This may look heavyweight for applies() but we have to check all ways the
    // current path could be attached to the selected menus before turning over
    // breadcrumb building (and caching) to another builder.  Generally this
    // should not be a problem since it will then fall back to the system (path
    // based) breadcrumb builder which caches a breadcrumb no matter what.
    if (!$this->config->get('determine_menu')) {
      return FALSE;
    }
    // Don't breadcrumb the admin pages, if disabled on config options:
    if ($this->config->get('disable_admin_page') && $this->adminContext->isAdminRoute($route_match->getRouteObject())) {
      return FALSE;
    }
    // No route name means no active trail:
    $route_name = $route_match->getRouteName();
    if (!$route_name) {
      return FALSE;
    }

    // This might be a "node" with no fields, e.g. a route to a "revision" URL,
    // so we don't check for taxonomy fields on unfieldable nodes:
    $node_object = $route_match->getParameters()->get('node');
    $node_is_fieldable = $node_object instanceof FieldableEntityInterface;

    // Make sure menus are selected, and breadcrumb text strings, are displayed
    // in the content rather than the (default) interface language:
    $this->contentLanguage = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    // Check each selected menu, in turn, until a menu or taxonomy match found:
    // then cache its state for building & caching in build() and exit.
    $menus = $this->config->get('menu_breadcrumb_menus');
    uasort($menus, function ($a, $b) {
      return SortArray::sortByWeightElement($a, $b);
    });
    foreach ($menus as $menu_name => $params) {

      // Look for current path on any enabled menu.
      if (!empty($params['enabled'])) {

        // Skip over any menu that's not in the current content language,
        // if and only if the "language handling" option set for that menu.
        // NOTE this menu option is added late, so we check its existence first.
        if (array_key_exists('langhandle', $params) && $params['langhandle']) {
          $menu_objects = $this->entityTypeManager->getStorage('menu')
            ->loadByProperties(['id' => $menu_name]);
          if ($menu_objects) {
            $menu_language = reset($menu_objects)->language()->getId();
            if ($menu_language != $this->contentLanguage &&
              $menu_language !== Language::LANGCODE_NOT_SPECIFIED &&
              $menu_language !== Language::LANGCODE_NOT_APPLICABLE) {
              continue;
            }
          }
        }

        if ($this->config->get('derived_active_trail')) {
          // Do not use the global MenuActiveTrail service because we need one
          // which is aware of the given routeMatch, not of the global one.
          $menuActiveTrail = new MenuActiveTrail($this->menuLinkManager, $route_match, $this->cacheMenu, $this->lock);
          $trail_ids = $menuActiveTrail->getActiveTrailIds($menu_name);
        }
        else {
          // Default, for the majority & compatibility with historical use and
          // other modules: use the global (injected) MenuActiveTrail service.
          $trail_ids = $this->menuActiveTrail->getActiveTrailIds($menu_name);
        }
        $trail_ids = array_filter($trail_ids);
        if ($trail_ids) {
          $this->menuName = $menu_name;
          $this->menuTrail = $trail_ids;
          $this->taxonomyAttachment = NULL;
          return TRUE;
        }
      }

      // Look for a "taxonomy attachment" by node field, regardless of language.
      if (!empty($params['taxattach']) && $node_is_fieldable) {

        // Check all taxonomy terms applying to the current page.
        foreach ($node_object->getFields() as $field) {
          if ($field->getSetting('target_type') == 'taxonomy_term') {

            // In general these entity references will support multiple
            // values so we check all terms in the order they are listed.
            foreach ($field->referencedEntities() as $term) {
              $url = $term->toUrl();
              $route_links = $this->menuLinkManager->loadLinksByRoute($url->getRouteName(), $url->getRouteParameters(), $menu_name);
              if (!empty($route_links)) {
                // Successfully found taxonomy attachment, so pass to build():
                // - the menu in which we have found the attachment
                // - the effective menu trail of the taxonomy-attached node
                // - the node itself (in build() we will find its title & URL)
                $taxonomy_term_link = reset($route_links);
                $taxonomy_term_id = $taxonomy_term_link->getPluginId();
                $this->menuName = $menu_name;
                $this->menuTrail = $this->menuLinkManager->getParentIds($taxonomy_term_id);
                $this->taxonomyAttachment = $node_object;
                return TRUE;
              }
            }
          }
        }
      }
    }
    // No more menus to check...
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    // Breadcrumbs accumulate in this array, with lowest index being the root
    // (i.e., the reverse of the assigned breadcrumb trail):
    $links = [];
    // (https://www.drupal.org/docs/develop/standards/coding-standards#array)
    //
    if ($this->languageManager->isMultilingual()) {
      $breadcrumb->addCacheContexts(['languages:language_content']);
    }

    // Changing the <front> page will invalidate any breadcrumb generated here:
    $site_config = $this->configFactory->get('system.site');
    $breadcrumb->addCacheableDependency($site_config);

    // Changing any module settings will invalidate the breadcrumb:
    $breadcrumb->addCacheableDependency($this->config);

    // Changing the active trail or URL, of either the current path or the
    // taxonomy-attached path, on this menu will invalidate this breadcrumb:
    $breadcrumb->addCacheContexts(['route.menu_active_trails:' . $this->menuName]);
    $breadcrumb->addCacheContexts(['url.path']);

    // Generate basic breadcrumb trail from active trail.
    // Keep same link ordering as Menu Breadcrumb (so also reverses menu trail)
    foreach (array_reverse($this->menuTrail) as $id) {
      $plugin = $this->menuLinkManager->createInstance($id);

      // Skip items that have an empty URL if the option is set.
      if ($this->config->get('exclude_empty_url') && empty($plugin->getUrlObject()->toString())) {
        continue;
      }

      // Skip items that are disabled in the menu if the option is set.
      if ($this->config->get('exclude_disabled_menu_items') && !$plugin->isEnabled()) {
        continue;
      }

      // Stop items when the first url matching occurs.
      if ($this->config->get('stop_on_first_match') && $plugin->getUrlObject()->toString() == Url::fromRoute('<current>')->toString()) {
        break;
      }

      $links[] = Link::fromTextAndUrl($plugin->getTitle(), $plugin->getUrlObject());
      $breadcrumb->addCacheableDependency($plugin);
      // In the last line, MenuLinkContent plugin is not providing cache tags.
      // Until this is fixed in core add the tags here:
      if ($plugin instanceof MenuLinkContent) {
        $uuid = $plugin->getDerivativeId();
        $entities = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties(['uuid' => $uuid]);
        if ($entity = reset($entities)) {
          $breadcrumb->addCacheableDependency($entity);
        }
      }
    }
    $this->addMissingCurrentPage($links, $route_match);

    // Create a breadcrumb for <front> which may be either added or replaced:
    $langcode = $this->contentLanguage;
    $label = $this->config->get('front_title') ?
      $this->configFactory->get('system.site')->get('name') :
      $this->t('Home', [], ['langcode' => $langcode]);
    // (https://www.drupal.org/docs/develop/standards/coding-standards#array)
    $home_link = Link::createFromRoute($label, '<front>');

    // The first link from the menu trail, being the root, may be the
    // <front> so first compare those two routes to see if they are identical.
    // (Though in general a link deeper in the menu could be <front>, in that
    // case it's arguable that the node-based pathname would be preferred.)
    $front_page = $site_config->get('page.front');
    $front_url = Url::fromUri("internal:$front_page");
    $first_url = $links[0]->getUrl();
    // If options are set to remove <front>, strip off that link, otherwise
    // replace it with a breadcrumb named according to option settings:
    if (($first_url->isRouted() && $front_url->isRouted()) &&
      ($front_url->getRouteName() === $first_url->getRouteName()) &&
      ($front_url->getRouteParameters() === $first_url->getRouteParameters())) {

      // According to the confusion hopefully cleared up in issue 2754521, the
      // sense of "remove_home" is slightly different than in Menu Breadcrumb:
      // we remove any match with <front> rather than replacing it.
      if ($this->config->get('remove_home')) {
        array_shift($links);
      }
      elseif ($this->config->get('front_title') != 2) {
        $links[0] = $home_link;
      }
    }
    else {
      // If trail *doesn't* begin with the home page, add it if that option set.
      if ($this->config->get('add_home')) {
        array_unshift($links, $home_link);
      }
    }

    if (!empty($links)) {
      $page_type = $this->taxonomyAttachment ? 'member_page' : 'current_page';
      // Display the last item of the breadcrumbs trail as the options indicate.
      /** @var \Drupal\Core\Link $current */
      $current = array_pop($links);
      if ($this->config->get('append_' . $page_type)) {
        if (!$this->config->get($page_type . '_as_link')) {
          $current->setUrl(new Url('<none>'));
        }
        array_push($links, $current);
      }
    }
    return $breadcrumb->setLinks($links);
  }

  /**
   * If the current page is missing from the breadcrumb links, add it.
   *
   * @param \Drupal\Core\Link[] $links
   *   The breadcrumb links.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  protected function addMissingCurrentPage(array &$links, RouteMatchInterface $route_match) {
    // Check if the current page is already present.
    if (!empty($links)) {
      $last_url = end($links)->getUrl();
      if ($last_url->isRouted() &&
        $last_url->getRouteName() === $route_match->getRouteName() &&
        $last_url->getRouteParameters() === $route_match->getRawParameters()->all()
      ) {
        // We already have a link, so no need to add one.
        return;
      }
    }

    // If we got here, the current page is missing from the breadcrumb links.
    // This can happen if the active trail is only partial, and doesn't reach
    // the current page, or if a taxonomy attachment is used.
    $route = $route_match->getRouteObject();
    $request = Request::create($route->getPath());
    // Performance optimization: set a short accept header to reduce overhead in
    // AcceptHeaderMatcher when matching the request.
    $request->headers->set('Accept', 'text/html');
    $request->attributes->add($route_match->getParameters()->all());
    $title = $this->titleResolver->getTitle($request, $route);
    if (isset($title)) {
      $links[] = Link::fromTextAndUrl($title,
        Url::fromRouteMatch($route_match));
    }
  }

  /**
   * The getter function for $menuName property.
   *
   * @return string
   *   The menu name.
   */
  public function getMenuName() {
    return $this->menuName;
  }

  /**
   * The setter function for $menuName property.
   *
   * @param string $menu_name
   *   The menu name.
   */
  public function setMenuName($menu_name) {
    $this->menuName = $menu_name;
  }

  /**
   * The getter function for $menuTrail property.
   *
   * @return string
   *   The menu trail.
   */
  public function getMenuTrail() {
    return $this->menuTrail;
  }

  /**
   * The setter function for $menuTrail property.
   *
   * @param string $menu_trail
   *   The menu trail.
   */
  public function setMenuTrail($menu_trail) {
    $this->menuTrail = $menu_trail;
  }

  /**
   * The getter function for $taxonomyAttachment property.
   *
   * @return \Drupal\node\NodeInterface
   *   The taxonomy attachment.
   */
  public function getTaxonomyAttachment() {
    return $this->taxonomyAttachment;
  }

  /**
   * The setter function for $taxonomyAttachment property.
   *
   * @param \Drupal\node\NodeInterface $taxonomy_attachment
   *   The taxonomy attachment.
   */
  public function setTaxonomyAttachment(NodeInterface $taxonomy_attachment) {
    $this->taxonomyAttachment = $taxonomy_attachment;
  }

  /**
   * The getter function for $contentLanguage property.
   *
   * @return string
   *   The content language.
   */
  public function getContentLanguage() {
    return $this->contentLanguage;
  }

  /**
   * The setter function for $contentLanguage property.
   *
   * @param string $contentLanguage
   *   The content language.
   */
  public function setContentLanguage($contentLanguage) {
    $this->contentLanguage = $contentLanguage;
  }

}
