<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms_sendtophone\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\node\Entity\NodeType;
use Drupal\sms\Entity\PhoneNumberSettingsInterface;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms_sendtophone\Plugin\Field\FieldFormatter\SmsLinkFormatter;
use Drupal\sms_sendtophone\Plugin\Filter\FilterInlineSms;
use Drupal\Tests\sms\Functional\SmsFrameworkBrowserTestBase;
use Drupal\sms\Entity\PhoneNumberSettings;

/**
 * Integration tests for the SMS SendToPhone Module.
 *
 * @group SMS Framework
 */
final class SmsSendToPhoneBrowserTest extends SmsFrameworkBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms',
    'sms_sendtophone',
    'sms_test_gateway',
    'node',
    'field',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  private FieldStorageConfigInterface $phoneField;
  private PhoneNumberSettingsInterface $phoneNumberSettings;
  private SmsGatewayInterface $gateway;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]);
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }

    $this->gateway = $this->createMemoryGateway(['skip_queue' => TRUE]);
    $this->setFallbackGateway($this->gateway);

    $this->phoneField = FieldStorageConfig::create([
      'entity_type' => 'user',
      'field_name' => mb_strtolower($this->randomMachineName()),
      'type' => 'telephone',
    ]);
    $this->phoneField->save();

    FieldConfig::create([
      'entity_type' => 'user',
      'bundle' => 'user',
      'field_name' => $this->phoneField->getName(),
    ])->save();

    $this->phoneNumberSettings = PhoneNumberSettings::create();
    $this->phoneNumberSettings
      ->setPhoneNumberEntityTypeId('user')
      ->setPhoneNumberBundle('user')
      ->setFieldName('phone_number', $this->phoneField->getName())
      ->setVerificationMessage($this->randomString())
      ->save();
  }

  /**
   * Tests admin settings page and sendtophone node integration.
   */
  public function testAdminSettingsAndSendToPhone(): void {
    $user = $this->drupalCreateUser(['administer smsframework']);
    $this->drupalLogin($user);

    $this->drupalGet('admin/config/smsframework/sendtophone');
    $edit = [];
    $expected = [];
    foreach (NodeType::loadMultiple() as $type) {
      $this->assertSession()->pageTextContains($type->get('name'));
      if (rand(0, 1) > 0.5) {
        $edit["content_types[" . $type->get('type') . "]"] = $expected[$type->get('type')] = $type->get('type');
      }
    }
    // Ensure at least one type is enabled.
    $edit["content_types[page]"] = $expected['page'] = 'page';
    $this->drupalGet('admin/config/smsframework/sendtophone');
    $this->submitForm($edit, 'Save configuration');
    $saved = $this->config('sms_sendtophone.settings')->get('content_types', []);
    static::assertEquals($expected, $saved);

    // Create a new node with sendtophone enabled and verify that the button is
    // added.
    $types = array_keys(array_filter($expected));
    $node = $this->drupalCreateNode(['type' => $types[0]]);
    $this->drupalGet($node->toUrl());
    // Confirm message for user without confirmed number.
    $this->assertSession()->pageTextContains(t('Set up and confirm your mobile number to send to phone.'));

    // Confirm phone number.
    $phone_number = $this->randomPhoneNumbers(1)[0];
    $user->{$this->phoneField->getName()} = [$phone_number];
    $user->save();
    $this->verifyPhoneNumber($user, $phone_number);

    $this->drupalGet($node->toUrl());
    // Confirm message for user without confirmed number.
    $this->assertSession()->pageTextContains('Send to phone');
    $this->assertSession()->responseContains('Send a link via SMS.');

    // Navigate to the "Send to phone" link.
    $this->clickLink('Send to phone');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->fieldValueEquals('number', $phone_number);
    $this->assertSession()->fieldValueEquals('message_display', $node->toUrl()->setAbsolute()->toString());

    // Click the send button there.
    $this->submitForm(['number' => $phone_number], 'Send');

    $sms_message = $this->getLastTestMessage($this->gateway);
    static::assertTrue(in_array($phone_number, $sms_message->getRecipients()));
    static::assertEquals($sms_message->getMessage(), $node->toUrl()->setAbsolute()->toString());
  }

  /**
   * Tests sendtophone filter integration.
   *
   * @covers \Drupal\sms_sendtophone\Form\SendToPhoneForm
   */
  public function testSendToPhoneFilter(): void {
    $user = $this->drupalCreateUser(['administer filters']);
    $this->drupalLogin($user);

    $edit = [
      'filters[' . FilterInlineSms::PLUGIN_ID . '][status]' => TRUE,
      'filters[' . FilterInlineSms::PLUGIN_ID . '][settings][display]' => 'text',
    ];
    $this->drupalGet('admin/config/content/formats/manage/plain_text');
    $this->submitForm($edit, 'Save configuration');
    // Create a new node sms markup and verify that a link is created.
    $type_names = array_keys(NodeType::loadMultiple());
    $node_body = $this->randomMachineName(30);
    $node = $this->drupalCreateNode([
      'type' => array_pop($type_names),
      'body' => [
        [
          'value' => "[sms]{$node_body}[/sms]",
          'format' => 'plain_text',
        ],
      ],
    ]);

    // Unconfirmed users.
    $this->drupalGet('sms/sendtophone/inline');
    $this->assertSession()->pageTextContains('You need to set up and confirm your mobile phone to send messages');

    // Confirm phone number.
    $phone_number = $this->randomPhoneNumbers(1)[0];
    $user->{$this->phoneField->getName()} = [$phone_number];
    $user->save();
    $this->verifyPhoneNumber($user, $phone_number);

    $this->drupalGet($node->toUrl()->setOption('query', ['text' => $node_body]));
    // Confirm link was created for Send to phone.
    $this->assertSession()->pageTextContains("$node_body (Send to phone)");

    $this->clickLink('(Send to phone)');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($node_body);

    // Submit phone number and confirm message received.
    $this->submitForm([], 'Send');

    $sms_message = $this->getLastTestMessage($this->gateway);
    static::assertEquals($sms_message->getMessage(), $node_body, 'Message body "' . $node_body . '" successfully sent.');
  }

  /**
   * Tests field format integration and widget.
   *
   * @covers \Drupal\sms_sendtophone\Form\SendToPhoneForm
   */
  public function testFieldFormatAndWidget(): void {
    // Create a custom field of type 'text' using the sms_sendtophone formatter.
    $bundles = array_keys(NodeType::loadMultiple());
    $field_name = mb_strtolower($this->randomMachineName());
    $field_definition = [
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $bundles[0],
    ];
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'text',
    ]);
    $field_storage->save();
    $field = FieldConfig::create($field_definition);
    $field->save();

    $display = EntityViewDisplay::load('node.' . $bundles[0] . '.default');
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $bundles[0],
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $display->setComponent($field_name)->save();
    $random_text = $this->randomMachineName(32);
    $test_node = $this->drupalCreateNode([
      'type' => $bundles[0],
      $field_name => [[
        'value' => $random_text,
      ],
      ],
    ]);

    // This is a quick-fix. Need to find out how to add display filters in code.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->submitForm([
      'fields[' . $field_name . '][type]' => SmsLinkFormatter::PLUGIN_ID,
    ], 'Save');

    // Confirm phone number.
    $user = $this->drupalCreateUser();
    $phone_number = $this->randomPhoneNumbers(1)[0];
    $user->{$this->phoneField->getName()} = [$phone_number];
    $user->save();
    $this->verifyPhoneNumber($user, $phone_number);
    $this->drupalLogin($user);

    // Click send button.
    $this->drupalGet($test_node->toUrl()->setOption('query', ['text' => $random_text]));
    $this->assertSession()->pageTextContains($random_text);
    $this->assertSession()->pageTextContains($random_text . ' (Send to phone)');
    $this->clickLink('Send to phone');

    // Click the send button there.
    $this->submitForm([], 'Send');

    $sms_message = $this->getLastTestMessage($this->gateway);
    static::assertTrue(in_array($phone_number, $sms_message->getRecipients()), 'Message sent to correct number');
    static::assertEquals($sms_message->getMessage(), $random_text, 'Field content sent to user');
  }

}
