<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_api_explorer\Unit\Plugin\AiApiExplorer;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\ExplorerHelper;
use Drupal\ai_api_explorer\Plugin\AiApiExplorer\TranslationGenerator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests TranslationGenerator.
 */
#[Group('ai_api_explorer')]
final class TranslationGeneratorTest extends UnitTestCase {

  /**
   * Tests that getResponse() uses the correct provider form prefix.
   */
  public function testGetResponseUsesTranslationPrefix(): void {
    $requestStack = new RequestStack();
    $providerFormHelper = $this->createMock(AiProviderFormHelper::class);
    $explorerHelper = new ExplorerHelper();
    $providerManager = (new \ReflectionClass(AiProviderPluginManager::class))
      ->newInstanceWithoutConstructor();
    $formState = $this->createMock(FormStateInterface::class);

    $form = [
      'right' => [
        'response' => [
          '#context' => [
            'ai_response' => [],
          ],
        ],
      ],
    ];

    $providerFormHelper->expects($this->once())
      ->method('generateAiProviderFromFormSubmit')
      ->with(
        $this->anything(),
        $this->identicalTo($formState),
        'translate_text',
        'tt',
      )
      ->willThrowException(new \TypeError('Expected test exception'));

    $formState->expects($this->once())
      ->method('setRebuild');

    $plugin = new TranslationGenerator(
      [],
      'translation_generator',
      [],
      $requestStack,
      $providerFormHelper,
      $explorerHelper,
      $providerManager,
    );
    $plugin->setStringTranslation($this->getStringTranslationStub());

    $response = $plugin->getResponse($form, $formState);

    $this->assertArrayHasKey('message', $response['response']['#context']['ai_response']);
  }

}
