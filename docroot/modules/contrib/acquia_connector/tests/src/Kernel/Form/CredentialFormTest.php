<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\Form;

use Drupal\Core\Url;
use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\acquia_connector\Form\CredentialForm
 * @group acquia_connector
 */
final class CredentialFormTest extends AcquiaConnectorTestBase {

  /**
   * Tests the form submission data.
   *
   * @param string $identifier
   *   The network identifier.
   * @param string $key
   *   The network key.
   * @param string $application_uuid
   *   The application UUID.
   *
   * @note Without OAuth credentials, the form passes as long as we have a UUID.
   * @todo can this form provide more validation on the provided input?
   *
   * @dataProvider credentialData
   */
  public function testForm(string $identifier, string $key, string $application_uuid): void {
    $this->createUserWithSession();
    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_manual')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(200, $response->getStatusCode());

    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_manual')->toString(),
      'POST',
      [
        'identifier' => $identifier,
        'key' => $key,
        'application_uuid' => $application_uuid,
        // @phpstan-ignore-next-line
        'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
        // @phpstan-ignore-next-line
        'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
        // @phpstan-ignore-next-line
        'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
        'op' => 'Connect',
      ]);
    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode(), var_export($response->getContent(), TRUE));
    self::assertEquals(
      Url::fromRoute('acquia_connector.settings')->setAbsolute()->toString(),
      $response->headers->get('Location')
    );
    $actual_msg = $this->container->get('messenger')->all()['status'];
    self::assertSame(
      ['status' => '<h3>Connection successful!</h3>You are now connected to Acquia Cloud. Please enter a name for your site to begin sending profile data.'],
      ['status' => (string) array_shift($actual_msg)]
    );
  }

  /**
   * The test data.
   *
   * @return \Generator
   *   The data.
   */
  public static function credentialData() {
    yield ['ABC', 'CDE', 'a47ac10b-58cc-4372-a567-0e02b2c3d470'];
  }

}
