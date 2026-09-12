<?php

namespace Drupal\Tests\pathauto\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests tokens provided by Pathauto.
 *
 * @group pathauto
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class PathautoTokenTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system', 'token', 'path_alias', 'pathauto'];

  /**
   * Tests pathauto tokens.
   */
  public function testPathautoTokens() {

    $this->installConfig(['pathauto']);

    $array = [
      'test first arg',
      'The Array / value',
    ];

    $tokens = [
      'join-path' => 'test-first-arg/array-value',
    ];
    $data['array'] = $array;
    $replacements = $this->assertTokens('array', $data, $tokens);

    // Ensure the cleanTokenValues() method does not alter this token value.
    /** @var \Drupal\pathauto\AliasCleanerInterface $alias_cleaner */
    $alias_cleaner = \Drupal::service('pathauto.alias_cleaner');
    $alias_cleaner->cleanTokenValues($replacements, $data, []);
    $this->assertEquals('test-first-arg/array-value', $replacements['[array:join-path]']);

    // Test additional token cleaning and its configuration.
    $safe_tokens = $this->config('pathauto.settings')->get('safe_tokens');
    $safe_tokens[] = 'safe';
    $this->config('pathauto.settings')
      ->set('safe_tokens', $safe_tokens)
      ->save();

    $safe_tokens = [
      '[example:path]',
      '[example:url]',
      '[example:url-brief]',
      '[example:login-url]',
      '[example:login-url:relative]',
      '[example:url:relative]',
      '[example:safe]',
      '[safe:example]',
    ];
    $unsafe_tokens = [
      '[example:path_part]',
      '[example:something_url]',
      '[example:unsafe]',
    ];
    foreach ($safe_tokens as $token) {
      $replacements = [
        $token => 'this/is/a/path',
      ];
      $alias_cleaner->cleanTokenValues($replacements);
      $this->assertEquals('this/is/a/path', $replacements[$token], "Token $token cleaned.");
    }
    foreach ($unsafe_tokens as $token) {
      $replacements = [
        $token => 'This is not a / path',
      ];
      $alias_cleaner->cleanTokenValues($replacements);
      $this->assertEquals('not-path', $replacements[$token], "Token $token not cleaned.");
    }
  }

  /**
   * Tests that regex-special chars in safe_tokens don't break cleanTokenValues.
   *
   * Adds tokens with regex-special characters (?, -, :) to safe_tokens
   * and calls cleanTokenValues(). Without preg_quote(), preg_match()
   * would emit a PHP warning which PHPUnit treats as a test failure.
   *
   * @see https://www.drupal.org/project/pathauto/issues/3285655
   */
  public function testCleanTokenValuesWithRegexSpecialChars() {
    $this->installConfig(['pathauto']);

    // Add tokens containing regex-special characters: ? is a
    // quantifier, - is a range operator, : is used as a delimiter
    // in the safe_tokens regex pattern.
    $safe_tokens = $this->config('pathauto.settings')->get('safe_tokens');
    $safe_tokens[] = '?';
    $safe_tokens[] = 'url-brief';
    $safe_tokens[] = 'custom:token';
    $this->config('pathauto.settings')
      ->set('safe_tokens', $safe_tokens)
      ->save();

    $alias_cleaner = $this->container->get('pathauto.alias_cleaner');

    // Without the preg_quote() fix, this triggers a preg_match()
    // warning that PHPUnit converts to a test failure.
    $replacements = [
      '[node:title]' => 'Test title',
    ];
    $alias_cleaner->cleanTokenValues($replacements);

    // Verify that non-safe token values are cleaned (lowercased,
    // hyphenated, etc.).
    $this->assertEquals('test-title', $replacements['[node:title]']);
  }

  /**
   * Function copied from TokenTestHelper::assertTokens().
   */
  public function assertTokens($type, array $data, array $tokens, array $options = []) {
    $input = $this->mapTokenNames($type, array_keys($tokens));
    $bubbleable_metadata = new BubbleableMetadata();
    $replacements = \Drupal::token()->generate($type, $input, $data, $options, $bubbleable_metadata);
    foreach ($tokens as $name => $expected) {
      $token = $input[$name];
      if (!isset($expected)) {
        $this->assertTrue(!isset($values[$token]), new FormattableMarkup("Token value for @token was not generated.", [
          '@type' => $type,
          '@token' => $token,
        ]));
      }
      elseif (!isset($replacements[$token])) {
        $this->fail(new FormattableMarkup("Token value for @token was not generated.", [
          '@type' => $type,
          '@token' => $token,
        ]));
      }
      elseif (!empty($options['regex'])) {
        $this->assertTrue(preg_match('/^' . $expected . '$/', $replacements[$token]), new FormattableMarkup("Token value for @token was '@actual', matching regular expression pattern '@expected'.", [
          '@type' => $type,
          '@token' => $token,
          '@actual' => $replacements[$token],
          '@expected' => $expected,
        ]));
      }
      else {
        $this->assertSame($expected, $replacements[$token], new FormattableMarkup("Token value for @token was '@actual', expected value '@expected'.", [
          '@type' => $type,
          '@token' => $token,
          '@actual' => $replacements[$token],
          '@expected' => $expected,
        ]));
      }
    }

    return $replacements;
  }

  /**
   * Maps token names to a specific token format based on the provided type.
   *
   * @param string $type
   *   The type of tokens being mapped (e.g., entity type, category).
   * @param array $tokens
   *   An array of token names to map.
   *
   * @return array
   *   An associative array where the keys are the original token names and
   *   the values are formatted token strings in the pattern "[type:token]".
   */
  public function mapTokenNames($type, array $tokens = []) {
    $return = [];
    foreach ($tokens as $token) {
      $return[$token] = "[$type:$token]";
    }
    return $return;
  }

}
