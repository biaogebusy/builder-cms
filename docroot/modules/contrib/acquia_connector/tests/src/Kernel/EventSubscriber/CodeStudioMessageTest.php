<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\EventSubscriber;

use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @coversDefaultClass \Drupal\acquia_connector\EventSubscriber\KernelView\CodeStudioMessage
 * @group acquia_connector
 */
final class CodeStudioMessageTest extends AcquiaConnectorTestBase {

  /**
   * Tests when node CDE environment.
   */
  public function testNonCdeEnvironment(): void {
    $this->putEnv('AH_SITE_ENVIRONMENT', 'foo');
    $sut = $this->container->get('acquia_connector.kernel_view.codestudio_message');
    $sut->onViewRenderArray(new RequestEvent(
      $this->container->get('http_kernel'),
      Request::createFromGlobals(),
      1,
    ));
    $messenger = $this->container->get('messenger');
    self::assertEquals([], $messenger->all());
  }

  /**
   * Tests CDE environment message.
   *
   * @param string $ah_site_env
   *   The site env.
   * @param string $project_id
   *   The project ID.
   * @param string $request_iid
   *   The request IID.
   * @param string $project_path
   *   The project path.
   * @param string $server_url
   *   The server URL.
   * @param string $expected_message
   *   The expected message.
   *
   * @dataProvider environmentData
   */
  public function testCdeEnvironment(string $ah_site_env, string $project_id, string $request_iid, string $project_path, string $server_url, string $expected_message): void {
    $this->putEnv('AH_SITE_ENVIRONMENT', $ah_site_env);
    $this->putEnv('CODE_STUDIO_CI_PROJECT_ID', $project_id);
    $this->putEnv('CODE_STUDIO_CI_MERGE_REQUEST_IID', $request_iid);
    $this->putEnv('CODE_STUDIO_CI_PROJECT_PATH', $project_path);
    $this->putEnv('CODE_STUDIO_CI_SERVER_URL', $server_url);

    $sut = $this->container->get('acquia_connector.kernel_view.codestudio_message');
    $sut->onViewRenderArray(new RequestEvent(
      $this->container->get('http_kernel'),
      Request::createFromGlobals(),
      1
    ));
    $messenger = $this->container->get('messenger');
    if ($expected_message === '') {
      self::assertEquals([], $messenger->all());
    }
    else {
      $actual_msg = $messenger->all()['status'];
      self::assertSame(['status' => $expected_message], ['status' => (string) array_shift($actual_msg)]);
    }
  }

  /**
   * Test environment data for subscriptions.
   *
   * @return iterable
   *   The test data.
   */
  public static function environmentData(): iterable {
    yield 'not ah' => [
      '',
      '',
      '',
      '',
      '',
      '',
    ];
    yield 'standard ah' => [
      'prod',
      '',
      '',
      '',
      '',
      '',
    ];
    yield 'CDE missing vars' => [
      'ode1232',
      '',
      '',
      '',
      '',
      '',
    ];
    yield 'CDE ok' => [
      'ode1232',
      'SAMPLE_PROJECT_ID',
      'ABC123',
      'path/to/project',
      'https://ci_server_url',
      'This Acquia Continuous Delivery Environment (CDE) was automatically created by <a href="https://ci_server_url" target="_blank">Acquia Code Studio</a> for merge request <a href="https://ci_server_url/path/to/project/-/merge_requests/ABC123" target="_blank">!ABC123</a> for <a href="https://ci_server_url/path/to/project" target"_blank">path/to/project</a>. It will be destroyed when the merge request is closed or merged.',
    ];
  }

}
