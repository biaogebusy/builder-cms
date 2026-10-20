<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\xinshi_ai\Plugin\AiTask\ImageEditTask;
use Drupal\xinshi_ai\Plugin\AiTask\ImageGenerationTask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Image provider configuration reads negative prompts from the dedicated job field.
 */
final class ImageNegativePromptTest extends TestCase {

  use ImageJobNodeTrait;

  #[DataProvider('imageJobs')]
  public function testStoredNegativePromptReachesProviderConfig(string $kind, ?string $negativePrompt): void {
    $job = $this->jobNode('job-1', [
      'field_job_kind' => $kind,
      'field_negative_prompt' => $negativePrompt,
      'field_params' => '{"size":"1024*1024","negativePrompt":"unused","negative_prompt":"unused","unlisted":"ignored"}',
    ]);
    $taskClass = $kind === 'image_edit' ? ImageEditTask::class : ImageGenerationTask::class;
    $task = new $taskClass([], $kind, ['defaultN' => 4]);
    $config = (new \ReflectionMethod($task, 'buildGenConfig'))->invoke($task, $job, ['size']);

    $this->assertSame(4, $config['n']);
    $this->assertSame('7', $config['user']);
    $this->assertSame('1024x1024', $config['size']);
    $this->assertArrayNotHasKey('unlisted', $config);
    $this->assertArrayNotHasKey('negativePrompt', $config);
    if ($negativePrompt !== NULL && $negativePrompt !== '') {
      $this->assertSame($negativePrompt, $config['negative_prompt'] ?? NULL);
    }
    else {
      $this->assertArrayNotHasKey('negative_prompt', $config);
    }
  }

  public static function imageJobs(): array {
    $cases = [];
    foreach (['text_to_image', 'image_edit'] as $kind) {
      foreach (['populated' => 'watermarks, blurry', 'empty' => '', 'absent' => NULL] as $label => $value) {
        $cases[$kind . ' ' . $label] = [$kind, $value];
      }
    }
    return $cases;
  }

}
