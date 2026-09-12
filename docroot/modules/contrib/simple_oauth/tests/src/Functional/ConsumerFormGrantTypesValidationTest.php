<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\consumers\Entity\Consumer;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests consumer form grant type handling when updating secret.
 *
 * @group simple_oauth
 */
class ConsumerFormGrantTypesValidationTest extends BrowserTestBase {

  use SimpleOauthTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'consumers',
    'simple_oauth',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An administrator user for this test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpKeys();

    $this->drupalPlaceBlock('page_title_block');

    $this->adminUser = $this->drupalCreateUser([
      'administer consumer entities',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests saving a consumer with a new secret keeps grant types valid.
   */
  public function testSaveConsumerWithNewSecret(): void {
    $consumer = Consumer::create([
      'label' => 'Grant Types Save Test',
      'client_id' => 'grant_types_save_test',
      'grant_types' => [
        'refresh_token',
        'authorization_code',
        'client_credentials',
      ],
      'redirect' => ['http://localhost'],
      'user_id' => $this->adminUser->id(),
      'secret' => 'initial-secret',
    ]);
    $consumer->save();

    $this->drupalGet("/admin/config/services/consumer/{$consumer->id()}/edit");
    $assert_session = $this->assertSession();

    // Ensure grant type options are rendered.
    $assert_session->fieldExists('grant_types[refresh_token]');
    $assert_session->fieldExists('grant_types[authorization_code]');
    $assert_session->fieldExists('grant_types[client_credentials]');

    $updated_secret = 'updated-secret';
    $this->submitForm([
      'new_secret' => $updated_secret,
    ], 'Save');

    $assert_session->pageTextNotContains('Grant types field is required.');

    /** @var \Drupal\consumers\Entity\Consumer $saved */
    $saved = Consumer::load($consumer->id());
    $this->assertNotNull($saved);
    $this->assertTrue(password_verify($updated_secret, $saved->get('secret')->value));

    $grant_types = array_column($saved->get('grant_types')->getValue(), 'value');
    sort($grant_types);
    $this->assertSame(['authorization_code', 'client_credentials', 'refresh_token'], $grant_types);
  }

}
