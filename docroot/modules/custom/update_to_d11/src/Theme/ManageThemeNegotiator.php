<?php

declare(strict_types=1);

namespace Drupal\update_to_d11\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * 替代 switch_page_theme（不支持 D11）的轻量主题协商器。
 *
 * 规则存储于 update_to_d11.settings 的 switch_page_theme_rules 键，
 * 由 drush update-to-d11:switch-page-theme-replace 从原模块配置迁移。
 * 仅实现路径匹配（站点原配置的角色/语言条件均为"不过滤"，未迁移）。
 */
class ManageThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * applies() 阶段匹配到的主题，供 determineActiveTheme() 使用。
   */
  protected ?string $activeTheme = NULL;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected CurrentPathStack $currentPath,
    protected AliasManagerInterface $pathAlias,
    protected PathMatcherInterface $pathMatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $rules = $this->configFactory->get('update_to_d11.settings')->get('switch_page_theme_rules') ?? [];
    $current_path = $this->currentPath->getPath();
    $alias = $this->pathAlias->getAliasByPath($current_path);
    foreach ($rules as $rule) {
      if (($rule['status'] ?? 0) == 1 && !empty($rule['pages']) && !empty($rule['theme'])) {
        if ($this->pathMatcher->matchPath($current_path, $rule['pages']) || $this->pathMatcher->matchPath($alias, $rule['pages'])) {
          $this->activeTheme = $rule['theme'];
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return $this->activeTheme;
  }

}
