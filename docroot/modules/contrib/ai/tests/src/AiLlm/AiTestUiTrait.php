<?php

namespace Drupal\Tests\ai\AiLlm;

/**
 * A trait to support tests that can be modified via the AI Test UI.
 *
 * @phpstan-require-implements \Drupal\Tests\ai\AiLlm\AiTestUiInterface
 * @phpstan-require-extends \Drupal\Tests\ai\AiLlm\AiProviderTestBase
 */
trait AiTestUiTrait {

  /**
   * Data provider that handles UI provided data and the available models.
   *
   * @return \Generator<int|string, array>
   *   The full set of run data.
   */
  public static function dataProviderWithModels(): \Generator {
    if ($config = getenv('AI_PHPUNIT_UI_DATA')) {
      yield json_decode($config, flags: \JSON_THROW_ON_ERROR);
    }
    else {
      foreach (self::getModels() as $model) {
        yield from static::dataProvider($model);
      }
    }
  }

}
