<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\OperationType\TranslateText;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextOutput;

/**
 * Tests the TranslateTextInput and TranslateTextOutput classes.
 *
 * @group ai
 */
final class TranslateTextTest extends UnitTestCase {

  /**
   * Tests that a valid input object is created correctly.
   */
  public function testTranslateTextInputCreation(): void {
    $text = 'Hello, world!';
    $source_language = 'en';
    $target_language = 'es';

    $input = new TranslateTextInput($text, $source_language, $target_language);

    $this->assertEquals($text, $input->toString());
    $this->assertEquals($text, (string) $input);
    $this->assertEquals($source_language, $input->getSourceLanguage());
    $this->assertEquals($target_language, $input->getTargetLanguage());

    // Test setting new values.
    $new_source_language = 'fr';
    $new_target_language = 'de';

    $input->setSourceLanguage($new_source_language);
    $input->setTargetLanguage($new_target_language);

    $this->assertEquals($new_source_language, $input->getSourceLanguage());
    $this->assertEquals($new_target_language, $input->getTargetLanguage());
  }

  /**
   * Tests that the TranslateTextOutput class is created correctly.
   */
  public function testTranslateTextOutputCreation(): void {
    $normalized = '¡Hola, munda!';
    $raw_output = ['translated_text' => '¡Hola, munda!'];
    $metadata = ['confidence' => 0.95];

    $output = new TranslateTextOutput($normalized, $raw_output, $metadata);

    $this->assertEquals($normalized, $output->getNormalized());
    $this->assertEquals($raw_output, $output->getRawOutput());
    $this->assertEquals($metadata, $output->getMetadata());

    // Test converting to array.
    $array_output = $output->toArray();
    $this->assertArrayHasKey('normalized', $array_output);
    $this->assertArrayHasKey('rawOutput', $array_output);
    $this->assertArrayHasKey('metadata', $array_output);
  }

}
