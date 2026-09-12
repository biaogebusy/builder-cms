<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message\Form\MessageTemplateForm;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;

/**
 * Kernel tests for the message template add/edit form.
 *
 * @coversDefaultClass \Drupal\message\Form\MessageTemplateForm
 *
 * @group Message
 */
class MessageTemplateFormTest extends KernelTestBase {

  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'message',
    'system',
    'user',
  ];

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message', 'system', 'user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->formBuilder = $this->container->get('form_builder');
  }

  /**
   * Builds an entity form object for the given operation.
   *
   * @param string $operation
   *   The form operation.
   * @param \Drupal\message\Entity\MessageTemplate $entity
   *   The message template entity.
   *
   * @return \Drupal\message\Form\MessageTemplateForm
   *   The form object.
   */
  protected function getTemplateForm(string $operation, MessageTemplate $entity): MessageTemplateForm {
    /** @var \Drupal\message\Form\MessageTemplateForm $form_object */
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('message_template', $operation);
    $form_object->setEntity($entity);
    $form_object->setModuleHandler($this->container->get('module_handler'));
    $form_object->setEntityTypeManager($this->container->get('entity_type.manager'));
    return $form_object;
  }

  /**
   * Returns default settings values for the template form.
   *
   * @param bool $purge_override
   *   Whether purge override is enabled.
   * @param bool $quota_enabled
   *   Whether the quota purge method is enabled.
   *
   * @return array
   *   Settings form values.
   */
  protected function settingsValues(bool $purge_override = FALSE, bool $quota_enabled = FALSE): array {
    $settings = [
      'token options' => [
        'clear' => 1,
        'token replace' => 1,
      ],
      // Explicit NULL unchecks programmed checkboxes (omit/FALSE uses default).
      'purge_override' => $purge_override ? 1 : NULL,
      'purge_methods' => [
        'quota' => [
          'enabled' => $quota_enabled ? 1 : NULL,
          'weight' => 0,
          'data' => [],
        ],
        'days' => [
          'enabled' => NULL,
          'weight' => 1,
          'data' => [],
        ],
      ],
    ];
    return $settings;
  }

  /**
   * Submits the template form with the save button as triggering element.
   *
   * @param \Drupal\message\Form\MessageTemplateForm $form_object
   *   The form object.
   * @param array $values
   *   Form values.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state after submission.
   */
  protected function submitTemplateForm(MessageTemplateForm $form_object, array $values): FormStateInterface {
    $values += [
      '_triggering_element_name' => 'op',
      '_triggering_element_value' => 'Save message template',
      'op' => 'Save message template',
    ];
    $form_state = new FormState();
    $form_state->setValues($values);
    $this->formBuilder->submitForm($form_object, $form_state);
    return $form_state;
  }

  /**
   * Tests creating a message template via the form.
   *
   * @covers ::form
   * @covers ::actions
   * @covers ::submitForm
   * @covers ::save
   * @covers ::create
   */
  public function testCreateTemplate(): void {
    $entity = MessageTemplate::create([]);
    $form_object = $this->getTemplateForm('add', $entity);

    $form_state = $this->submitTemplateForm($form_object, [
      'label' => 'Kernel template',
      'template' => 'kernel_template',
      'description' => 'Created in kernel test',
      'text' => [
        [
          'value' => 'Hello kernel',
          'format' => filter_default_format(),
          '_weight' => 0,
        ],
      ],
      'settings' => $this->settingsValues(),
    ]);
    $this->assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $template = MessageTemplate::load('kernel_template');
    $this->assertNotNull($template);
    $this->assertEquals('Kernel template', $template->label());
    $this->assertEquals('Created in kernel test', $template->getDescription());
    $this->assertTrue($template->getSetting('token options')['clear']);
    $this->assertEquals([], $template->getSetting('purge_methods', []));

    $messages = \Drupal::messenger()->messagesByType('status');
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('created successfully', (string) $messages[0]);
    // Programmed form submissions skip redirects via FormState::getRedirect().
  }

  /**
   * Tests updating a message template via the form.
   *
   * @covers ::save
   */
  public function testUpdateTemplate(): void {
    $template = $this->createMessageTemplate(
      'update_template',
      'Original label',
      'Original description',
      ['Original text']
    );
    $form_object = $this->getTemplateForm('edit', $template);

    $form_state = $this->submitTemplateForm($form_object, [
      'label' => 'Updated label',
      'template' => 'update_template',
      'description' => 'Updated description',
      'text' => [
        [
          'value' => 'Updated text',
          'format' => filter_default_format(),
          '_weight' => 0,
        ],
      ],
      'settings' => $this->settingsValues(),
    ]);
    $this->assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $template = MessageTemplate::load('update_template');
    $this->assertEquals('Updated label', $template->label());
    $this->assertEquals('Updated description', $template->getDescription());

    $messages = \Drupal::messenger()->messagesByType('status');
    $this->assertStringContainsString('has been updated', (string) $messages[0]);
  }

  /**
   * Tests purge override settings on the template form.
   *
   * @covers ::submitForm
   */
  public function testPurgeOverride(): void {
    $template = $this->createMessageTemplate(
      'purge_template',
      'Purge template',
      'Purge description',
      ['Text']
    );
    $form_object = $this->getTemplateForm('edit', $template);

    $form_state = $this->submitTemplateForm($form_object, [
      'label' => 'Purge template',
      'template' => 'purge_template',
      'description' => 'Purge description',
      'text' => [
        [
          'value' => 'Text',
          'format' => filter_default_format(),
          '_weight' => 0,
        ],
      ],
      'settings' => $this->settingsValues(TRUE, TRUE),
      'quota' => 5,
    ]);
    $this->assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $template = MessageTemplate::load('purge_template');
    $this->assertTrue((bool) $template->getSetting('purge_override'));
    $this->assertArrayHasKey('quota', $template->getSetting('purge_methods'));
    $this->assertEquals(5, $template->getSetting('purge_methods')['quota']['data']['quota']);

    // Disable override and confirm purge methods are cleared.
    $form_object = $this->getTemplateForm('edit', $template);
    $form_state = $this->submitTemplateForm($form_object, [
      'label' => 'Purge template',
      'template' => 'purge_template',
      'description' => 'Purge description',
      'text' => [
        [
          'value' => 'Text',
          'format' => filter_default_format(),
          '_weight' => 0,
        ],
      ],
      'settings' => $this->settingsValues(FALSE, TRUE),
      'quota' => 5,
    ]);
    $this->assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $template = MessageTemplate::load('purge_template');
    $this->assertFalse((bool) $template->getSetting('purge_override'));
    $this->assertEquals([], $template->getSetting('purge_methods', []));
  }

  /**
   * Tests that text partials are sorted by weight on save.
   *
   * @covers ::save
   */
  public function testTextWeightSorting(): void {
    $template = $this->createMessageTemplate(
      'weight_template',
      'Weight template',
      'Weight description',
      ['First', 'Second']
    );
    $form_object = $this->getTemplateForm('edit', $template);

    $form_state = $this->submitTemplateForm($form_object, [
      'label' => 'Weight template',
      'template' => 'weight_template',
      'description' => 'Weight description',
      'text' => [
        [
          'value' => 'First',
          'format' => filter_default_format(),
          '_weight' => 1,
        ],
        [
          'value' => 'Second',
          'format' => filter_default_format(),
          '_weight' => 0,
        ],
      ],
      'settings' => $this->settingsValues(),
    ]);
    $this->assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $template = MessageTemplate::load('weight_template');
    $text = array_values($template->get('text'));
    $this->assertEquals('Second', $text[0]['value']);
    $this->assertEquals('First', $text[1]['value']);
    $this->assertArrayNotHasKey('_weight', $text[0]);
    $this->assertArrayNotHasKey('_weight', $text[1]);
  }

  /**
   * Tests the Ajax callback for adding another text item.
   *
   * @covers ::addMoreAjax
   */
  public function testAddMoreAjax(): void {
    $form = [
      'text' => [
        '#markup' => 'text element',
      ],
    ];
    $result = MessageTemplateForm::addMoreAjax($form, new FormState());
    $this->assertEquals($form['text'], $result);
  }

  /**
   * Tests the form builds token tree markup when Token is enabled.
   *
   * @covers ::form
   */
  public function testFormWithTokenModule(): void {
    $this->container->get('module_installer')->install(['token']);
    $this->container = $this->container->get('kernel')->getContainer();
    $this->formBuilder = $this->container->get('form_builder');

    $entity = MessageTemplate::create([]);
    $form_object = $this->getTemplateForm('add', $entity);
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_object, $form_state);

    $this->assertArrayHasKey('token_tree', $form);
    $this->assertEquals('token_tree_link', $form['token_tree']['#theme']);
  }

}
