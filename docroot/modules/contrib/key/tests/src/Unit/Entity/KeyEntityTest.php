<?php

namespace Drupal\Tests\key\Unit\Entity;

use Drupal\key\Entity\Key;
use Drupal\key\Plugin\KeyProvider\ConfigKeyProvider;
use Drupal\key\Plugin\KeyType\AuthenticationKeyType;
use Drupal\key\Plugin\KeyInput\NoneKeyInput;
use Drupal\Tests\key\Unit\KeyTestBase;

/**
 * @coversDefaultClass \Drupal\key\Entity\Key
 * @group key
 */
class KeyEntityTest extends KeyTestBase {

  /**
   * Key type manager.
   *
   * @var \Drupal\key\Plugin\KeyPluginManager
   */
  protected $keyTypeManager;

  /**
   * Key provider manager.
   *
   * @var \Drupal\key\Plugin\KeyPluginManager
   */
  protected $keyProviderManager;

  /**
   * Key plugin manager.
   *
   * @var \Drupal\key\Plugin\KeyPluginManager
   */
  protected $keyInputManager;

  /**
   * Key type settings.
   *
   * @var array
   *   Key type settings to use for Authentication key type.
   */
  protected $keyTypeSettings;

  /**
   * Key provider settings.
   *
   * @var array
   *   Key provider settings to use for Configuration key provider.
   */
  protected $keyProviderSettings;

  /**
   * Key input settings.
   *
   * @var array
   *   Key input settings to use for None key input.
   */
  protected $keyInputSettings;

  /**
   * Assert that key entity getters work.
   */
  public function testGetters() {
    // Create a key entity using Configuration key provider.
    $values = [
      'key_id' => $this->getRandomGenerator()->word(15),
      'key_provider' => 'config',
      'key_provider_settings' => $this->keyProviderSettings,
    ];
    $key = new Key($values, 'key');

    $this->assertEquals($values['key_provider'], $key->getKeyProvider()->getPluginId());
    $this->assertEquals($values['key_provider_settings'], $key->getKeyProvider()->getConfiguration());
    $this->assertEquals($values['key_provider_settings']['key_value'], $key->getKeyProvider()->getConfiguration()['key_value']);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $definition = [
      'id' => 'authentication',
      'label' => 'Authentication',
    ];
    $this->keyTypeSettings = [];
    $plugin = new AuthenticationKeyType($this->keyTypeSettings, 'authentication', $definition);

    // Mock the KeyTypeManager service.
    $this->keyTypeManager = $this->createMock('\Drupal\key\Plugin\KeyPluginManager');

    $this->keyTypeManager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn([
        ['id' => 'authentication', 'label' => 'Authentication'],
      ]);
    $this->keyTypeManager->expects($this->any())
      ->method('createInstance')
      ->with('authentication', $this->keyTypeSettings)
      ->willReturn($plugin);
    $this->container->set('plugin.manager.key.key_type', $this->keyTypeManager);

    $definition = [
      'id' => 'config',
      'label' => 'Configuration',
      'tags' => ['config'],
    ];
    $this->keyProviderSettings = ['key_value' => $this->createToken(), 'base64_encoded' => FALSE];
    $plugin = new ConfigKeyProvider($this->keyProviderSettings, 'config', $definition);

    // Mock the KeyProviderManager service.
    $this->keyProviderManager = $this->createMock('\Drupal\key\Plugin\KeyPluginManager');

    $this->keyProviderManager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn([
        ['id' => 'file', 'label' => 'File', 'tags' => ['file']],
        [
          'id' => 'config',
          'label' => 'Configuration',
          'tags' => ['config'],
        ],
      ]);
    $this->keyProviderManager->expects($this->any())
      ->method('createInstance')
      ->with('config', $this->keyProviderSettings)
      ->willReturn($plugin);
    $this->container->set('plugin.manager.key.key_provider', $this->keyProviderManager);

    $definition = [
      'id' => 'none',
      'label' => 'None',
    ];
    $this->keyInputSettings = [];
    $plugin = new NoneKeyInput($this->keyInputSettings, 'none', $definition);

    // Mock the KeyInputManager service.
    $this->keyInputManager = $this->createMock('\Drupal\key\Plugin\KeyPluginManager');

    $this->keyInputManager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn([
        ['id' => 'none', 'label' => 'None'],
      ]);
    $this->keyInputManager->expects($this->any())
      ->method('createInstance')
      ->with('none', $this->keyInputSettings)
      ->willReturn($plugin);
    $this->container->set('plugin.manager.key.key_input', $this->keyInputManager);

    \Drupal::setContainer($this->container);
  }

}
