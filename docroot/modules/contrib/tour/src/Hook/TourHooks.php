<?php

declare(strict_types=1);

namespace Drupal\tour\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\tour\Entity\Tour;
use Drupal\tour\RenderCallbacks;
use Drupal\tour\TipPluginManager;
use Drupal\tour\TourHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for tour module.
 */
class TourHooks {

  use StringTranslationTrait;

  public function __construct(
    protected AccountProxyInterface $currentUser,
    #[Autowire(service: 'tour.helper')]
    protected TourHelper $tourHelper,
    protected ElementInfoManagerInterface $elementInfoManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'plugin.manager.tour.tip')]
    protected TipPluginManager $tipPluginManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ModuleExtensionList $moduleExtensionList,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    if ($route_name == 'help.page.tour') {
      $output = '<h2>' . $this->t('About') . '</h2>';
      $output .= '<p>' . $this->t("The Tour module provides users with guided tours of the site interface. Each tour consists of several tips that highlight elements of the user interface, guide the user through a workflow, or explain key concepts of the website. For more information, see the <a href=':tour'>online documentation for the Tour module</a>.", [':tour' => 'https://www.drupal.org/documentation/modules/tour']) . '</p>';
      $output .= '<h2>' . $this->t('Uses') . '</h2>';
      $output .= '<dl>';
      $output .= '<dt>' . $this->t('Viewing tours') . '</dt>';
      $output .= '<dd>' . $this->t("If a tour is available on a page, a <em>Tour</em> button will be visible in the toolbar. If you click this button the first tip of the tour will appear. The tour continues after clicking the <em>Next</em> button in the tip. To see a tour users must have the permission <em>Access tour</em> and JavaScript must be enabled in the browser") . '</dd>';
      $output .= '<dt>' . $this->t('Creating tours') . '</dt>';
      $output .= '</dl>';
      return $output;
    }
    return '';
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'tour_listing_table' => [
        'variables' => [
          'headers' => NULL,
          'rows' => NULL,
          'empty' => NULL,
          'attributes' => [],
        ],
      ],
      'tour_recap' => [
        'variables' => [
          'tour' => NULL,
          'tips' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_toolbar().
   */
  #[Hook('toolbar')]
  public function toolbar(): array {
    $items = [];
    $items['tour'] = [
      '#cache' => [
        'contexts' => [
          'user.permissions',
        ],
      ],
    ];

    if (!$this->currentUser->hasPermission('access tour')) {
      return $items;
    }

    $results = $this->tourHelper->loadTourEntities();
    $no_tips = empty($results);

    if (!$this->tourHelper->shouldEmptyBeHidden($no_tips)) {
      $items['tour'] += [
        '#type' => 'toolbar_item',
        'tab' => [
          '#lazy_builder' => [
            'tour.lazy_builders:renderTour',
            [$no_tips],
          ],
          '#cache' => ['contexts' => ['url']],
        ],
        '#wrapper_attributes' => [
          'class' => ['tour-toolbar-tab'],
        ],
        '#attached' => [
          'library' => [
            'tour/tour',
          ],
        ],
      ];

      // \Drupal\toolbar\Element\ToolbarItem::preRenderToolbarItem adds an
      // #attributes property to each toolbar item's tab child automatically.
      // Lazy builders don't support an #attributes property, so we need to
      // add another render callback to remove the #attributes property. We
      // start by adding the defaults, and then we append our own pre-render
      // callback.
      $items['tour'] += $this->elementInfoManager->getInfo('toolbar_item');
      $items['tour']['#pre_render'][] = [
        RenderCallbacks::class,
        'removeTabAttributes',
      ];
    }
    return $items;
  }

  /**
   * Implements hook_page_bottom().
   */
  #[Hook('page_bottom')]
  public function pageBottom(array &$page_bottom): void {
    if (!$this->currentUser->hasPermission('access tour')) {
      return;
    }

    $results = $this->tourHelper->loadTourEntities();

    if (!empty($results)) {
      $tours = Tour::loadMultiple($results);

      if (!empty($tours)) {
        $page_bottom['tour'] = $this->entityTypeManager
          ->getViewBuilder('tour')
          ->viewMultiple($tours);
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for tour entities.
   */
  #[Hook('tour_insert')]
  public function tourInsert(): void {
    $this->tipPluginManager->clearCachedDefinitions();
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for tour entities.
   */
  #[Hook('tour_update')]
  public function tourUpdate(): void {
    $this->tipPluginManager->clearCachedDefinitions();
  }

  /**
   * Implements hook_tour_tips_alter().
   */
  #[Hook('tour_tips_alter')]
  public function tourTipsAlter(array $tour_tips, EntityInterface $entity): void {
    if ($this->moduleHandler->moduleExists('language')) {
      foreach ($tour_tips as $tour_tip) {
        if ($tour_tip->get('id') == 'language-overview') {
          if ($this->moduleHandler->moduleExists('locale')) {
            $additional_overview = $this->t("This page also provides an overview of how much of the site's interface has been translated for each configured language.");
          }
          else {
            $additional_overview = $this->t("If the Interface Translation module is installed, this page will provide an overview of how much of the site's interface has been translated for each configured language.");
          }
          $tour_tip->set('body', $tour_tip->get('body') . '<br>' . $additional_overview);
        }
        elseif ($tour_tip->get('id') == 'language-continue') {
          $additional_continue = '';
          $additional_modules = [];
          if (!$this->moduleHandler->moduleExists('locale')) {
            $additional_modules[] = $this->moduleExtensionList->getName('locale');
          }
          if (!$this->moduleHandler->moduleExists('content_translation')) {
            $additional_modules[] = $this->moduleExtensionList->getName('content_translation');
          }
          if (!empty($additional_modules)) {
            $additional_continue = $this->t('Depending on your site features, additional modules that you might want to install are:') . '<ul>';
            foreach ($additional_modules as $additional_module) {
              $additional_continue .= '<li>' . $additional_module . '</li>';
            }
            $additional_continue .= '</ul>';
          }
          if (!empty($additional_continue)) {
            $tour_tip->set('body', $tour_tip->get('body') . '<br>' . $additional_continue);
          }
        }
      }
    }

    if ($this->moduleHandler->moduleExists('dblog')) {
      foreach ($tour_tips as $tour_tip) {
        if ($tour_tip->get('id') == 'dblog-more-information') {
          $body = $tour_tip->get('body');
          // Tips can reference the help page conditionally, only create the
          // link if the help module is enabled, else remove it.
          if ($this->moduleHandler->moduleExists('help')) {
            $body = str_replace('[help.page.dblog]', '<a href="' . Url::fromRoute('help.page', ['name' => 'dblog'])->toString() . '">the help text</a> and ', $body);
          }
          else {
            $body = str_replace('[help.page.dblog]', '', $body);
          }
          $tour_tip->set('body', $body);
        }
      }
    }
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules): void {
    if (in_array('navigation', $modules)) {
      $this->messenger->addWarning($this->t('Currently, <em>Tour</em> module does not have integration with <em>Navigation</em>, recommend following <a href="https://www.drupal.org/project/tour/issues/3489075">https://www.drupal.org/project/tour/issues/3489075</a> for when that does. For now use the Tour block.'));
    }
  }

}
