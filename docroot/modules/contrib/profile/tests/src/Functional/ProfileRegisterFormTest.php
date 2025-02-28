<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Tests attaching of profile entity forms to other forms.
 *
 * @group profile
 */
class ProfileRegisterFormTest extends ProfileTestBase {

  /**
   * Test user registration integration.
   */
  public function testUserRegisterForm() {
    $id = $this->type->id();
    $field_name = $this->field->getName();

    $this->field->setRequired(TRUE);
    $this->field->save();

    // Allow registration without administrative approval and log in user
    // directly after registering.
    \Drupal::configFactory()->getEditable('user.settings')
      ->set('register', UserInterface::REGISTER_VISITORS)
      ->set('verify_mail', 0)
      ->save();
    user_role_grant_permissions(AccountInterface::AUTHENTICATED_ROLE, ['view own test profile']);

    $this->drupalGet('user/register');
    // Verify that the additional profile field is attached and required.
    $name = $this->randomMachineName();
    $pass_raw = $this->randomMachineName();
    // Use existing email to verify normal validation happens.
    $edit = [
      'name' => $name,
      'mail' => $this->adminUser->getEmail(),
      'pass[pass1]' => $pass_raw,
      'pass[pass2]' => $pass_raw,
    ];
    $this->submitForm($edit, 'Create new account');

    $this->assertSession()->pageTextContains(new FormattableMarkup('@name field is required.', ['@name' => $this->field->getLabel()]));
    $this->assertSession()->pageTextContains(new FormattableMarkup('The email address @email is already taken.', ['@email' => $this->adminUser->getEmail()]));

    // Verify that we can register.
    $edit['mail'] = $this->randomMachineName() . '@example.com';
    $edit[$id . "_profiles[0][entity][$field_name][0][value]"] = $this->randomMachineName();
    $this->submitForm($edit, 'Create new account');
    $this->assertSession()->pageTextContains(new FormattableMarkup('Registration successful. You are now logged in.', []));

    $new_user = user_load_by_name($name);
    $this->assertTrue($new_user->isActive(), 'New account is active after registration.');

    /** @var \Drupal\profile\ProfileStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('profile');

    // Verify that a new profile was created for the new user ID.
    $profile = $storage->loadByUser($new_user, $this->type->id());

    $this->assertEquals($profile->get($field_name)->value, $edit[$id . "_profiles[0][entity][$field_name][0][value]"], 'Field value found in loaded profile.');
    // Verify that, as the first profile of this type for the user, it was set
    // as default.
    $this->assertTrue($profile->isDefault());

    // Verify that the profile field value appears on the user account page.
    $this->drupalGet($profile->toUrl());
    $this->assertSession()->pageTextContains($edit[$id . "_profiles[0][entity][$field_name][0][value]"]);
  }

}
