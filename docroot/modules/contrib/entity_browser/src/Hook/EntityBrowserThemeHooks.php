<?php

namespace Drupal\entity_browser\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Theme\ThemePreprocess;

/**
 * Theme and preprocess hook implementations for entity_browser.
 */
class EntityBrowserThemeHooks {

  /**
   * Constructs a new EntityBrowserThemeHooks object.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ThemeManagerInterface $themeManager,
  ) {}

  /**
   * Implements hook_theme().
   *
   * Overrides the core html theme to use a custom template for iframes.
   */
  #[Hook('theme')]
  public function theme() {
    // The procedural template_preprocess_html()/template_preprocess_page()
    // functions were converted to methods on the ThemePreprocess service and
    // registered as "initial preprocess" in drupal:11.2.0; they are removed
    // entirely in drupal:12.0.0. Register the service methods when available
    // and fall back to the procedural functions on older core.
    // @todo Remove the fallback once we require at least drupal:11.2.0.
    $has_service_preprocess = method_exists(ThemePreprocess::class, 'preprocessHtml');

    $definitions = [
      'html__entity_browser__iframe' => [
        'template' => 'html--entity-browser--iframe',
        'render element' => 'html',
      ],
      'html__entity_browser__modal' => [
        'template' => 'html--entity-browser--iframe',
        'render element' => 'html',
      ],
      'page__entity_browser__iframe' => [
        'template' => 'page--entity-browser--iframe',
        'render element' => 'html',
      ],
      'page__entity_browser__modal' => [
        'template' => 'page--entity-browser--iframe',
        'render element' => 'html',
      ],
    ];

    foreach ($definitions as $hook => &$definition) {
      $is_page = str_starts_with($hook, 'page__');
      if ($has_service_preprocess) {
        $definition['initial preprocess'] = $is_page
          ? ThemePreprocess::class . ':preprocessPage'
          : ThemePreprocess::class . ':preprocessHtml';
      }
      else {
        $definition['preprocess functions'] = [
          $is_page ? 'template_preprocess_page' : 'template_preprocess_html',
        ];
      }
    }

    return $definitions;
  }

  /**
   * Implements hook_preprocess_page__entity_browser__iframe().
   *
   * Also handles hook_preprocess_page__entity_browser__modal(): both templates
   * need the messages block figured out and displayed separately.
   */
  #[Hook('preprocess_page__entity_browser__iframe')]
  #[Hook('preprocess_page__entity_browser__modal')]
  public function preprocessPageEntityBrowser(&$variables) {
    if (!$this->moduleHandler->moduleExists('block')) {
      return;
    }
    $variables['messages'] = '';
    $blocks = $this->entityTypeManager->getStorage('block')->loadByProperties([
      'theme' => $this->themeManager->getActiveTheme()->getName(),
      'plugin' => 'system_messages_block',
    ]);
    if (($messages = current($blocks)) && !empty($variables['page'][$messages->getRegion()][$messages->id()])) {
      $variables['messages'] = $variables['page'][$messages->getRegion()][$messages->id()];
    }
  }

}
