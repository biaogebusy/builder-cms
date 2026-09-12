<?php

namespace Drupal\Tests\webform\Functional;

use Drupal\webform\Entity\Webform;

/**
 * Tests for webform entity validation.
 *
 * @group webform
 */
class WebformEntityElementsValidationTest extends WebformBrowserTestBase {

  /**
   * Tests validating elements.
   */
  public function testValidate(): void {
    $assert = $this->assertSession();

    $this->drupalLogin($this->rootUser);

    $webform = Webform::create([
      'id' => 'test_elements_validation',
      'title' => 'Test elements validation',
    ]);
    $webform->save();

    // Check render validation.
    $tests = [
      '' => 'Elements (YAML) field is required.',
      'not-valid' => 'YAML must contain an associative array of elements.',
      "foo:
  '#markup': !my_tag
    foo: bar"
        => 'strlen(): Argument #1 ($string) must be of type string, Symfony\Component\Yaml\Tag\TaggedValue given',
    ];
    foreach ($tests as $elements => $message) {
      $this->drupalGet('/admin/structure/webform/manage/test_elements_validation');
      $this->submitForm(['elements' => $elements], 'Save');
      $assert->responseContains($message);
    }
  }

}
