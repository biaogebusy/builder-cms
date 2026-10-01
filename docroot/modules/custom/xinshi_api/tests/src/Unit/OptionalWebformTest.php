<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\xinshi_api\EntityJsonBase;
use PHPUnit\Framework\TestCase;

/** Checks page serialization with and without the optional Webform module. */
final class OptionalWebformTest extends TestCase {

  private EntityTypeManagerInterface $manager;
  private EntityJsonBase $json;

  protected function setUp(): void {
    parent::setUp();
    $this->manager = $this->createMock(EntityTypeManagerInterface::class);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->manager);
    \Drupal::setContainer($container);
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getCacheTags')->willReturn(['node:1']);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    $this->json = new EntityJsonBase($entity);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testDisabledModuleDoesNotLoadStorage(): void {
    $this->manager->expects($this->once())->method('hasDefinition')->with('webform')->willReturn(FALSE);
    $this->manager->expects($this->never())->method('getStorage');
    $data = ['fields' => ['dataType' => 'webform', 'data' => 'contact']];
    $this->json->setFullText($data);
    $this->assertSame(['fields' => []], $data);
  }

  public function testEmptyReferenceDoesNotLoadStorage(): void {
    $this->manager->expects($this->never())->method('getStorage');
    $data = ['fields' => ['dataType' => 'webform', 'data' => '']];
    $this->json->setFullText($data);
    $this->assertSame(['fields' => []], $data);
  }

  public function testMissingFormIsEmptyWhenModuleIsEnabled(): void {
    $this->manager->method('hasDefinition')->with('webform')->willReturn(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with('missing')->willReturn(NULL);
    $this->manager->method('getStorage')->with('webform')->willReturn($storage);
    $data = ['fields' => ['dataType' => 'webform', 'data' => 'missing']];
    $this->json->setFullText($data);
    $this->assertSame(['fields' => []], $data);
  }

  public function testEnabledFormKeepsFieldsAndCacheability(): void {
    $this->manager->method('hasDefinition')->with('webform')->willReturn(TRUE);
    $form = $this->createMock(OptionalWebformEntityInterface::class);
    $form->method('getCacheTags')->willReturn(['config:webform.webform.contact']);
    $form->method('getCacheContexts')->willReturn(['languages:language_interface']);
    $form->method('getCacheMaxAge')->willReturn(60);
    $form->method('getElementsDecodedAndFlattened')->willReturn([
      'email' => ['#type' => 'email', '#title' => 'Email', '#placeholder' => 'Your email', '#required' => TRUE],
      'message' => ['#type' => 'textarea', '#title' => 'Message', '#rows' => 4],
    ]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())->method('load')->with('contact')->willReturn($form);
    $this->manager->method('getStorage')->with('webform')->willReturn($storage);
    $data = ['fields' => ['dataType' => 'webform', 'data' => 'contact']];
    $this->json->setFullText($data);
    $this->assertSame([
      ['label' => 'Email', 'key' => 'email', 'placeholder' => 'Your email', 'params' => ['required' => TRUE], 'type' => 'input'],
      ['label' => 'Message', 'key' => 'message', 'params' => ['rows' => 4, 'matAutosizeMinRows' => 4], 'type' => 'textarea'],
    ], $data['fields']);
    $this->assertContains('config:webform.webform.contact', $this->json->getCacheTags());
    $this->assertContains('languages:language_interface', $this->json->getCacheContexts());
    $this->assertSame(60, $this->json->getCacheMaxAge());
  }

}

/** Minimal optional entity contract, without loading Webform in the bootstrap. */
interface OptionalWebformEntityInterface extends EntityInterface {

  public function getElementsDecodedAndFlattened();

}
