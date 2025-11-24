<?php

namespace Drupal\Tests\crop\Functional;

use Drupal\Component\Utility\UrlHelper;
use Drupal\crop\CropInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\BrowserTestBase;
use Drupal\crop\Entity\Crop;
use Drupal\crop\Entity\CropType;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

/**
 * Functional tests for crop API.
 *
 * @group crop
 */
class CropFunctionalTest extends BrowserTestBase {

  /**
   * Test file URI.
   */
  protected string $fileUri = 'public://sarajevo.png';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['crop', 'file'];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Test image style.
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected $testStyle;

  /**
   * Test crop type.
   *
   * @var \Drupal\crop\CropInterface
   */
  protected $cropType;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer crop types', 'administer image styles']);

    // Create test image style.
    $this->testStyle = ImageStyle::create([
      'name' => 'test',
      'label' => 'Test image style',
      'effects' => [],
    ]);
    $this->testStyle->save();
  }

  /**
   * Tests crop type crud pages.
   */
  public function testCropTypeCrud(): void {
    $assert_session = $this->assertSession();

    // Anonymous users don't have access to crop type admin pages.
    $this->drupalGet('admin/config/media/crop');
    $assert_session->statusCodeEquals(403);

    $this->drupalGet('admin/config/media/crop/add');
    $assert_session->statusCodeEquals(403);

    // Can access pages if logged in and no crop types exist.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/media/crop');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('No crop types available.');
    $assert_session->linkExists('Add crop type');

    // Can access add crop type form.
    $this->clickLink('Add crop type');
    $assert_session->statusCodeEquals(200);
    $assert_session->addressEquals('admin/config/media/crop/add');

    // Create crop type.
    $crop_type_id = strtolower($this->randomMachineName());
    $edit = [
      'id' => $crop_type_id,
      'label' => $this->randomMachineName(),
      'description' => $this->getRandomGenerator()->word(10),
    ];
    $this->drupalGet('admin/config/media/crop/add');
    $this->submitForm($edit, 'Save crop type');
    $assert_session->responseContains("The crop type <em class=\"placeholder\">{$edit['label']}</em> has been added.");
    $this->cropType = CropType::load($crop_type_id);
    $assert_session->addressEquals('admin/config/media/crop');
    $label = $this->xpath("//td[contains(concat(' ',normalize-space(@class),' '),' menu-label ')]");
    $this->assertStringContainsString($edit['label'], $label[0]->getText(), 'Crop type label found on listing page.');
    $assert_session->pageTextContains($edit['description']);

    // Check edit form.
    $this->clickLink('Edit');
    $assert_session->pageTextContains("Edit {$edit['label']} crop type");

    $assert_session->responseContains($edit['id']);
    $assert_session->fieldExists('edit-label');
    $assert_session->responseContains($edit['description']);

    // See if crop type appears on image effect configuration form.
    $this->drupalGet('admin/config/media/image-styles/manage/' . $this->testStyle->id() . '/add/crop_crop');
    $option = $this->xpath("//select[@id='edit-data-crop-type']/option");
    $this->assertStringContainsString($edit['label'], $option[0]->getText(), 'Crop type label found on image effect page.');
    $this->drupalGet('admin/config/media/image-styles/manage/' . $this->testStyle->id() . '/add/crop_crop');
    $this->submitForm(['data[crop_type]' => $edit['id']], 'Add effect');
    $assert_session->pageTextContains('The image effect was successfully applied.');
    $assert_session->pageTextContains("Manual crop uses {$edit['label']} crop type");
    $this->testStyle = $this->container->get('entity_type.manager')->getStorage('image_style')->loadUnchanged($this->testStyle->id());
    $this->assertCount(1, $this->testStyle->getEffects(), 'One image effect added to test image style.');
    $effect_configuration = $this->testStyle->getEffects()->getIterator()->current()->getConfiguration();
    self::assertEquals($effect_configuration['data'], ['crop_type' => $edit['id'], 'automatic_crop_provider' => NULL], 'Manual crop effect uses correct image style.');

    // Tests the image URI is extended with shortened hash in case of image
    // style and corresponding crop existence.
    $this->doTestFileUriAlter();
    $this->assertSame(0, $this->countCrops());

    // Try to access edit form as anonymous user.
    $this->drupalLogout();
    $this->drupalGet('admin/config/media/crop/manage/' . $edit['id']);
    $assert_session->statusCodeEquals(403);
    $this->drupalLogin($this->adminUser);

    // Try to create crop type with same machine name.
    $this->drupalGet('admin/config/media/crop/add');
    $this->submitForm($edit, 'Save crop type');
    $assert_session->pageTextContains('The machine-readable name is already in use. It must be unique.');

    $file = $this->createFile();
    $this->addCropToCropType($file);
    $this->assertSame(1, $this->countCrops());

    // Flush all crops of the type we just saved.
    $this->drupalGet('admin/config/media/crop');
    $assert_session->linkExists('Test image style');
    $this->clickLink('Flush');
    $assert_session->pageTextContains('This action cannot be undone.');
    $this->click('.crop-type-flush-form .form-actions input[type="submit"]');

    // Expected that all crop are deleted.
    $this->assertSame(0, $this->countCrops());

    $this->drupalGet('admin/config/media/crop');
    $assert_session->linkExists('Test image style');
    $this->clickLink('Flush');
    $assert_session->pageTextContains("you can safely delete this crop type.");

    // Clean file.
    $file->delete();

    // Delete crop type.
    $this->drupalGet('admin/config/media/crop');
    $assert_session->linkExists('Test image style');
    $this->clickLink('Delete');
    $label = $edit['label'];
    $assert_session->pageTextContains("Are you sure you want to delete the crop type $label?");
    // Confirm that the user is warned about the crop type being used by other
    // config objects.
    $assert_session->pageTextContains('The listed configuration will be deleted.');
    $assert_session->pageTextContains('Test image style');
    $this->submitForm([], 'Delete');
    $assert_session->statusMessageContains("The crop type $label has been deleted.");
    $assert_session->pageTextContains('No crop types available.');
  }

  /**
   * Asserts a shortened hash is added to the file URI.
   *
   * Tests crop_file_url_alter().
   */
  protected function doTestFileUriAlter(): void {
    $file = $this->createFile();
    $crop = $this->addCropToCropType($file);
    $this->assertSame(1, $this->countCrops());

    // Test that the hash is appended both when a URL is created and passed
    // through file_create_url() and when a URL is created, without additional
    // file_create_url() calls.
    $shortened_hash = $crop->getShortHash();

    // Build an image style derivative for the file URI.
    $image_style_uri = $this->testStyle->buildUri($this->fileUri);

    $image_style_uri_url = \Drupal::service('file_url_generator')->generateAbsoluteString($image_style_uri);
    $url = UrlHelper::parse($image_style_uri_url);
    $this->assertSame($shortened_hash, $url['query']['h'], 'The image style URL contains a shortened hash.');

    $image_style_url = $this->testStyle->buildUrl($this->fileUri);
    $url = UrlHelper::parse($image_style_url);
    $this->assertSame($shortened_hash, $url['query']['h'], 'The image style URL contains a shortened hash.');

    // Update the crop to assert the hash has changed.
    $crop->setPosition('80', '80')->save();
    $old_hash = $shortened_hash;
    $new_hash = $crop->getShortHash();

    $image_style_url = $this->testStyle->buildUrl($this->fileUri);
    $url = UrlHelper::parse($image_style_url);
    $this->assertSame($new_hash, $url['query']['h'], 'The image style URL contains an updated hash.');

    // Delete the file and the crop entity associated,
    // the crop entity are auto cleaned by crop_file_delete().
    $file->delete();

    // Check that the crop entity is correctly deleted.
    $this->assertFalse(Crop::cropExists($this->fileUri), 'The Crop entity was correctly deleted after file delete.');
  }

  /**
   * Creates a file to use for testing crops.
   *
   * @return \Drupal\file\FileInterface
   *   A new, saved file entity.
   */
  private function createFile(): FileInterface {
    \Drupal::service('file_system')
      ->copy(__DIR__ . '/../../files/sarajevo.png', 'public://');

    /** @var \Drupal\file\FileInterface $file*/
    $file = File::create(['uri' => $this->fileUri]);
    $file->setPermanent();
    $file->save();

    return $file;
  }

  /**
   * Create Crop.
   *
   * @param \Drupal\file\FileInterface $file
   *   Public file.
   *
   * @return \Drupal\crop\CropInterface
   *   Newly created Crop.
   */
  private function addCropToCropType(FileInterface $file): CropInterface {
    $crop = Crop::create([
      'type' => $this->cropType->id(),
      'entity_id' => $file->id(),
      'entity_type' => $file->getEntityTypeId(),
      'uri' => $this->fileUri,
      'x' => '100',
      'y' => '150',
      'width' => '200',
      'height' => '250',
    ]);
    $crop->save();
    return $crop;
  }

  /**
   * Count the number of crops of $this->cropType that exist.
   *
   * @return int
   *   Number of crops.
   */
  private function countCrops(): int {
    return \Drupal::entityTypeManager()
      ->getStorage('crop')
      ->getQuery()
      ->count()
      ->accessCheck(FALSE)
      ->condition('type', $this->cropType->id())
      ->execute();
  }

}
