<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\Plugin\views\field;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Plugin\views\field\GetText;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\ResultRow;
use Drupal\views\Views;

/**
 * Kernel tests for the GetText views field handler.
 *
 * @coversDefaultClass \Drupal\message\Plugin\views\field\GetText
 *
 * @group Message
 */
class GetTextTest extends KernelTestBase {

  use MessageTemplateCreateTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'message',
    'message_test',
    'system',
    'user',
    'views',
  ];

  /**
   * A message entity used for render tests.
   *
   * @var \Drupal\message\Entity\Message
   */
  protected Message $message;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message', 'message_test', 'system', 'user', 'views']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');

    $template = $this->createMessageTemplate(
      'get_text_tpl',
      'Get text template',
      'Description',
      [
        'First partial',
        'Second partial',
      ]
    );

    $this->message = Message::create([
      'template' => $template->id(),
      'uid' => $this->createUser()->id(),
    ]);
    $this->message->save();
  }

  /**
   * Tests that message_views_data_alter() registers the get_text field.
   */
  public function testViewsDataAlter(): void {
    $views_data = $this->container->get('views.views_data')->get('message');
    $this->assertArrayHasKey('get_text', $views_data);
    $this->assertEquals('get_text', $views_data['get_text']['field']['id']);
  }

  /**
   * Tests query is a no-op and options form exposes delta.
   *
   * @covers ::query
   * @covers ::defineOptions
   * @covers ::buildOptionsForm
   */
  public function testQueryAndOptionsForm(): void {
    $view = Views::getView('message_test');
    $view->setDisplay('default');
    $view->initHandlers();

    /** @var \Drupal\message\Plugin\views\field\GetText $plugin */
    $plugin = $view->field['get_text'];
    $this->assertInstanceOf(GetText::class, $plugin);
    $plugin->query();

    $form = [];
    $form_state = new FormState();
    $plugin->buildOptionsForm($form, $form_state);
    $this->assertArrayHasKey('delta', $form);
    $this->assertEquals('textfield', $form['delta']['#type']);
  }

  /**
   * Tests rendering all text and a specific delta.
   *
   * @covers ::render
   */
  public function testRender(): void {
    $row = new ResultRow();
    $row->_entity = $this->message;

    $all = $this->createGetTextPlugin(['delta' => '']);
    $rendered = $all->render($row);
    $this->assertInstanceOf(FormattableMarkup::class, $rendered);
    $this->assertStringContainsString('First partial', (string) $rendered);
    $this->assertStringContainsString('Second partial', (string) $rendered);

    $delta = $this->createGetTextPlugin(['delta' => '0']);
    $rendered_delta = $delta->render($row);
    $this->assertStringContainsString('First partial', (string) $rendered_delta);
    $this->assertStringNotContainsString('Second partial', (string) $rendered_delta);
  }

  /**
   * Tests the field through the installed message_test view.
   *
   * @covers ::render
   */
  public function testViewExecution(): void {
    $view = Views::getView('message_test');
    $this->assertNotNull($view);
    $this->assertTrue($view->storage->status());

    $view->setDisplay('default');
    $view->execute();
    $this->assertNotEmpty($view->result);

    $output = $view->render('default');
    $rendered = (string) $this->container->get('renderer')->renderRoot($output);
    $this->assertStringContainsString('First partial', $rendered);
  }

  /**
   * Creates a GetText plugin instance with the given options.
   *
   * @param array $options
   *   Plugin options.
   *
   * @return \Drupal\message\Plugin\views\field\GetText
   *   The plugin instance.
   */
  protected function createGetTextPlugin(array $options = []): GetText {
    $view = Views::getView('message_test');
    $view->setDisplay('default');

    $handler_options = $options + [
      'id' => 'get_text',
      'table' => 'message',
      'field' => 'get_text',
      'delta' => '',
    ];

    /** @var \Drupal\message\Plugin\views\field\GetText $plugin */
    $plugin = $this->container->get('plugin.manager.views.field')
      ->createInstance('get_text');
    $plugin->init($view, $view->getDisplay(), $handler_options);
    return $plugin;
  }

}
