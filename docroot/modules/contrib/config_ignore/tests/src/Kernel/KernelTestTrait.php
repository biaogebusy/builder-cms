<?php

namespace Drupal\Tests\config_ignore\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\StorageInterface;

/**
 * Trait to be used in Kernel tests when modifying storage.
 */
trait KernelTestTrait {

  /**
   * Set up the active, sync and expected storages.
   *
   * @param array $active
   *   Modifications to the active config.
   * @param array $sync
   *   Modifications to the sync storage.
   * @param array $expected
   *   Array  of modifications to the expected storages keyed by storage alias.
   *
   * @return \Drupal\Core\Config\StorageInterface[]
   *   The expected storage.
   */
  protected function setUpMultipleStorages(array $active, array $sync, array $expected) : array {
    // Copy the active config to the sync storage and the expected storage.
    $syncStorage = $this->getSyncFileStorage();
    $this->copyConfig($this->getActiveStorage(), $syncStorage);
    $expectedStorages = [];
    foreach ($expected as $alias => $unused) {
      $expectedStorage = new MemoryStorage();
      $this->copyConfig($this->getActiveStorage(), $expectedStorage);
      $expectedStorages[$alias] = $expectedStorage;
    }

    // Then modify the active storage by saving the config which was given.
    foreach ($active as $lang => $configs) {
      foreach ($configs as $name => $data) {
        if ($lang === '') {
          $config = $this->config($name);
        }
        else {
          // Load the config override.
          /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
          $language_manager = \Drupal::languageManager();
          $config = $language_manager->getLanguageConfigOverride($lang, $name);
        }

        if ($data !== FALSE) {
          $config->merge($data)->save();
        }
        else {
          // If the data is not an array we want to delete it.
          $config->delete();
        }
      }
    }

    // Apply modifications to the storages.
    static::modifyStorage($syncStorage, $sync);
    foreach ($expected as $alias => $expectedData) {
      static::modifyStorage($expectedStorages[$alias], $expectedData);
    }

    return $expectedStorages;
  }

  /**
   * Set up the active, sync and expected storages.
   *
   * @param array $active
   *   Modifications to the active config.
   * @param array $sync
   *   Modifications to the sync storage.
   * @param array $expected
   *   Modifications to the expected storage.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The expected storage.
   */
  protected function setUpStorages(array $active, array $sync, array $expected): StorageInterface {
    return $this->setUpMultipleStorages($active, $sync, ['expected' => $expected])['expected'];
  }

  /**
   * Helper method to modify a config storage.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The storage to modify.
   * @param array $modifications
   *   The modifications keyed by language.
   */
  protected static function modifyStorage(StorageInterface $storage, array $modifications) {
    foreach ($modifications as $lang => $configs) {
      $lang = $lang === '' ? StorageInterface::DEFAULT_COLLECTION : 'language.' . $lang;
      $storage = $storage->createCollection($lang);
      if ($configs === NULL) {
        // If it is set to null explicitly remove everything.
        $storage->deleteAll();
        return;
      }
      foreach ($configs as $name => $data) {
        if ($data !== FALSE) {
          if (is_array($storage->read($name))) {
            // Merge nested arrays if the storage already has data.
            $data = NestedArray::mergeDeepArray([$storage->read($name), $data], TRUE);
          }
          $storage->write($name, $data);
        }
        else {
          // A config name set to false means deleting it.
          $storage->delete($name);
        }
      }
    }
  }

}
