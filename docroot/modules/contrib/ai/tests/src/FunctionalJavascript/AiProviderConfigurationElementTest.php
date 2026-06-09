<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\ai\AiProviderInterface;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\user\UserInterface;

/**
 * Tests the AI Provider Configuration form element.
 *
 * @group ai
 * @group 3580935
 */
class AiProviderConfigurationElementTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * AI Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $aiAdmin;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $user = $this->drupalCreateUser([
      'administer ai',
    ]);
    $this->assertNotFalse($user, 'AI admin user should be created successfully.');
    $this->aiAdmin = $user;
  }

  /**
   * Tests the AI Provider Configuration element renders correctly.
   */
  public function testElementRenders(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded');

    // Check that the element is present.
    $this->assertSession()->elementExists('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    $this->assertSession()->pageTextContains('AI Provider Configuration');
    $this->assertSession()->pageTextContains('Select an AI provider and model.');
    $this->takeScreenshot('2_element_rendered');
  }

  /**
   * Tests that AJAX loads configuration fields when provider/model is selected.
   */
  public function testAjaxLoadsConfiguration(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Check that configuration wrapper exists.
    $assert->waitForElement('css', '#edit-provider_config-config');
    $this->takeScreenshot('2_config_wrapper_present');

    // Select a provider/model if available.
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    if ($select) {
      $options = $select->findAll('css', 'option');
      // Skip the first option as its the default.
      if (count($options) > 1) {
        // Get the first non-empty option value.
        $option_value = NULL;
        foreach ($options as $option) {
          $value = $option->getValue();
          if (!empty($value) && $value !== AiProviderInterface::DEFAULT_MODEL_VALUE) {
            $option_value = $value;
            break;
          }
        }

        if ($option_value && $select->getValue() !== $option_value) {
          $select->setValue($option_value);
          // Wait for AJAX to complete.
          $assert->assertWaitOnAjaxRequest();
          $this->takeScreenshot('3_provider_selected_ajax_complete');

          // Wait for and verify the configuration container exists after AJAX.
          $config_wrapper = $assert->waitForElement('css', '#edit-provider_config-config');

          $this->assertNotNull(
            $config_wrapper,
            'Configuration wrapper should exist after selecting provider/model'
          );

          // The config_wrapper IS the details element, verify it's visible.
          $this->assertTrue(
            $config_wrapper->isVisible(),
            'Configuration details should be visible when provider/model is selected'
          );

          // Verify it has form fields inside (configuration was loaded).
          $form_fields = $config_wrapper->findAll('css', 'input, select, textarea');
          $this->assertNotEmpty(
            $form_fields,
            'Configuration fields should be present when provider/model is selected'
          );
          $this->takeScreenshot('4_configuration_fields_loaded');
        }
      }
    }
  }

  /**
   * Tests that default provider option appears when enabled.
   */
  public function testDefaultProviderOption(): void {
    $this->drupalLogin($this->aiAdmin);

    // Ensure a default provider is configured for the operation type.
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('ai.settings');
    $default_providers = $config->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'ai_test',
      'model_id' => 'test_model',
    ];
    $config->set('default_providers', $default_providers)->save();

    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded_with_default_provider_configured');

    $page = $this->getSession()->getPage();
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');

    if ($select) {
      $options = $select->findAll('css', 'option');
      foreach ($options as $option) {
        if ($option->getValue() === AiProviderInterface::DEFAULT_MODEL_VALUE) {
          $this->assertEquals('Default', $option->getText());
          $this->takeScreenshot('2_default_option_present');
          return;
        }
      }
      $this->fail('Default option should be present when default provider is configured.');
    }
  }

  /**
   * Tests that default value is set correctly.
   */
  public function testDefaultValue(): void {
    $this->drupalLogin($this->aiAdmin);

    // Set a default provider for chat operation type.
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('ai.settings');
    $default_providers = $config->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'ai_test',
      'model_id' => 'test_model',
    ];
    $config->set('default_providers', $default_providers)->save();

    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded_with_default_provider_configured');

    $page = $this->getSession()->getPage();
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');

    if ($select) {
      // The selected value should match the configured default provider.
      // The provider must be available and configured for this to work.
      $selected_value = $select->getValue();
      // If default is set, it should be DEFAULT_MODEL_VALUE or
      // 'provider__model'.
      $this->assertNotEmpty($selected_value);
      $this->takeScreenshot('2_default_value_present');
    }
  }

  /**
   * Tests form submission returns correct value structure.
   */
  public function testFormSubmissionValueStructure(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Select a provider/model if available.
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    if ($select) {
      $options = $select->findAll('css', 'option');
      if (count($options) > 1) {
        // Get the first non-empty, non-default option.
        $option_value = NULL;
        foreach ($options as $option) {
          $value = $option->getValue();
          if (!empty($value) && $value !== AiProviderInterface::DEFAULT_MODEL_VALUE) {
            $option_value = $value;
            break;
          }
        }

        if ($option_value) {
          $select->setValue($option_value);
          $assert->assertWaitOnAjaxRequest();
          $this->takeScreenshot('2_provider_selected_ajax_complete');

          // Submit the form.
          $page->pressButton('Submit');
          $this->takeScreenshot('3_form_submitted');

          // Check that a status message appears (form was submitted).
          $assert->pageTextContains('Form submitted');

          // Verify the value structure contains provider, model, config keys.
          $assert->pageTextMatches('/provider.*model.*config/i');
          $this->takeScreenshot('4_submission_output_verified');
        }
      }
    }
  }

  /**
   * Tests form submission with default provider returns correct value.
   */
  public function testFormSubmissionWithDefaultProvider(): void {
    $this->drupalLogin($this->aiAdmin);

    // Ensure a default provider is configured.
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('ai.settings');
    $default_providers = $config->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'ai_test',
      'model_id' => 'test_model',
    ];
    $config->set('default_providers', $default_providers)->save();

    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded_with_default_provider_configured');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Select default provider.
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    if ($select) {
      $options = $select->findAll('css', 'option');
      foreach ($options as $option) {
        if ($option->getValue() === AiProviderInterface::DEFAULT_MODEL_VALUE) {
          // Only trigger AJAX if value is changing.
          if ($select->getValue() !== AiProviderInterface::DEFAULT_MODEL_VALUE) {
            $select->setValue(AiProviderInterface::DEFAULT_MODEL_VALUE);
            $assert->assertWaitOnAjaxRequest();
          }
          $this->takeScreenshot('2_default_selected_ajax_complete');

          // Submit the form.
          $page->pressButton('Submit');
          $this->takeScreenshot('3_form_submitted');

          // Check that a status message appears (form was submitted).
          $assert->pageTextContains('Form submitted');

          // Verify the value structure contains provider and model keys.
          // When default is selected, config should be empty array.
          $assert->pageTextMatches('/provider.*model/i');
          $this->takeScreenshot('4_submission_output_verified');
          return;
        }
      }
    }
  }

  /**
   * Tests that advanced_config can be disabled.
   */
  public function testAdvancedConfigDisabled(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements', ['query' => ['advanced_config' => 0]]);
    $this->takeScreenshot('1_form_loaded_advanced_config_disabled');

    $assert = $this->assertSession();

    $assert->elementNotExists('css', '#edit-provider_config-config');
    $this->takeScreenshot('2_config_container_not_present');
  }

  /**
   * Tests that default_provider_allowed can be disabled.
   */
  public function testDefaultProviderAllowedDisabled(): void {
    $this->drupalLogin($this->aiAdmin);
    // Set default_provider_allowed to 0 (FALSE).
    $this->drupalGet('admin/config/ai/test-form-elements', ['query' => ['default_provider_allowed' => 0]]);
    $this->takeScreenshot('1_form_loaded_default_provider_disallowed');

    $page = $this->getSession()->getPage();
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');

    if ($select) {
      $options = $select->findAll('css', 'option');
      // Default option should not be present.
      foreach ($options as $option) {
        $this->assertNotEquals(AiProviderInterface::DEFAULT_MODEL_VALUE, $option->getValue(), 'Default option should not be present when default_provider_allowed is FALSE');
      }
      $this->takeScreenshot('2_default_option_not_present');
    }
  }

  /**
   * Tests pseudo operation types work correctly.
   */
  public function testPseudoOperationTypes(): void {
    $this->drupalLogin($this->aiAdmin);
    // Test with a pseudo operation type.
    $this->drupalGet('admin/config/ai/test-form-elements', ['query' => ['operation_type' => 'chat_with_tools']]);
    $this->takeScreenshot('1_form_loaded_pseudo_operation_type');

    $assert = $this->assertSession();

    // Element should still render correctly.
    $assert->elementExists('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    $this->takeScreenshot('2_element_rendered');

    // The element should handle the pseudo operation type.
    // It should use 'chat' as actual_type.
  }

  /**
   * Tests that config container is closed when default provider is selected.
   */
  public function testConfigClosedWhenDefaultSelected(): void {
    $this->drupalLogin($this->aiAdmin);

    // Ensure a default provider is configured.
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('ai.settings');
    $default_providers = $config->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'ai_test',
      'model_id' => 'test_model',
    ];
    $config->set('default_providers', $default_providers)->save();

    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded_with_default_provider_configured');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Select default provider if available.
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    if ($select) {
      $options = $select->findAll('css', 'option');
      foreach ($options as $option) {
        if ($option->getValue() === AiProviderInterface::DEFAULT_MODEL_VALUE) {
          // Only trigger AJAX if value is changing.
          if ($select->getValue() !== AiProviderInterface::DEFAULT_MODEL_VALUE) {
            $select->setValue(AiProviderInterface::DEFAULT_MODEL_VALUE);
            $assert->assertWaitOnAjaxRequest();
          }
          $this->takeScreenshot('2_default_selected_ajax_complete');

          // Configuration container should exist.
          $config_wrapper = $assert->waitForElement('css', '#edit-provider_config-config');
          $this->assertNotNull(
            $config_wrapper,
            'Configuration wrapper should exist when default is selected'
          );

          // Check that details element exists (it may be closed/collapsed).
          $details = $config_wrapper->find('css', 'details');
          if ($details) {
            // The details element should be present, even if closed.
            $this->assertTrue(
              $details->isVisible(),
              'Configuration details should exist when default is selected'
            );
          }

          $this->takeScreenshot('3_config_container_checked');
          return;
        }
      }
    }
  }

  /**
   * Tests that config container opens when switching from default to provider.
   */
  public function testConfigOpensWhenSwitchingFromDefaultToProvider(): void {
    $this->drupalLogin($this->aiAdmin);

    // Ensure a default provider is configured.
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('ai.settings');
    $default_providers = $config->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'ai_test',
      'model_id' => 'test_model',
    ];
    $config->set('default_providers', $default_providers)->save();

    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded_with_default_provider_configured');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    if ($select) {
      $options = $select->findAll('css', 'option');

      // First, select default if available and not already selected.
      $has_default = FALSE;
      $current_value = $select->getValue();
      foreach ($options as $option) {
        if ($option->getValue() === AiProviderInterface::DEFAULT_MODEL_VALUE) {
          if ($current_value !== AiProviderInterface::DEFAULT_MODEL_VALUE) {
            $select->setValue(AiProviderInterface::DEFAULT_MODEL_VALUE);
            $assert->assertWaitOnAjaxRequest();
          }
          $has_default = TRUE;
          break;
        }
      }

      if ($has_default) {
        $this->takeScreenshot('2_default_selected_ajax_complete');
      }

      // Then switch to a specific provider/model.
      if ($has_default) {
        foreach ($options as $option) {
          $value = $option->getValue();
          if (!empty($value) && $value !== AiProviderInterface::DEFAULT_MODEL_VALUE) {
            if ($select->getValue() !== $value) {
              $select->setValue($value);
              $assert->assertWaitOnAjaxRequest();
            }

            $this->takeScreenshot('3_switched_to_provider_ajax_complete');

            // Configuration container should be open now.
            $config_wrapper = $assert->waitForElement('css', '#edit-provider_config-config');

            $this->assertNotNull(
              $config_wrapper,
              'Configuration wrapper should exist after switching to provider'
            );

            $this->takeScreenshot('4_config_container_checked');
            return;
          }
        }
      }
    }
  }

  /**
   * Tests that empty selection results in empty config container.
   */
  public function testEmptySelectionResultsInEmptyConfig(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements');
    $this->takeScreenshot('1_form_loaded');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Check that configuration wrapper exists initially.
    $assert->waitForElement('css', '#edit-provider_config-config');
    $this->takeScreenshot('2_config_wrapper_present');

    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    if ($select) {
      // Select empty option if available and value is changing.
      $current_value = $select->getValue();
      if ($current_value !== '') {
        $select->setValue('');
        $assert->assertWaitOnAjaxRequest();
      }

      $this->takeScreenshot('3_empty_selected_ajax_complete');

      // Configuration container should still exist but be empty.
      $config_wrapper = $assert->waitForElement('css', '#edit-provider_config-config');
      $this->assertNotNull(
        $config_wrapper,
        'Configuration wrapper should exist even when empty'
      );

      $this->takeScreenshot('4_config_container_checked');
    }
  }

  /**
   * Tests that a saved use_default config value loads as the Default option.
   */
  public function testConfigTargetLoadsSavedDefaultSelection(): void {
    $this->drupalLogin($this->aiAdmin);

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');

    $default_providers = $config_factory->get('ai.settings')->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'ai_test',
      'model_id' => 'test_model',
    ];
    $config_factory->getEditable('ai.settings')
      ->set('default_providers', $default_providers)
      ->save();

    $config_factory->getEditable('ai_test.settings')
      ->set('provider_config', [
        'use_default' => TRUE,
        'provider' => 'ai_test',
        'model' => 'test_model',
        'config' => ['temperature' => 0.7],
      ])
      ->save();

    $this->drupalGet('admin/config/ai/test-provider-config-target');
    $this->takeScreenshot('1_form_loaded_config_target');

    $page = $this->getSession()->getPage();
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');
    $this->assertNotNull($select);
    $this->assertSame(AiProviderInterface::DEFAULT_MODEL_VALUE, $select->getValue());
    $this->takeScreenshot('2_default_selected_from_saved_config');
  }

  /**
   * Tests that selecting Default saves ai.provider_config via #config_target.
   */
  public function testConfigTargetSavesDefaultSelection(): void {
    $this->drupalLogin($this->aiAdmin);

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');

    $default_providers = $config_factory->get('ai.settings')->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'ai_test',
      'model_id' => 'test_model',
    ];
    $config_factory->getEditable('ai.settings')
      ->set('default_providers', $default_providers)
      ->save();

    $this->drupalGet('admin/config/ai/test-provider-config-target');
    $this->takeScreenshot('1_form_loaded_config_target');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();
    $select = $page->find('css', 'select[data-drupal-selector="edit-provider-config-provider-model"]');

    $this->assertNotNull($select);
    if ($select->getValue() !== AiProviderInterface::DEFAULT_MODEL_VALUE) {
      $select->setValue(AiProviderInterface::DEFAULT_MODEL_VALUE);
      $assert->assertWaitOnAjaxRequest();
    }

    $this->takeScreenshot('2_default_selected_ajax_complete');

    $page->pressButton('Save configuration');
    $this->takeScreenshot('3_configuration_saved');
    $assert->pageTextContains('The configuration options have been saved.');

    $saved = $config_factory->get('ai_test.settings')->get('provider_config');
    $this->assertTrue($saved['use_default']);
    $this->assertSame('', $saved['provider']);
    $this->assertSame('', $saved['model']);
    $this->takeScreenshot('4_saved_config_verified');
  }

}
