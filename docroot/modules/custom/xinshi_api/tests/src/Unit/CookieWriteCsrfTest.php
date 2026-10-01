<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityAccessCheck;
use Drupal\Core\PrivateKey;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\MetadataBag;
use Drupal\Core\Session\SessionConfiguration;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\user\Access\LoginStatusCheck;
use Drupal\user\Access\PermissionAccessCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * Exercises cookie-write route requirements with the locked core CSRF checker.
 *
 * No site, OAuth resource server, or controller persistence is bootstrapped here.
 */
final class CookieWriteCsrfTest extends TestCase {

  private static function routes(): array {
    $routes = [];
    foreach (glob(dirname(__DIR__, 4) . '/*/*.routing.yml') as $file) {
      $routes += Yaml::parseFile($file);
    }
    return $routes;
  }

  public static function protectedRoutes(): iterable {
    foreach (self::routes() as $name => $definition) {
      if (in_array('cookie', $definition['options']['_auth'] ?? [], TRUE)
        && array_diff($definition['methods'] ?? [], ['GET', 'HEAD', 'OPTIONS', 'TRACE'])) {
        yield $name => [$name, $definition];
      }
    }
  }

  #[DataProvider('protectedRoutes')]
  public function testCookieWriteProtection(string $name, array $definition): void {
    $route = new Route($definition['path'], $definition['defaults'], $definition['requirements']);
    $route->setMethods($definition['methods']);
    $session = new class extends SessionConfiguration {

      protected function drupalValidTestUa() {
        return FALSE;
      }

    };
    $settings = new Settings(['hash_salt' => 'isolated-test-salt']);
    $metadata = new MetadataBag($settings);
    $metadata->setCsrfTokenSeed('isolated-browser-session');
    $key = $this->createMock(PrivateKey::class);
    $key->method('get')->willReturn('isolated-test-key');
    $generator = new CsrfTokenGenerator($key, $metadata);
    $checker = new CsrfRequestHeaderAccessCheck($session, $generator);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);

    $this->assertSame(['oauth2', 'cookie'], $definition['options']['_auth'], $name);
    $this->assertSame('TRUE', $route->getRequirement('_csrf_request_header_token'), $name);
    $this->assertTrue($checker->applies($route), $name);
    $this->assertTrue(isset($definition['requirements']['_permission'])
      || isset($definition['requirements']['_entity_access'])
      || isset($definition['requirements']['_user_is_logged_in']), $name);

    $request = Request::create('https://cms.example.test' . $route->getPath(), $definition['methods'][0]);
    $cookie_name = $session->getOptions($request)['name'];
    $request->cookies->set($cookie_name, 'test-session');
    $this->assertTrue($checker->access($request, $account)->isForbidden(), "$name: missing token");
    $request->headers->set('X-CSRF-Token', 'wrong');
    $this->assertTrue($checker->access($request, $account)->isForbidden(), "$name: wrong token");
    $request->headers->set('X-CSRF-Token', $generator->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY));
    $this->assertTrue($checker->access($request, $account)->isAllowed(), "$name: valid cookie token");
    $metadata->setCsrfTokenSeed('another-browser-session');
    $this->assertTrue($checker->access($request, $account)->isForbidden(), "$name: other session token");

    // Authentication is assumed successful; a Bearer header alone is not proof.
    $request->headers->set('Authorization', 'Bearer isolated-fixture');
    $request->headers->remove('X-CSRF-Token');
    $this->assertTrue($checker->access($request, $account)->isForbidden(), "$name: bearer plus session");
    $request->headers->set('X-CSRF-Token', $generator->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY));
    $this->assertTrue($checker->access($request, $account)->isAllowed(), "$name: bearer plus valid token");
    $request->headers->remove('X-CSRF-Token');
    $request->cookies->remove($cookie_name);
    $request->cookies->set('frontend_oauth_storage', 'not-a-drupal-session');
    $this->assertTrue($checker->access($request, $account)->isAllowed(), "$name: bearer without session");
    $this->assertSame(0, $checker->access($request, $account)->getCacheMaxAge());

    // Passing CSRF must not override route-level permission/entity/login checks.
    $request->cookies->set($cookie_name, 'test-session');
    $request->headers->set('X-CSRF-Token', $generator->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY));
    foreach ([FALSE, TRUE] as $authenticated) {
      $denied_account = $this->createMock(AccountInterface::class);
      $denied_account->method('isAuthenticated')->willReturn($authenticated);
      $denied_account->method('hasPermission')->willReturn(FALSE);
      if ($route->hasRequirement('_permission')) {
        $business_access = (new PermissionAccessCheck())->access($route, $denied_account);
      }
      elseif ($route->hasRequirement('_entity_access')) {
        $node = $this->createMock(NodeInterface::class);
        $node->expects($this->once())->method('access')->with('update', $denied_account, TRUE)
          ->willReturn(AccessResult::forbidden());
        $business_access = (new EntityAccessCheck())->access($route, new RouteMatch($name, $route, ['node' => $node]), $denied_account);
      }
      elseif (!$authenticated) {
        $business_access = (new LoginStatusCheck())->access($denied_account, $route);
      }
      else {
        continue;
      }
      $this->assertFalse($checker->access($request, $denied_account)->andIf($business_access)->isAllowed(), "$name: business access denied");
    }
  }

  public function testInventoryAndSeparateAuthenticationBoundaries(): void {
    $this->assertCount(15, iterator_to_array(self::protectedRoutes()));
    $routes = self::routes();
    foreach (['xinshi_ai.figma', 'xinshi_ai.figma.assets', 'xinshi_ai.product_documents.mcp', 'xinshi_ai.conversation.update', 'xinshi_linux_do.session.logout'] as $name) {
      $this->assertSame(['oauth2'], $routes[$name]['options']['_auth'], $name);
      $this->assertSame('TRUE', $routes[$name]['requirements']['_user_is_logged_in'], $name);
      $this->assertArrayNotHasKey('_csrf_request_header_token', $routes[$name]['requirements'], $name);
    }
    $this->assertSame(['_access' => 'TRUE'], $routes['xinshi_ai_usage.ingest']['requirements']);
    $this->assertStringEndsWith('::accessByTicket', $routes['xinshi_ai.image_job.events']['requirements']['_custom_access']);
    foreach ($routes as $name => $route) {
      if (isset($route['methods']) && !array_diff($route['methods'], ['GET', 'HEAD', 'OPTIONS', 'TRACE'])) {
        $this->assertArrayNotHasKey('_csrf_request_header_token', $route['requirements'], $name);
      }
    }
  }

}
