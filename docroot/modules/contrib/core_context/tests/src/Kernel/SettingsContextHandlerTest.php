<?php

namespace Drupal\Tests\core_context\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests handling of contexts stored in config entity third-party settings.
 *
 * @covers \Drupal\core_context\SettingsContextHandler
 *
 * @group core_context
 */
class SettingsContextHandlerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'core_context',
    'core_context_test',
    'ctools',
    'entity_test',
  ];

  /**
   * Tests extracting contexts from a config entity's third-party settings.
   */
  public function testContextsFromThirdPartySettings() {
    /** @var \Drupal\core_context\EntityContextHandlerInterface $handler */
    $handler = $this->container->get('entity_type.manager')
      ->getHandler('entity_test_bundle', 'context');

    $entity = EntityTestBundle::create([
      'id' => 'test',
      'label' => 'Test',
    ]);
    $entity->save();

    $contexts = $handler->getContexts($entity);
    $this->assertIsArray($contexts);
    $this->assertEmpty($contexts);

    $entity->setThirdPartySetting('core_context', 'contexts', [
      'password' => [
        'type' => 'string',
        'label' => 'Password',
        'description' => 'The password to enter Moria.',
        'value' => 'Mellon',
      ],
    ])->save();

    $contexts = $handler->getContexts($entity);
    $this->assertIsArray($contexts);
    $this->assertCount(1, $contexts);
    $this->assertInstanceOf(Context::class, $contexts['password']);

    /** @var \Drupal\Core\Plugin\Context\Context $context */
    $context = $contexts['password'];
    $context_definition = $context->getContextDefinition();
    $this->assertSame('string', $context_definition->getDataType());
    $this->assertSame('Password', $context_definition->getLabel());
    $this->assertSame('The password to enter Moria.', $context_definition->getDescription());
    $this->assertSame('Mellon', $context->getContextValue());

    // Ensure that the entity's cache metadata has been applied to the context.
    $this->assertSame($context->getCacheMaxAge(), $entity->getCacheMaxAge());
    $this->assertSame($context->getCacheTags(), $entity->getCacheTags());
    $this->assertSame($context->getCacheContexts(), $entity->getCacheContexts());
  }

}
