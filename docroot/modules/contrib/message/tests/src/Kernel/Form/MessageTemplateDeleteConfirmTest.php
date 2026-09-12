<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message\Form\MessageTemplateDeleteConfirm;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;

/**
 * Kernel tests for the message template delete confirm form.
 *
 * @coversDefaultClass \Drupal\message\Form\MessageTemplateDeleteConfirm
 *
 * @group Message
 */
class MessageTemplateDeleteConfirmTest extends KernelTestBase {

  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'message', 'system', 'user'];

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
   * Builds the delete confirm form object for a template.
   *
   * @param \Drupal\message\Entity\MessageTemplate $entity
   *   The message template.
   *
   * @return \Drupal\message\Form\MessageTemplateDeleteConfirm
   *   The form object.
   */
  protected function getDeleteForm(MessageTemplate $entity): MessageTemplateDeleteConfirm {
    /** @var \Drupal\message\Form\MessageTemplateDeleteConfirm $form_object */
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('message_template', 'delete');
    $form_object->setEntity($entity);
    $form_object->setModuleHandler($this->container->get('module_handler'));
    $form_object->setEntityTypeManager($this->container->get('entity_type.manager'));
    return $form_object;
  }

  /**
   * Tests deleting an unused message template.
   *
   * @covers ::getQuestion
   * @covers ::getConfirmText
   * @covers ::getCancelUrl
   * @covers ::buildForm
   * @covers ::submitForm
   */
  public function testDeleteUnusedTemplate(): void {
    $template = $this->createMessageTemplate(
      'unused_kernel',
      'Unused kernel',
      'Not in use',
      ['Hello']
    );
    $form_object = $this->getDeleteForm($template);

    $this->assertEquals('Delete', (string) $form_object->getConfirmText());
    $this->assertStringContainsString('Unused kernel', (string) $form_object->getQuestion());
    $this->assertEquals('message.overview_templates', $form_object->getCancelUrl()->getRouteName());

    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_object, $form_state);
    $this->assertArrayHasKey('actions', $form);
    $this->assertArrayHasKey('submit', $form['actions']);

    $form_state = new FormState();
    $form_state->setValues([
      '_triggering_element_name' => 'op',
      '_triggering_element_value' => 'Delete',
      'op' => 'Delete',
      'confirm' => 1,
    ]);
    $this->formBuilder->submitForm($form_object, $form_state);
    $this->assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $this->assertNull(MessageTemplate::load('unused_kernel'));
    $messages = \Drupal::messenger()->messagesByType('status');
    $this->assertStringContainsString('Unused kernel', (string) $messages[0]);
    $this->assertStringContainsString('has been deleted', (string) $messages[0]);
    // Programmed form submissions skip redirects via FormState::getRedirect().
  }

  /**
   * Tests that templates still in use cannot be deleted.
   *
   * @covers ::buildForm
   */
  public function testDeleteBlockedWhenMessagesExist(): void {
    $template = $this->createMessageTemplate(
      'used_kernel',
      'Used kernel',
      'In use',
      ['Hello']
    );
    Message::create(['template' => 'used_kernel'])->save();
    Message::create(['template' => 'used_kernel'])->save();

    $form_object = $this->getDeleteForm($template);
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm($form_object, $form_state);

    $this->assertArrayNotHasKey('actions', $form);
    $this->assertArrayHasKey('description', $form);
    $this->assertStringContainsString('used by 2 messages', (string) $form['description']['#markup']);
    $this->assertNotNull(MessageTemplate::load('used_kernel'));
  }

}
