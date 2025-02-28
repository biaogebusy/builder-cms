<?php

namespace Drupal\Tests\eck\Functional;

/**
 * Tests eck's bundle creation, update and deletion.
 *
 * @group eck
 */
class BundleCRUDTest extends FunctionalTestBase {

  /**
   * Tests single bundle creation.
   *
   * @test
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function singleBundleCreation() {
    $entityTypeInfo = $this->createEntityType([], 'TestType');
    $this->createEntityBundle($entityTypeInfo['id'], 'TestBundle');
  }

  /**
   * Tests single bundle creation with title overrides.
   *
   * @test
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function singleBundleCreationWithOverrides() {
    $entityTypeInfo = $this->createEntityType();

    $title_overrides = [];
    foreach ($this->getConfigurableBaseFields() as $field) {
      $title_overrides[$field] = $this->randomMachineName(16);
    }

    $this->createEntityBundle($entityTypeInfo['id'], '', $title_overrides);
  }

  /**
   * Tests single bundle edit with title overrides.
   *
   * @test
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function singleBundleEditWithOverrides() {
    $entityTypeInfo = $this->createEntityType();

    $title_overrides = [];
    foreach ($this->getConfigurableBaseFields() as $field) {
      $title_overrides[$field] = $this->randomMachineName(16);
    }

    $bundle_info = $this->createEntityBundle($entityTypeInfo['id'], '', $title_overrides);

    $new_title_overrides = [];
    foreach ($this->getConfigurableBaseFields() as $field) {
      $new_title_overrides[$field] = $this->randomMachineName(16);
    }

    $this->editEntityBundle($entityTypeInfo['id'], $bundle_info['type'], $this->randomMachineName(16), $new_title_overrides);
  }

  /**
   * Tests multiple bundle creation.
   *
   * @test
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function multipleBundleCreation() {
    $entityTypeInfo = $this->createEntityType([], 'TestType');
    $this->createEntityBundle($entityTypeInfo['id'], 'TestBundle1');
    $this->createEntityBundle($entityTypeInfo['id'], 'TestBundle2');
  }

  /**
   * Tests identically named bundle creation.
   *
   * @test
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function identicallyNamedBundleCreation() {
    $entityTypeInfo1 = $this->createEntityType([], 'TestType1');
    $entityTypeInfo2 = $this->createEntityType([], 'TestType2');

    $this->createEntityBundle($entityTypeInfo1['id'], 'TheBundle');
    $this->createEntityBundle($entityTypeInfo2['id'], 'TheBundle');
  }

}
