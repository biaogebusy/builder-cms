<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_content_suggestions\Kernel;

use Drupal\ai_content_suggestions\AiContentSuggestionsInterface;
use Drupal\ai_content_suggestions\Plugin\AiContentSuggestions\Summarise;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests getPluginResponse() by building the node add form for the 'page' type.
 *
 * @group ai_content_suggestions
 */
class GetPluginResponseTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'ai_content_suggestions',
    'node',
    'field',
    'text',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    // Create the 'page' content type.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // Set a current user that has the required AI suggestion permission.
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(
        fn(string $permission) => $permission === 'access ai content suggestion tools',
      );
    \Drupal::currentUser()->setAccount($account);

    // Enable the summarise plugin and allow it on all node bundles.
    $this->container->get('config.factory')
      ->getEditable('ai_content_suggestions.settings')
      ->set('plugins', ['summarise' => 'echoai__gpt-test'])
      ->set('entity_types', ['node' => ['mode' => 'disable', 'bundles' => []]])
      ->save();
  }

  /**
   * Returns a real Summarise plugin instance from the container.
   */
  private function getSummarisePlugin(): AiContentSuggestionsInterface {
    return \Drupal::service('plugin.manager.ai_content_suggestions')
      ->createInstance('summarise');
  }

  /**
   * Builds and returns the node add form for the 'page' content type.
   *
   * Passes a minimal form through the form alter service, which mirrors what
   * hook_form_alter does on a real content entity form.
   *
   * @return array
   *   The altered form array.
   */
  private function buildPageNodeAddForm(): array {
    $node = Node::create(['type' => 'page', 'uid' => 0]);

    $form_object = $this->createMock(ContentEntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($node);

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getFormObject')->willReturn($form_object);

    // Minimal form structure with a title field to give getAllTextFields() a
    // field to discover.
    $form = ['title' => ['#type' => 'textfield', '#title' => 'Title']];

    \Drupal::service('ai_content_suggestions.form_alter')->alter($form, $form_state);

    return $form;
  }

  /**
   * Tests that the Summarise plugin's alterForm is called on the node add form.
   *
   * Verifies that the form alter service triggers alterForm on the Summarise
   * plugin, which adds the plugin's top-level element to the form.
   */
  public function testAlterFormIsCalledOnPageNodeAddForm(): void {
    $form = $this->buildPageNodeAddForm();

    $this->assertArrayHasKey('summarise', $form,
      'The Summarise plugin alterForm method was called and added its element to the node form.');
  }

  /**
   * Tests the summarise_submit button has an ajax callback 'getPluginResponse'.
   *
   * After alterForm runs, the form must contain a 'summarise_submit' button
   * whose #ajax callback points to the Summarise plugin's getPluginResponse
   * method.
   */
  public function testSummariseSubmitButtonHasAjaxCallbackToGetPluginResponse(): void {
    $form = $this->buildPageNodeAddForm();

    $this->assertArrayHasKey('summarise_submit', $form['summarise'],
      'The summarise_submit button is present in the form.');

    $button = $form['summarise']['summarise_submit'];
    $this->assertSame('button', $button['#type']);
    $this->assertArrayHasKey('#ajax', $button,
      'The summarise_submit button has an #ajax configuration.');

    $callback = $button['#ajax']['callback'];
    $this->assertIsArray($callback,
      'The #ajax callback is a PHP callable array.');
    $this->assertInstanceOf(Summarise::class, $callback[0],
      'The #ajax callback is bound to an instance of the Summarise plugin.');
    $this->assertSame('getPluginResponse', $callback[1],
      'The #ajax callback method is getPluginResponse.');
  }

  /**
   * Tests that clicking the summarise_submit button invokes getPluginResponse.
   *
   * Simulates an AJAX submit by invoking the button's #ajax callback directly
   * with a form state that identifies the Summarise plugin as the trigger.
   * Verifies that the response section of the form is returned.
   */
  public function testSummariseSubmitInvokesGetPluginResponse(): void {
    $form = $this->buildPageNodeAddForm();

    // Simulate the form state produced when the summarise_submit button is
    // clicked.
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getTriggeringElement')
      ->willReturn(['#plugin' => 'summarise']);
    $form_state->method('getValue')
      ->willReturnCallback(static function ($key) {
        return $key === 'summarise' ? ['target_fields' => []] : NULL;
      });

    // Invoke the button's #ajax callback exactly as Drupal would on submit.
    $callback = $form['summarise']['summarise_submit']['#ajax']['callback'];
    $result = call_user_func($callback, $form, $form_state);

    // getPluginResponse must return the plugin's response container.
    $this->assertIsArray($result);
    $this->assertArrayHasKey('response', $result);
  }

  /**
   * Tests that getPluginResponse calls updateFormWithResponse.
   */
  public function testGetPluginResponseReturnsResponseSection(): void {
    $plugin_id = 'summarise';

    $form = [
      $plugin_id => [
        'response' => [
          'response' => [
            '#type' => 'inline_template',
            '#context' => ['response' => []],
          ],
        ],
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getTriggeringElement')
      ->willReturn(['#plugin' => $plugin_id]);
    $form_state->method('getValue')
      ->willReturnCallback(static function ($key) {
        return $key === 'summarise' ? ['target_fields' => []] : NULL;
      });

    $result = $this->getSummarisePlugin()->getPluginResponse($form, $form_state);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('response', $result);
    $this->assertArrayHasKey('#context', $result['response']);
    $this->assertArrayHasKey('response', $result['response']['#context']['response']);
  }

  /**
   * Tests that the response contains the "no text" when fields are empty.
   */
  public function testResponseContainsNoTextMessageWhenFieldsAreEmpty(): void {
    $plugin_id = 'summarise';

    $form = [
      $plugin_id => [
        'response' => [
          'response' => [
            '#type' => 'inline_template',
            '#context' => ['response' => []],
          ],
        ],
      ],
    ];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getTriggeringElement')
      ->willReturn(['#plugin' => $plugin_id]);
    $form_state->method('getValue')
      ->willReturnCallback(static function ($key) {
        return $key === 'summarise' ? ['target_fields' => []] : NULL;
      });

    $result = $this->getSummarisePlugin()->getPluginResponse($form, $form_state);

    $markup = (string) $result['response']['#context']['response']['response']['#markup'];
    $this->assertStringContainsString('no text', $markup);
  }

}
