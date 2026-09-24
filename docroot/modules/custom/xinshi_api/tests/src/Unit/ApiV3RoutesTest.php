<?php

namespace Drupal\Tests\xinshi_api\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the api/v3 endpoints stay controller routes the builder can call.
 *
 * As REST resources they required the generated "restful get ..." permission,
 * which no role held, so every endpoint answered 403.
 */
final class ApiV3RoutesTest extends TestCase {

  private const ENDPOINTS = [
    'xinshi_api.v3.accountProfile' => [
      '/api/v3/accountProfile',
      ['_user_is_logged_in' => 'TRUE'],
    ],
    'xinshi_api.v3.comment' => [
      '/api/v3/comment/{comment_type}/{entity_uuid}',
      ['_permission' => 'access comments'],
    ],
    'xinshi_api.v3.landingPage' => [
      '/api/v3/landingPage',
      ['_permission' => 'access content'],
    ],
    'xinshi_api.v3.node.component' => [
      '/api/v3/node/component',
      ['_permission' => 'access content'],
    ],
    'xinshi_api.v3.webform' => [
      '/api/v3/webform',
      ['_user_is_logged_in' => 'TRUE'],
    ],
    'xinshi_api.v3.statistics.node.published' => [
      '/api/v3/statistics/node/published',
      ['_access' => 'TRUE'],
    ],
    'xinshi_api.v3.statistics.user.register' => [
      '/api/v3/statistics/user/register',
      ['_access' => 'TRUE'],
    ],
  ];

  private const REST_PERMISSIONS = [
    'restful get xinshi_api_account_rest',
    'restful get xinshi_api_comment_rest',
    'restful get xinshi_api_landing_page_rest',
    'restful get xinshi_api_node_component_rest',
    'restful get xinshi_api_statistics_node_rest',
    'restful get xinshi_api_statistics_register_rest',
    'restful get xinshi_api_webform_rest',
  ];

  private function modulePath(): string {
    return dirname(__DIR__, 3);
  }

  public function testEndpointsRouteToControllersWithoutRestPermission(): void {
    $routes = Yaml::parseFile($this->modulePath() . '/xinshi_api.routing.yml');
    foreach (self::ENDPOINTS as $route => [$path, $requirements]) {
      $this->assertArrayHasKey($route, $routes);
      $definition = $routes[$route];
      $this->assertSame($path, $definition['path'], $route);
      $this->assertSame(['GET'], $definition['methods'], $route);
      $this->assertSame(['oauth2', 'cookie'], array_values($definition['options']['_auth']), $route);
      $this->assertStringStartsWith('\\Drupal\\xinshi_api\\Controller\\', $definition['defaults']['_controller'], $route);
      foreach ($requirements as $requirement => $value) {
        $this->assertSame($value, $definition['requirements'][$requirement] ?? NULL, $route);
      }
      foreach ($definition['requirements'] as $value) {
        // A generated REST permission is the regression this guards against.
        $this->assertFalse(str_starts_with((string) $value, 'restful '), "$route: $value");
      }
    }
  }

  public function testRestResourcePluginsAndConfigsAreGone(): void {
    $this->assertDirectoryDoesNotExist($this->modulePath() . '/src/Plugin/rest/resource');
    $this->assertSame([], glob($this->modulePath() . '/config/install/rest.resource.xinshi_api_*.yml'));
  }

  public function testUpdateHookDropsEveryRestResourceConfig(): void {
    $install = file_get_contents($this->modulePath() . '/xinshi_api.install');
    $this->assertStringContainsString("substr(\$permission, strlen('restful get '))", $install);
    foreach (self::REST_PERMISSIONS as $permission) {
      $this->assertStringContainsString("'$permission'", $install);
    }
  }

}
