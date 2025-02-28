<?php

namespace Drupal\Tests\commerce_product\Functional;

use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests translating product attributes and their values.
 *
 * @group commerce
 */
class ProductAttributeTranslationTest extends ProductBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_product_test',
    'config_translation',
    'content_translation',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_attribute_filtered_variations'];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_product_attribute',
      'administer languages',
      'translate any entity',
      'translate configuration',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add the French language.
    $edit = ['predefined_langcode' => 'fr'];
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, $this->t('Add language'));
    \Drupal::languageManager()->reset();
  }

  /**
   * Tests product attribute translation.
   */
  public function testProductAttributeTranslation() {
    // Create an attribute with no values.
    $this->createEntity('commerce_product_attribute', [
      'id' => 'color',
      'label' => 'Color',
    ]);
    // Confirm that the attribute is translatable, and there's no value
    // translation form is missing.
    $this->drupalGet('admin/commerce/product-attributes/manage/color/translate/fr/add');
    $this->assertSession()->pageTextContains('Label');
    $this->assertSession()->pageTextNotContains('Value');

    // Add two attribute values.
    $red_value = $this->createEntity('commerce_product_attribute_value', [
      'attribute' => 'color',
      'name' => 'Red',
      'weight' => 0,
    ]);
    $blue_value = $this->createEntity('commerce_product_attribute_value', [
      'attribute' => 'color',
      'name' => 'Blue',
      'weight' => 1,
    ]);
    // Confirm that the value translation form is still missing.
    $this->drupalGet('admin/commerce/product-attributes/manage/color/translate/fr/add');
    $this->assertSession()->pageTextNotContains('Value');

    // Enable attribute value translations.
    $edit = [
      'enable_value_translation' => TRUE,
    ];
    $this->drupalGet('admin/commerce/product-attributes/manage/color');
    $this->submitForm($edit, $this->t('Save'));

    // Translate the attribute and its values to French.
    $this->drupalGet('admin/commerce/product-attributes/manage/color/translate/fr/add');
    $this->assertSession()->pageTextContains('Label');
    $this->assertSession()->pageTextContains('Value');
    $edit = [
      'translation[config_names][commerce_product.commerce_product_attribute.color][label]' => 'Couleur',
      'values[' . $red_value->id() . '][translation][name][0][value]' => 'Rouge',
      // Leave the second value untouched.
    ];
    $this->submitForm($edit, $this->t('Save translation'));

    \Drupal::entityTypeManager()->getStorage('commerce_product_attribute')->resetCache();
    \Drupal::entityTypeManager()->getStorage('commerce_product_attribute_value')->resetCache();
    $color_attribute = ProductAttribute::load('color');
    // Confirm the attribute translation.
    $language_manager = \Drupal::languageManager();
    $config_name = $color_attribute->getConfigDependencyName();
    $config_translation = $language_manager->getLanguageConfigOverride('fr', $config_name);
    $this->assertEquals('Couleur', $config_translation->get('label'));

    // Confirm the attribute value translations.
    $values = $color_attribute->getValues();
    $first_value = reset($values);
    $first_value = $first_value->getTranslation('fr');
    $this->assertEquals('fr', $first_value->language()->getId());
    $this->assertEquals('Rouge', $first_value->label());
    $second_value = end($values);
    $second_value = $second_value->getTranslation('fr');
    $this->assertEquals('fr', $second_value->language()->getId());
    $this->assertEquals('Blue', $second_value->label());
  }

  /**
   * Tests the product attribute UI with mismatched languages.
   */
  public function testMismatchedLanguages() {
    // Create a French attribute with two English (default language) values.
    $this->createEntity('commerce_product_attribute', [
      'id' => 'color',
      'label' => 'Couleur',
      'langcode' => 'fr',
    ]);
    $red_value = $this->createEntity('commerce_product_attribute_value', [
      'attribute' => 'color',
      'name' => 'Red',
      'weight' => 0,
    ]);
    $blue_value = $this->createEntity('commerce_product_attribute_value', [
      'attribute' => 'color',
      'name' => 'Blue',
      'weight' => 1,
    ]);

    // Enable attribute value translations.
    $edit = [
      'enable_value_translation' => TRUE,
    ];
    $this->drupalGet('admin/commerce/product-attributes/manage/color');
    $this->submitForm($edit, $this->t('Save'));

    // Translate the English values to French.
    $red_value_en = $red_value->addTranslation('fr', ['name' => 'Rouge']);
    $red_value_en->save();
    $blue_value_en = $blue_value->addTranslation('fr', ['name' => 'Bleu']);
    $blue_value_en->save();

    // Since the attribute language is French, the displayed values should
    // also be in French, not English.
    $this->drupalGet('admin/commerce/product-attributes/manage/color');
    $this->assertSession()->elementExists('xpath', "//input[@value='Rouge']");
    $this->assertSession()->elementExists('xpath', "//input[@value='Bleu']");

    $this->drupalGet('admin/commerce/product-attributes/manage/color/translate/en/add');
    $this->assertSession()->pageTextContains('Rouge');
    $this->assertSession()->pageTextContains('Bleu');
  }

  /**
   * Tests attribute values translation in views exposed filters.
   */
  public function testExposedAttributeFilterTranslation() {
    // Add the Hungarian language.
    $edit = ['predefined_langcode' => 'hu'];
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add language');
    \Drupal::languageManager()->reset();

    ViewTestData::createTestViews(self::class, ['commerce_product_test']);

    // Create an attribute, so we can test it displays, too.
    $attribute = $this->createEntity('commerce_product_attribute', [
      'id' => 'color',
      'label' => 'Color',
    ]);
    assert($attribute instanceof ProductAttribute);
    $attribute->save();

    $this->container->get('commerce_product.attribute_field_manager')->createField($attribute, 'default');

    $attribute_values = [];
    $colors = ['Red', 'Green', 'Black'];
    foreach ($colors as $color_attribute_value) {
      $lowercase = strtolower($color_attribute_value);
      $attribute_values[$lowercase] = $this->createEntity('commerce_product_attribute_value', [
        'attribute' => $attribute->id(),
        'name' => $color_attribute_value,
      ]);
    }

    // Enable translations.
    $edit = [
      'enable_value_translation' => TRUE,
    ];
    $this->drupalGet('admin/commerce/product-attributes/manage/color');
    $this->submitForm($edit, 'Save');

    // Translate the attribute and its values to French.
    $this->drupalGet('admin/commerce/product-attributes/manage/color/translate/fr/add');
    $this->assertSession()->pageTextContains('Label');
    $this->assertSession()->pageTextContains('Value');
    $edit = [
      'translation[config_names][commerce_product.commerce_product_attribute.color][label]' => 'Couleur',
      'values[' . $attribute_values['red']->id() . '][translation][name][0][value]' => 'Rouge',
      'values[' . $attribute_values['green']->id() . '][translation][name][0][value]' => 'Vert',
      'values[' . $attribute_values['black']->id() . '][translation][name][0][value]' => 'Noir',
      // Leave the second value untouched.
    ];
    $this->submitForm($edit, 'Save translation');

    $this->drupalGet('variations');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=1]', 'Red');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=2]', 'Green');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=3]', 'Black');

    $this->drupalGet('fr/variations');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=1]', 'Rouge');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=2]', 'Vert');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=3]', 'Noir');

    // Switching to a language attribute values are not translated to.
    $this->drupalGet('hu/variations');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=1]', 'Red');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=2]', 'Green');
    $this->assertSession()->elementTextContains('css', 'select[name="attribute_color_target_id[]"] option[value=3]', 'Black');
  }

}
