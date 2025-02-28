<?php

namespace Drupal\Tests\entity_print\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\entity_print\PrintEngineException;
use Drupal\Tests\UnitTestCase;

/**
 * Test print engine exceptions.
 *
 * @group entity_print
 */
class PrintEngineExceptionTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests the pretty error messages for authorisation failures.
   *
   * @dataProvider authorisationExceptions
   */
  public function testAuthorisationException($message) {
    $exception = new PrintEngineException($message);
    $this->assertEquals('Authorisation failed, are your resources behind HTTP authentication? Check the admin page to set credentials.', $exception->getPrettyMessage());
  }

  /**
   * Gets the exception messages for the providers for authorisation failures.
   *
   * @return array
   *   An array of possible exception messages.
   */
  public function authorisationExceptions() {
    return [
      'Dompdf' => ['Failed to generate PDF: file_get_contents(http://d8.dev/sites/default/files/css/css_qm-4SpSW1uLvtwb-T4OPS48NMb2DSFqwLd5C3MLRLuY.css?om2pmk): failed to open stream: HTTP request failed! HTTP/1.1 401 Unauthorized , Unable to load css file http://d8.dev/sites/default/files/css/css_qm-4SpSW1uLvtwb-T4OPS48NMb2DSFqwLd5C3MLRLuY.css?om2pmk'],
      'wkhtmltopdf' => ['Failed to generate PDF: Loading pages (1/6) [> ] 0% [======> ] 10% [==========> ] 18% Error: Invalid username or password [============================================================] 100% Counting pages (2/6) [============================================================] Object 1 of 1 Resolving links (4/6) [============================================================] Object 1 of 1 Loading headers and footers (5/6) Printing pages (6/6) [> ] Preparing [============================================================] Page 1 of 1 Done Exit with code 1 due to network error: AuthenticationRequiredError'],
    ];
  }

}
