<?php

namespace Drupal\Tests\panels\Kernel {

  use Drupal\page_manager\Entity\PageVariant;
  use Drupal\Tests\system\Kernel\Token\TokenReplaceKernelTestBase;

  /**
   * @coversDefaultClass \Drupal\panels\Plugin\DisplayBuilder\StandardDisplayBuilder
   * @group Panels
   */
  class StandardDisplayBuilderTest extends TokenReplaceKernelTestBase {

    /**
     * {@inheritdoc}
     */
    protected static $modules = [
      'ctools',
      'layout_discovery',
      'page_manager',
      'panels',
    ];

    protected function setUp(): void {
      parent::setUp();
      // Set the site name to something other than an empty string.
      $this->config('system.site')->set('name', 'Drupal')->save();
    }

    /**
     * Test that page title with normal text is rendered correctly.
     */
    public function testNormalPageTitle() {
      /** @var \Drupal\panels\Plugin\DisplayBuilder\DisplayBuilderManagerInterface $displayBuilderManager */
      $displayBuilderManager = $this->container->get('plugin.manager.panels.display_builder');

      /** @var \Drupal\panels\Plugin\DisplayBuilder\DisplayBuilderInterface $standardDisplayBuilder */
      $standardDisplayBuilder = $displayBuilderManager->createInstance('standard');

      $variant = PageVariant::create([
        'id' => 'stunning',
        'label' => $this->randomMachineName(),
        'variant' => 'panels_variant',
        'variant_settings' => [
          'page_title' => 'Pastafazoul',
          'storage_type' => 'page_manager',
          'storage_id' => $this->randomMachineName(),
          'layout' => 'layout_onecol',
          'layout_settings' => [],
          'blocks' => [
            'page_title_block' => [
              'id' => 'page_title_block',
              'label' => '',
              'provider' => FALSE,
              'label_display' => FALSE,
              'region' => 'content',
            ],
          ],
        ],
        'page' => $this->randomMachineName(),
      ]);

      $buildResult = $standardDisplayBuilder->build($variant->getVariantPlugin());

      $this->assertSame('Pastafazoul', '' . $buildResult['content']['page_title_block']['content']['#title']);
    }

    /**
     * Test that page title with token is rendered correctly.
     */
    public function testTokenPageTitle() {
      /** @var \Drupal\panels\Plugin\DisplayBuilder\DisplayBuilderManagerInterface $displayBuilderManager */
      $displayBuilderManager = $this->container->get('plugin.manager.panels.display_builder');

      /** @var \Drupal\panels\Plugin\DisplayBuilder\DisplayBuilderInterface $standardDisplayBuilder */
      $standardDisplayBuilder = $displayBuilderManager->createInstance('standard');

      $variant = PageVariant::create([
        'id' => 'stunning',
        'label' => $this->randomMachineName(),
        'variant' => 'panels_variant',
        'variant_settings' => [
          'page_title' => '[site:name]',
          'storage_type' => 'page_manager',
          'storage_id' => $this->randomMachineName(),
          'layout' => 'layout_onecol',
          'layout_settings' => [],
          'blocks' => [
            'page_title_block' => [
              'id' => 'page_title_block',
              'label' => '',
              'provider' => FALSE,
              'label_display' => FALSE,
              'region' => 'content',
            ],
          ],
        ],
        'page' => $this->randomMachineName(),
      ]);

      $buildResult = $standardDisplayBuilder->build($variant->getVariantPlugin());
      $this->assertSame('Drupal', '' . $buildResult['content']['page_title_block']['content']['#title']);
    }

  }
}

namespace Drupal\Core\Extension {
  if (!function_exists('system_get_info')) {

    function system_get_info($type, $name = NULL) {
      switch ($name) {
        case 'ctools':
          return 'Chaos tools';

        default:
          return $name;
      }
    }

  }
}
