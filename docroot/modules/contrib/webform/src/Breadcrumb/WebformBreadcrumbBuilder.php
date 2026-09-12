<?php

namespace Drupal\webform\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformRequestInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a webform breadcrumb builder.
 */
class WebformBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The current route's entity or plugin type.
   *
   * @var string
   */
  protected $type;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected $requestHandler;

  /**
   * Constructs a WebformBreadcrumbBuilder object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\webform\WebformRequestInterface $request_handler
   *   The webform request handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, WebformRequestInterface $request_handler, TranslationInterface $string_translation, ?ConfigFactoryInterface $config_factory = NULL) {
    $this->moduleHandler = $module_handler;
    $this->requestHandler = $request_handler;
    $this->setStringTranslation($string_translation);
    $this->configFactory = $config_factory ?: \Drupal::configFactory();
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    // @todo Remove null safe operator after Drupal 12.0.0 becomes the minimum
    //   requirement, see https://www.drupal.org/project/drupal/issues/3459277.
    $cacheable_metadata?->addCacheContexts(['route']);

    $route_name = $route_match->getRouteName();
    // All routes must begin or contain 'webform.
    if (!$route_name || !str_contains($route_name, 'webform')) {
      return FALSE;
    }

    $args = explode('.', $route_name);

    // Skip all config_translation routes except the overview
    // and allow Drupal to use the path as the breadcrumb.
    $config_translation_route = str_contains($route_name, 'config_translation');
    $webform_config_translation_route = !in_array(
      $route_name,
      [
        'entity.webform.config_translation_overview',
        'config_translation.item.overview.webform.config',
        'config_translation.item.add.webform.config',
        'config_translation.item.edit.webform.config',
        'config_translation.item.delete.webform.config',
      ]
    );
    if ($config_translation_route && $webform_config_translation_route) {
      return FALSE;
    }
    try {
      $path = Url::fromRouteMatch($route_match)->toString();
    }
    catch (\Exception $exception) {
      $path = '';
    }

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = ($route_match->getParameter('webform') instanceof WebformInterface) ? $route_match->getParameter('webform') : NULL;

    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = ($route_match->getParameter('webform_submission') instanceof WebformSubmissionInterface) ? $route_match->getParameter('webform_submission') : NULL;

    if ((count($args) > 2) && $args[0] === 'entity' && ($args[2] === 'webform' || $args[2] === 'webform_submission')) {
      $this->type = 'webform_source_entity';
    }
    elseif ($route_name === 'webform.reports_plugins.elements.test') {
      $this->type = 'webform_plugins_elements';
    }
    elseif (str_starts_with($route_name, 'webform.help.')) {
      $this->type = 'webform_help';
    }
    elseif (str_starts_with($route_name, 'entity.webform_ui.element')) {
      $this->type = 'webform_element';
    }
    elseif (str_starts_with($route_name, 'entity.webform.handler.')) {
      $this->type = 'webform_handler';
    }
    elseif (str_starts_with($route_name, 'entity.webform.variant.')) {
      $this->type = 'webform_variant';
    }
    elseif ($webform_submission && str_contains($route_name, '.webform.user.submission')) {
      $this->type = 'webform_user_submission';
    }
    elseif (str_contains($route_name, '.webform.user.submissions')) {
      $this->type = 'webform_user_submissions';
    }
    elseif (str_contains($route_name, '.webform.user.drafts')) {
      $this->type = 'webform_user_drafts';
    }
    elseif ($webform_submission && $webform_submission->access('admin')) {
      $this->type = 'webform_submission';
    }
    elseif ($webform && $webform->access('admin')) {
      $this->type = ($webform->isTemplate() && $this->moduleHandler->moduleExists('webform_templates')) ? 'webform_template' : 'webform';
    }
    elseif (str_contains($path, 'admin/structure/webform/test/')) {
      $this->type = 'webform_test';
    }
    elseif (str_contains($path, 'admin/structure/webform/options/')) {
      $this->type = 'webform_options';
    }
    elseif (str_contains($path, 'admin/structure/webform/config/')) {
      $this->type = 'webform_config';
    }
    else {
      $this->type = NULL;
    }

    return ($this->type) ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $route_name = $route_match->getRouteName();

    if ($this->type === 'webform_source_entity') {
      $source_entity = $this->requestHandler->getCurrentSourceEntity(['webform', 'webform_submission']);
      $entity_type = $source_entity->getEntityTypeId();
      $entity_id = $source_entity->id();

      $breadcrumb = new Breadcrumb();
      $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
      $breadcrumb->addLink($source_entity->toLink());
      if ($webform_submission = $route_match->getParameter('webform_submission')) {
        if (str_contains($route_match->getRouteName(), 'webform.user.submission')) {
          $breadcrumb->addLink(Link::createFromRoute($this->t('Submissions'), "entity.$entity_type.webform.user.submissions", [$entity_type => $entity_id]));
        }
        elseif ($source_entity->access('webform_submission_view') || $webform_submission->access('view_any')) {
          $breadcrumb->addLink(Link::createFromRoute($this->t('Results'), "entity.$entity_type.webform.results_submissions", [$entity_type => $entity_id]));
        }
        elseif ($webform_submission->access('view_own')) {
          $breadcrumb->addLink(Link::createFromRoute($this->t('Results'), "entity.$entity_type.webform.user.submissions", [$entity_type => $entity_id]));
        }
      }
    }
    elseif ($this->type === 'webform_help') {
      $breadcrumb = new Breadcrumb();
      $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
      $breadcrumb->addLink(Link::createFromRoute($this->t('Administration'), 'system.admin'));
      $breadcrumb->addLink(Link::createFromRoute($this->t('Help'), 'help.main'));
      $breadcrumb->addLink(Link::createFromRoute($this->t('Webform', [], ['context' => 'module']), 'help.page', ['name' => 'webform']));
    }
    elseif ($this->type === 'webform_plugins_elements') {
      $breadcrumb = new Breadcrumb();
      $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
      $breadcrumb->addLink(Link::createFromRoute($this->t('Administration'), 'system.admin'));
      $breadcrumb->addLink(Link::createFromRoute($this->t('Reports'), 'system.admin_reports'));
      $breadcrumb->addLink(Link::createFromRoute($this->t('Elements'), 'webform.reports_plugins.elements'));
    }
    else {
      $breadcrumb = new Breadcrumb();
      $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
      $breadcrumb->addLink(Link::createFromRoute($this->t('Administration'), 'system.admin'));
      if (!$this->configFactory->get('webform.settings')->get('ui.toolbar_item')) {
        $breadcrumb->addLink(Link::createFromRoute($this->t('Structure'), 'system.admin_structure'));
      }
      $breadcrumb->addLink(Link::createFromRoute($this->t('Webforms'), 'entity.webform.collection'));
      switch ($this->type) {
        case 'webform_config':
          $breadcrumb->addLink(Link::createFromRoute($this->t('Configuration'), 'webform.config'));
          if (str_starts_with($route_name, 'config_translation.item.') && $route_name !== 'config_translation.item.overview.webform.config') {
            $breadcrumb->addLink(Link::createFromRoute($this->t('Translate'), 'config_translation.item.overview.webform.config'));
          }
          break;

        case 'webform_options':
          if ($route_name !== 'entity.webform_options.collection') {
            $breadcrumb->addLink(Link::createFromRoute($this->t('Options'), 'entity.webform_options.collection'));
          }
          if (str_starts_with($route_name, 'entity.webform_image_select_images')) {
            // @see webform_image_select.module.
            if ($route_name !== 'entity.webform_image_select_images.collection') {
              $breadcrumb->addLink(Link::createFromRoute($this->t('Images'), 'entity.webform_image_select_images.collection'));
            }
          }
          elseif (str_starts_with($route_name, 'entity.webform_options_custom')) {
            // @see webform_custom_options.module.
            if ($route_name !== 'entity.webform_options_custom.collection') {
              $breadcrumb->addLink(Link::createFromRoute($this->t('Custom'), 'entity.webform_options_custom.collection'));
            }
          }
          break;

        case 'webform_test':
          $breadcrumb->addLink(Link::createFromRoute($this->t('Testing'), 'webform_test.index'));
          break;

        case 'webform_template':
          $breadcrumb->addLink(Link::createFromRoute($this->t('Templates'), 'entity.webform.templates'));
          break;

        case 'webform_element':
          /** @var \Drupal\webform\WebformInterface $webform */
          $webform = $route_match->getParameter('webform');
          $breadcrumb->addLink(Link::createFromRoute($webform->label(), 'entity.webform.canonical', ['webform' => $webform->id()]));
          $breadcrumb->addLink(Link::createFromRoute($this->t('Elements'), 'entity.webform.edit_form', ['webform' => $webform->id()]));
          break;

        case 'webform_handler':
          /** @var \Drupal\webform\WebformInterface $webform */
          $webform = $route_match->getParameter('webform');
          $breadcrumb->addLink(Link::createFromRoute($webform->label(), 'entity.webform.canonical', ['webform' => $webform->id()]));
          $breadcrumb->addLink(Link::createFromRoute($this->t('Emails / Handlers'), 'entity.webform.handlers', ['webform' => $webform->id()]));
          break;

        case 'webform_variant':
          /** @var \Drupal\webform\WebformInterface $webform */
          $webform = $route_match->getParameter('webform');
          $breadcrumb->addLink(Link::createFromRoute($webform->label(), 'entity.webform.canonical', ['webform' => $webform->id()]));
          $breadcrumb->addLink(Link::createFromRoute($this->t('Variants'), 'entity.webform.variants', ['webform' => $webform->id()]));
          break;

        case 'webform_submission':
          /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
          $webform_submission = $route_match->getParameter('webform_submission');
          $webform = $webform_submission->getWebform();
          $breadcrumb->addLink(Link::createFromRoute($webform->label(), 'entity.webform.canonical', ['webform' => $webform->id()]));
          $breadcrumb->addLink(Link::createFromRoute($this->t('Results'), 'entity.webform.results_submissions', ['webform' => $webform->id()]));
          break;

        case 'webform_user_submissions':
        case 'webform_user_drafts':
          /** @var \Drupal\webform\WebformInterface $webform */
          $webform = $route_match->getParameter('webform');
          $breadcrumb = new Breadcrumb();
          $breadcrumb->addLink(Link::createFromRoute($webform->label(), 'entity.webform.canonical', ['webform' => $webform->id()]));
          break;

        case 'webform_user_submission':
          /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
          $webform_submission = $route_match->getParameter('webform_submission');
          $webform = $webform_submission->getWebform();
          $breadcrumb = new Breadcrumb();
          $breadcrumb->addLink(Link::createFromRoute($webform->label(), 'entity.webform.canonical', ['webform' => $webform->id()]));
          if ($webform_submission->access('view_own')) {
            $breadcrumb->addLink(Link::createFromRoute($this->t('Submissions'), 'entity.webform.user.submissions', ['webform' => $webform->id()]));
          }
          break;
      }
    }

    // This breadcrumb builder is based on a route parameter, and hence it
    // depends on the 'route' cache context.
    // @todo Remove after Drupal 12.0.0 becomes the minimum requirement,
    //   see https://www.drupal.org/project/drupal/issues/3459277.
    $breadcrumb->addCacheContexts(['route']);

    return $breadcrumb;
  }

  /**
   * Get the type of webform breadcrumb.
   *
   * @return string
   *   The type of webform breadcrumb.
   */
  public function getType() {
    return $this->type;
  }

}
