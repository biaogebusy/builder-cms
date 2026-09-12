<?php

namespace Drupal\Tests\tour\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Tour UI.
 */
#[Group('tour')]
class TourUITest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config', 'tour_test'];


  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Returns info for the test.
   */
  public static function getInfo(): array {
    return [
      'name' => 'Tour UI',
      'description' => 'Tests the Tour UI.',
      'group' => 'Tour',
    ];
  }

  /**
   * Tests the listing and editing of a tour.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUi(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer tour', 'export configuration']));

    $this->listTest();
    $this->editTest();
    $this->tipTest();
    $this->cloneTest();
    $this->exportConfigTest();
  }

  /**
   * Tests the listing of a tour.
   */
  protected function listTest(): void {
    $this->drupalGet('admin/config/user-interface/tour');

    // The first column contains the label.
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[1]', [':class' => 'tip_edit']);
    $this->assertSame($elements[0]->getText(), 'Edit tip');

    // The second column contains the machine_name.
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[2]', [':class' => 'tip_edit']);
    $this->assertSame($elements[0]->getText(), 'tip_edit');

    // The third column contains the status (for now).
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[3]', [':class' => 'tip_edit']);
    $this->assertSame($elements[0]->getText(), 'Enabled');

    // Test the disable/enable feature.
    $this->click('a[href*="admin/config/user-interface/tour/manage/tip_edit/disable"]');
    $this->drupalGet('admin/config/user-interface/tour');
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[3]', [':class' => 'tip_edit']);
    $this->assertSame($elements[0]->getText(), 'Disabled');

    $this->click('a[href*="admin/config/user-interface/tour/manage/tip_edit/enable"]');
    $this->drupalGet('admin/config/user-interface/tour');
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[3]', [':class' => 'tip_edit']);
    $this->assertSame($elements[0]->getText(), 'Enabled');

    // The fourth column contains the number of tips.
    $elements = $this->xpath('//table/tbody/tr[contains(@class, :class)]/td[4]', [':class' => 'tip_edit']);
    $this->assertSame($elements[0]->getText(), '4', 'Tour UI - tip-edit has 4 tips.');
  }

  /**
   * Tests the editing of a tour.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function editTest(): void {
    // Create a new tour. Ensure that it comes before the test tours.
    $label = 'a' . $this->randomString();
    $edit = [
      'label' => $label,
      'id' => strtolower($this->randomMachineName()),
      'module' => strtolower($this->randomMachineName()),
      'routes' => '<front>',
    ];
    $this->drupalGet('admin/config/user-interface/tour/add');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains($this->t('The tour %tour has been created.', ['%tour' => $edit['label']]));

    // Edit and re-save an existing tour.
    $this->assertSession()->titleEquals('Edit ' . $label . ' tour | Drupal');

    $this->submitForm([], 'Save');
    $this->assertSession()->responseContains($this->t('The tour %tour has been updated.', ['%tour' => $edit['label']]));

    // Reorder the tour tips.
    $this->drupalGet('admin/config/user-interface/tour/manage/tip_edit/tips');
    $elements = $this->xpath('//table/tbody/tr');
    $this->assertEquals(5, count($elements));

    $weights = [
      'tips[tour_page][weight]' => '2',
      'tips[tour_label][weight]' => '1',
    ];
    $this->submitForm($weights, 'Save');
    $elements = $this->xpath('//tr[contains(@class, "draggable")]/td[contains(text(), "Label")]');
    $this->assertEquals(1, count($elements), 'Found tip "Label".');

    $weights = [
      'tips[tour_page][weight]' => '1',
      'tips[tour_label][weight]' => '2',
    ];
    $this->submitForm($weights, 'Save');
    $elements = $this->xpath('//tr[contains(@class, "draggable")]/td[contains(text(), "Tour edit")]');
    $this->assertEquals(1, count($elements), 'Found odd tip "Tour edit".');

    $this->drupalGet('admin/config/user-interface/tour/add');

    // Attempt to create a duplicate tour.
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains($this->t('The machine-readable name is already in use. It must be unique.'));

    // Delete a tour.
    $this->drupalGet('admin/config/user-interface/tour/manage/' . $edit['id']);
    $this->clickLink('Delete');
    $this->assertSession()->responseContains($this->t('Are you sure you want to delete the tour %tour?', ['%tour' => $edit['label']]));
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains($this->t('The tour %tour has been deleted.', ['%tour' => $edit['label']]));
  }

  /**
   * Tests the add/edit/delete of a tour tip.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function tipTest(): void {
    // Create a new tour for tips to be added to.
    $edit = [
      'label' => 'a' . $this->randomString(),
      'id' => strtolower($this->randomMachineName()),
      'module' => $this->randomString(),
      'routes' => '<front>',
    ];
    $this->drupalGet('admin/config/user-interface/tour/add');
    $this->submitForm($edit, 'Save');

    $this->assertSession()->responseContains($this->t('The tour %tour has been created.', ['%tour' => $edit['label']]));

    $this->drupalGet('admin/config/user-interface/tour/manage/' . $edit['id'] . '/tips');
    // Add a new tip.
    $new = [
      'new' => 'text',
    ];
    $this->submitForm($new, 'Add');

    $tip = [
      'label' => 'a' . $this->randomMachineName(),
      'id' => 'tour_ui_test_image_tip',
      'body' => $this->randomString(),
    ];
    $this->submitForm($tip, 'Save');

    $this->assertSession()->addressEquals('admin/config/user-interface/tour/manage/' . $edit['id'] . '/tips');
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', [
      ':class' => 'draggable',
      ':text' => $tip['label'],
    ]);
    $this->assertEquals(1, count($elements), 'Found tip "' . $tip['label'] . '".');

    // Edit the tip.
    $tip_id = $tip['id'];
    $tip['label'] = 'a' . $this->randomString();
    $this->drupalGet('admin/config/user-interface/tour/manage/' . $edit['id'] . '/tip/edit/' . $tip_id);
    $this->submitForm($tip, 'Save');

    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', [
      ':class' => 'draggable',
      ':text' => $tip['label'],
    ]);
    $this->assertEquals(1, count($elements), 'Found tip "' . $tip['label'] . '".');
    $this->drupalGet('admin/config/user-interface/tour/manage/' . $edit['id'] . '/tip/edit/' . $tip_id);
    $this->assertSession()->titleEquals('Edit tip | Drupal');

    // Delete the tip.
    $this->clickLink('Delete');
    $this->submitForm([], 'Confirm');
    $elements = $this->xpath('//tr[@class=:class and ./td[contains(., :text)]]', [
      ':class' => 'draggable odd',
      ':text' => $tip['label'],
    ]);
    $this->assertNotEquals(1, count($elements), 'Did not find tip "' . $tip['label'] . '".');
  }

  /**
   * Tests the clone action.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function cloneTest(): void {
    $this->drupalGet('admin/config/user-interface/tour');

    $this->click('a[href$="admin/config/user-interface/tour/manage/tip_edit/clone"]');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'label' => 'Clone test',
      'new_name' => 'tip_edit_clone',
    ], 'Save');

    $this->assertSession()->addressEquals('admin/config/user-interface/tour/manage/tip_edit_clone');
    $this->assertSession()->fieldValueEquals('label', 'Clone test');
  }

  /**
   * Tests the export configuration action.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function exportConfigTest(): void {
    $this->drupalGet('admin/config/user-interface/tour');

    $this->click('a[href$="admin/config/development/configuration/single/export/tour/tip_edit"]');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('admin/config/development/configuration/single/export/tour/tip_edit');
  }

}
