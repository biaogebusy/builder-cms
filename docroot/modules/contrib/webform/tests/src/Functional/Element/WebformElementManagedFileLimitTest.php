<?php

namespace Drupal\Tests\webform\Functional\Element;

use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\webform\Entity\Webform;

/**
 * Test for webform element managed file limit.
 *
 * @group webform
 */
class WebformElementManagedFileLimitTest extends WebformElementManagedFileTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['file', 'webform'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_managed_file_limit'];

  /**
   * Test file limit.
   */
  public function testLimits(): void {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->rootUser);

    // Get a 1 MB text file.
    $files = $this->getTestFiles('text', '1024');
    $file = reset($files);
    $bytes = filesize($file->uri);
    $this->debug($bytes);

    $webform = Webform::load('test_element_managed_file_limit');

    // Check form file limit.
    $this->drupalGet('/webform/test_element_managed_file_limit');
    $assert_session->responseContains('The accumulated size of all files in this form cannot exceed 1 MB.');
    $assert_session->responseContains('512 KB limit per file. The accumulated size of all files in this form cannot exceed 1 MB.');
    $assert_session->responseContains('The accumulated size of all files in this form cannot exceed 1 MB.');

    // Check empty form file limit.
    $webform->setSetting('form_file_limit', '')->save();
    $this->drupalGet('/webform/test_element_managed_file_limit');
    $assert_session->responseNotContains('The accumulated size of all files in this form cannot exceed 1 MB.');

    // Check default form file limit.
    \Drupal::configFactory()
      ->getEditable('webform.settings')
      ->set('settings.default_form_file_limit', '2 MB')
      ->save();
    $this->drupalGet('/webform/test_element_managed_file_limit');
    $assert_session->responseContains('The accumulated size of all files in this form cannot exceed 2 MB.');

    // Set limit to 2 files.
    \Drupal::configFactory()
      ->getEditable('webform.settings')
      ->set('settings.default_form_file_limit', ($bytes * 2) . ' bytes')
      ->save();
    $this->drupalGet('/webform/test_element_managed_file_limit');
    $assert_session->responseContains('The accumulated size of all files in this form cannot exceed ' . ByteSizeMarkup::create($bytes * 2));

    // Check valid file upload.
    $edit = [
      'files[managed_file_01]' => \Drupal::service('file_system')->realpath($file->uri),
    ];
    $sid = $this->postSubmission($webform, $edit);
    $this->assertNotNull($sid);

    // Check invalid file upload.
    $edit = [
      'files[managed_file_01]' => \Drupal::service('file_system')->realpath($file->uri),
      'files[managed_file_02]' => \Drupal::service('file_system')->realpath($file->uri),
      'files[managed_file_03]' => \Drupal::service('file_system')->realpath($file->uri),
    ];
    $this->postSubmission($webform, $edit);
    $assert_session->responseContains('This form\'s file upload quota of <em class="placeholder">2 KB</em> has been exceeded. Please remove some files.');

    // Check invalid composite file upload.
    $this->drupalGet('/webform/test_element_managed_file_limit');
    $edit = [
      'files[managed_file_01]' => \Drupal::service('file_system')->realpath($file->uri),
      'files[custom_composite_managed_files_items_0_managed_file]' => \Drupal::service('file_system')->realpath($file->uri),
      'files[custom_composite_managed_files_items_1_managed_file]' => \Drupal::service('file_system')->realpath($file->uri),
    ];
    $this->submitForm([], 'Add');
    $this->submitForm($edit, 'Submit');
    $assert_session->responseContains('This form\'s file upload quota of <em class="placeholder">2 KB</em> has been exceeded. Please remove some files.');
  }

}
