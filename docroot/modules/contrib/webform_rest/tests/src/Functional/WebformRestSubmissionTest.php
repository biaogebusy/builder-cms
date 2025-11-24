<?php

namespace Drupal\Tests\webform_rest\Functional;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Client;
use Behat\Mink\Driver\BrowserKitDriver;

/**
 * Test the webform rest endpoints for submissions.
 *
 * @group webform_rest
 */
class WebformRestSubmissionTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'webform',
    'webform_rest',
    'webform_rest_test',
  ];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['webform_rest_test'];

  /**
   * Test method GET submission resource.
   */
  public function testWebformRestGetSubmission() {
    $webform = Webform::load('webform_rest_test');
    $this->drupalLogin($this->rootUser);
    $sid = $this->postSubmission($webform, [
      'first_name' => 'John',
      'last_name' => 'Smith',
    ]);
    $webform_submission = WebformSubmission::load($sid);
    $uuid = $webform_submission->uuid();

    // Get webform submission.
    $result = $this->drupalGet("/webform_rest/webform_rest_test/submission/$uuid", ['query' => ['_format' => 'hal_json']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('"data":{"first_name":"John","last_name":"Smith"}');
  }

  /**
   * Test method POST submission resource.
   */
  public function testWebformRestPostSubmission() {
    $webform = Webform::load('webform_rest_test');
    $this->drupalLogin($this->rootUser);
    $body = [
      'webform_id' => 'webform_rest_test',
      'first_name' => 'John',
      'last_name' => 'Smith',
    ];

    $client = new Client();
    $token = $this->drupalGet("/session/token", ['query' => ['_format' => 'hal_json']]);
    $response = $this->request('POST', $this->baseUrl . '/webform_rest/submit?_format=json', [
      'body' => Json::encode($body),
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'X-CSRF-Token' => $token,
      ],
    ]);
    $created_response = Json::decode((string) $response->getBody());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertArrayHasKey('sid', $created_response);
    $this->assertNotEmpty($created_response['sid']);
  }

  /**
   * Test method PATCH submission resource.
   */
  public function testWebformRestPatchSubmission() {
    $webform = Webform::load('webform_rest_test');
    $this->drupalLogin($this->rootUser);
    $sid = $this->postSubmissionTest($webform, ['first_name' => 'John', 'last_name' => 'Smith']);

    // Get webform submission.
    $webform_submission = WebformSubmission::load($sid);
    $uuid = $webform_submission->uuid();

    $body = [
      'webform_id' => 'webform_rest_test',
      'first_name' => 'Daniel',
      'last_name' => 'Hopkins',
    ];

    $client = new Client();
    $token = $this->drupalGet("/session/token", ['query' => ['_format' => 'hal_json']]);
    $response = $this->request('PATCH', $this->baseUrl . "/webform_rest/webform_rest_test/submission/$uuid?_format=json", [
      'body' => Json::encode($body),
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'X-CSRF-Token' => $token,
      ],
    ]);
    $created_response = Json::decode((string) $response->getBody());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertArrayHasKey('sid', $created_response);
    $this->assertNotEmpty($created_response['sid']);
    $uuid = $created_response['sid'];
    // Get webform submission.
    $result = $this->drupalGet("/webform_rest/webform_rest_test/submission/$uuid", ['query' => ['_format' => 'hal_json']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('"data":{"first_name":"Daniel","last_name":"Hopkins"}');
  }

  /**
   * Performs a HTTP request. Wraps the Guzzle HTTP client.
   *
   * Why wrap the Guzzle HTTP client? Because we want to keep the actual test
   * code as simple as possible, and hence not require them to specify the
   * 'http_errors = FALSE' request option, nor do we want them to have to
   * convert Drupal Url objects to strings.
   *
   * We also don't want to follow redirects automatically, to ensure these tests
   * are able to detect when redirects are added or removed.
   *
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   URL to request.
   * @param array $request_options
   *   Request options to apply.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  protected function request($method, $url, array $request_options = []) {
    $request_options[RequestOptions::HTTP_ERRORS] = FALSE;
    $request_options[RequestOptions::ALLOW_REDIRECTS] = FALSE;
    $request_options = $this->decorateWithXdebugCookie($request_options);
    $client = $this->getHttpClient();
    return $client->request($method, $url, $request_options);
  }

  /**
   * Adds the Xdebug cookie to the request options.
   *
   * @param array $request_options
   *   The request options.
   *
   * @return array
   *   Request options updated with the Xdebug cookie if present.
   */
  protected function decorateWithXdebugCookie(array $request_options) {
    $session = $this->getSession();
    $driver = $session->getDriver();
    if ($driver instanceof BrowserKitDriver) {
      $client = $driver->getClient();
      foreach ($client->getCookieJar()->all() as $cookie) {
        if (isset($request_options[RequestOptions::HEADERS]['Cookie'])) {
          $request_options[RequestOptions::HEADERS]['Cookie'] .= '; ' . $cookie->getName() . '=' . $cookie->getValue();
        }
        else {
          $request_options[RequestOptions::HEADERS]['Cookie'] = $cookie->getName() . '=' . $cookie->getValue();
        }
      }
    }
    return $request_options;
  }

}
