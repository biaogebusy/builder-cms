<?php

namespace Drupal\Tests\eck\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\eck\EckEntityTypeBundleInfo;

/**
 * Tests the form element implementation.
 *
 * @group eck
 */
class EntityTypeBundleInfoTest extends UnitTestBase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManagerMock;
  /**
   * The language manager mock.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManagerMock;
  /**
   * The module handler mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandlerMock;
  /**
   * The typed data manager mock.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManagerMock;
  /**
   * The cache backend mock.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackendMock;

  /**
   * Tests that entityTypeHasBundles returns false on non-existing entity type.
   *
   * @test
   */
  public function returnsFalseWhenNonExistingEntityTypeIsPassed() {
    $sut = $this->createNewTestSubject();
    $this->assertFalse($sut->entityTypeHasBundles('does not exist'));
  }

  /**
   * Tests that entityTypeHasBundles returns false if it has no bundles.
   *
   * @test
   */
  public function returnsFalseWhenEntityTypeHasNoBundles() {
    $sut = $this->createNewTestSubjectWithEntityType();
    $this->assertFalse($sut->entityTypeHasBundles('existing_entity_type'));
  }

  /**
   * Tests that entityTypeHasBundles returns true if it has bundles.
   *
   * @test
   */
  public function returnsTrueWhenEntityTypeHasAtLeastOneBundle() {
    $sut = $this->createNewTestSubjectWithEntityTypeAndBundles();
    $this->assertTrue($sut->entityTypeHasBundles('existing_entity_type'));
  }

  /**
   * Tests caching of data.
   *
   * @test
   */
  public function entityTypeHasBundlesMethodCachesData() {
    $this->cacheBackendMock = $this->createMock(CacheBackendInterface::class);
    $this->cacheBackendMock->expects($this->once())->method('set');
    $sut = $this->createNewTestSubject();
    $sut->entityTypeHasBundles('test');
  }

  /**
   * Tests that cached data is used if available.
   *
   * @test
   */
  public function usesCachedDataWhenAvailable() {
    $this->cacheBackendMock = $this->cacheBackendMock = $this->createMock(CacheBackendInterface::class);
    $this->cacheBackendMock->expects($this->once())
      ->method('get')
      ->willReturn((object) ['data' => 'obviously not normal bundle info']);

    $sut = $this->createNewTestSubject();
    $this->assertSame('obviously not normal bundle info', $sut->getAllBundleInfo());
  }

  /**
   * Tests that no machine names are returned if the entity type doesn't exist.
   *
   * @test
   */
  public function returnsNoMachineNamesIfEntityTypeDoesNotExist() {
    $sut = $this->createNewTestSubject();
    $this->assertEmpty($sut->getEntityTypeBundleMachineNames('non_existing_entity_type'));
  }

  /**
   * Tests that no machine names are returned if there are no bundles.
   *
   * @test
   */
  public function returnsNoMachineNamesIfEntityTypeHasNoBundles() {
    $sut = $this->createNewTestSubjectWithEntityType();
    $this->assertEmpty($sut->getEntityTypeBundleMachineNames('existing_entity_type'));
  }

  /**
   * Tests that machine names are returned if there are bundles.
   *
   * @test
   */
  public function returnsMachineNamesIfEntityTypeHasBundles() {
    $sut = $this->createNewTestSubjectWithEntityTypeAndBundles();
    $this->assertNotEmpty($sut->getEntityTypeBundleMachineNames('existing_entity_type'));
  }

  /**
   * Tests that zero is returned if the entity type doesn't exist.
   *
   * @test
   */
  public function returnsZeroIfEntityTypeDoesNotExist() {
    $sut = $this->createNewTestSubject();
    $this->assertEquals(0, $sut->entityTypeBundleCount('non_existing_entity_type'));
  }

  /**
   * Tests that zero is returned if the entity type has no bundles.
   *
   * @test
   */
  public function returnsZeroIfEntityTypeHasNoBundles() {
    $sut = $this->createNewTestSubjectWithEntityType();
    $this->assertEquals(0, $sut->entityTypeBundleCount('existing_entity_type'));
  }

  /**
   * Tests that the number of entity type bundles is counted correctly.
   *
   * @test
   */
  public function correctlyCountsEntityTypeBundles() {
    for ($i = 1; $i <= 10; $i++) {
      $sut = $this->createNewTestSubjectWithEntityTypeAndBundles($i);
      $this->assertEquals($i, $sut->entityTypeBundleCount('existing_entity_type'));
    }
  }

  /**
   * Creates a new test subject.
   *
   * @return \Drupal\eck\EckEntityTypeBundleInfo
   *   The newly created test subject.
   */
  protected function createNewTestSubject() {
    if (NULL === $this->entityTypeManagerMock) {
      $this->entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
      $this->entityTypeManagerMock->method('getDefinitions')->willReturn([]);
    }
    if (NULL === $this->languageManagerMock) {
      $this->languageManagerMock = $this->createLanguageManagerMock();
    }
    if (NULL === $this->moduleHandlerMock) {
      $this->moduleHandlerMock = $this->createMock(ModuleHandlerInterface::class);
    }
    if (NULL === $this->typedDataManagerMock) {
      $this->typedDataManagerMock = $this->createMock(TypedDataManagerInterface::class);
    }
    if (NULL === $this->cacheBackendMock) {
      $this->cacheBackendMock = $this->createMock(CacheBackendInterface::class);
    }

    return new EckEntityTypeBundleInfo($this->entityTypeManagerMock, $this->languageManagerMock, $this->moduleHandlerMock, $this->typedDataManagerMock, $this->cacheBackendMock);
  }

  /**
   * Creates a new test subject with an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityTypeMock
   *   The entity type mock.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entityStorageMock
   *   The entity storage mock.
   *
   * @return \Drupal\eck\EckEntityTypeBundleInfo
   *   The test subject.
   */
  protected function createNewTestSubjectWithEntityType(?EntityTypeInterface $entityTypeMock = NULL, ?EntityStorageInterface $entityStorageMock = NULL) {
    if (NULL === $entityTypeMock) {
      $entityTypeMock = $this->createMock(EntityTypeInterface::class);
      $entityTypeMock->method('getBundleEntityType')
        ->willReturn('eck_entity_bundle');
    }
    if (NULL === $entityStorageMock) {
      $entityStorageMock = $this->createMock(EntityStorageInterface::class);
      $entityStorageMock->method('loadMultiple')->willReturn([]);
    }

    $this->entityTypeManagerMock = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManagerMock->method('getDefinitions')
      ->willReturn(['existing_entity_type' => $entityTypeMock]);
    $this->entityTypeManagerMock->method('getStorage')
      ->willReturn($entityStorageMock);

    return $this->createNewTestSubject();
  }

  /**
   * Creates a new test subject with entity type and bundles.
   *
   * @param int $numberOfBundlesToCreate
   *   The number of bundles to create.
   *
   * @return \Drupal\eck\EckEntityTypeBundleInfo
   *   The bundle info to test.
   */
  protected function createNewTestSubjectWithEntityTypeAndBundles($numberOfBundlesToCreate = 1) {
    $bundles = [];
    for ($i = 0; $i < $numberOfBundlesToCreate; $i++) {
      $machineName = $this->randomMachineName();
      $bundleMock = $this->createMock(EntityInterface::class);
      $bundleMock->method('id')->willReturn(strtolower($machineName));
      $bundleMock->method('label')->willReturn($machineName);
      $bundles[strtolower($machineName)] = $bundleMock;
    }
    $entityStorageMock = $this->createMock(EntityStorageInterface::class);
    $entityStorageMock->method('loadMultiple')->willReturn($bundles);
    return $this->createNewTestSubjectWithEntityType(NULL, $entityStorageMock);
  }

}
