<?php

namespace Drupal\Tests\flag\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Tests modal form options for action link plugins.
 *
 * @group flag
 */
class ModalFormTest extends WebDriverTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flag', 'node', 'user'];

  /**
   * Flag to test with.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin;

  /**
   * Normal user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // A test flag.
    $this->flag = $this->createFlag('node', [], 'confirm');
    $this->flagService = $this->container->get('flag');

    // A node to test with.
    $this->admin = $this->createUser([], NULL, TRUE);
    $type = $this->createContentType();
    $this->node = $this->createNode([
      'type' => $type->id(),
      'uid' => $this->admin->id(),
    ]);

    $this->webUser = $this->createUser(array_keys($this->flag->actionPermissions()));
    $this->drupalLogin($this->webUser);
  }

  /**
   * Tests the modal form option for confirm and field entry link types.
   */
  public function testModalOption() {
    // Verify default, non-modal behavior.
    $this->drupalGet($this->node->toUrl());
    $this->clickLink($this->flag->getShortText('flag'));

    // Should be on the confirm form page, since this isn't using a modal.
    $expected = Url::fromRoute('flag.confirm_flag', [
      'flag' => $this->flag->id(),
      'entity_id' => $this->node->id(),
    ]);
    $this->assertSession()->addressEquals($expected->getInternalPath());
    $this->assertSession()->buttonExists(t('Create flagging'))->press();
    $this->assertSession()->addressEquals($this->node->toUrl());

    // Unflag.
    $this->clickLink($this->flag->getShortText('unflag'));
    $expected = Url::fromRoute('flag.confirm_unflag', [
      'flag' => $this->flag->id(),
      'entity_id' => $this->node->id(),
    ]);
    $this->assertSession()->addressEquals($expected->getInternalPath());
    $this->assertSession()->buttonExists(t('Delete flagging'))->press();
    $this->assertSession()->addressEquals($this->node->toUrl());

    // Set the modal option for the 'confirm' link.
    $configuration = $this->flag->getLinkTypePlugin()->getConfiguration();
    $configuration['form_behavior'] = 'modal';
    $this->flag->getLinkTypePlugin()->setConfiguration($configuration);
    $this->flag->save();

    $this->drupalGet($this->node->toUrl());
    $this->clickLink($this->flag->getShortText('flag'));
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Should still be on the node url, as this is using a modal.
    $this->assertSession()->addressEquals($this->node->toUrl()
      ->getInternalPath());
    // Note, there is some odd behavior calling the `press()` method on the
    // button, so after asserting it exists, click via this method.
    $this->assertSession()->buttonExists(t('Create flagging'));
    $this->click('button:contains("Create flagging")');
    $this->assertSession()->addressEquals($this->node->toUrl()
      ->getInternalPath());

    // Unflag.
    $this->clickLink($this->flag->getShortText('unflag'));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->addressEquals($this->node->toUrl()
      ->getInternalPath());
    $this->assertSession()->buttonExists(t('Delete flagging'));
    $this->click('button:contains("Delete flagging")');
    $this->assertSession()->addressEquals($this->node->toUrl()
      ->getInternalPath());
  }

}
