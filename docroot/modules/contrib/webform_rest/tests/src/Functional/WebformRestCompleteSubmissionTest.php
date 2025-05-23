<?php

namespace Drupal\Tests\webform_rest\Functional;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\webform\Entity\Webform;
use Drupal\Component\Serialization\Json;

/**
 * Test the webform rest endpoints for complete submissions.
 *
 * @group webform_rest
 */
class WebformRestCompleteSubmissionTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
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
   * Test method GET complete submission resource.
   */
  public function testWebformRestGetCompleteSubmission() {
    $this->drupalLogin($this->rootUser);
    $webform = Webform::load('webform_rest_test');
    $sid = $this->postSubmission($webform, [
      'first_name' => 'John',
      'last_name' => 'Smith',
    ]);

    // Get webform submission and fields.
    $result = $this->drupalGet("/webform_rest/webform_rest_test/complete_submission/$sid", ['query' => ['_format' => 'hal_json']]);
    $created_response = Json::decode((string) $result);
    // debug($result);
    $this->assertResponse(200);
    $this->assertRaw('"title":"Test: Webform rest"');
    $this->assertRaw('"first_name":{"#title":"First name"');
    $this->assertRaw('"last_name":{"#title":"Last name"');
    $this->assertArrayHasKey('processed_submission', $created_response);
    $this->assertArrayHasKey('first_name', $created_response['processed_submission']);
    $this->assertArrayHasKey('last_name', $created_response['processed_submission']);
    $this->assertArrayHasKey('value', $created_response['processed_submission']['first_name']);
    $this->assertArrayHasKey('value', $created_response['processed_submission']['last_name']);
    $this->assertEquals('John', $created_response['processed_submission']['first_name']['value']);
    $this->assertEquals('Smith', $created_response['processed_submission']['last_name']['value']);
  }

}
