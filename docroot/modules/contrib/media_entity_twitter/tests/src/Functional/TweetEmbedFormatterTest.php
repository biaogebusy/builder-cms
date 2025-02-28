<?php

namespace Drupal\Tests\media_entity_twitter\Functional;

use Drupal\Tests\media\Functional\MediaFunctionalTestBase;

/**
 * Tests for Twitter embed formatter.
 *
 * @group media_entity_twitter
 */
class TweetEmbedFormatterTest extends MediaFunctionalTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'media_entity_twitter',
    'link',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests adding and editing a twitter embed formatter.
   */
  public function testManageEmbedFormatter() {
    // Test and create one media type.
    $bundle = $this->createMediaType('twitter', ['id' => 'twitter']);

    // We need to fix widget and formatter config for the default field.
    $source = $bundle->getSource();
    $source_field = $source->getSourceFieldDefinition($bundle);
    // Use the default widget and settings.
    $component = \Drupal::service('plugin.manager.field.widget')
      ->prepareConfiguration('string', []);

    // Enable the conical URL.
    \Drupal::configFactory()
      ->getEditable('media.settings')
      ->set('standalone_url', TRUE)
      ->save(TRUE);
    $this->container->get('router.builder')->rebuild();

    // @todo Replace entity_get_form_display() when #2367933 is done.
    // https://www.drupal.org/node/2872159.
    \Drupal::service('entity_display.repository')->getFormDisplay('media', $bundle->id(), 'default')
      ->setComponent($source_field->getName(), $component)
      ->save();

    // Assert that the media type has the expected values before proceeding.
    $this->drupalGet('admin/structure/media/manage/' . $bundle->id());
    $this->assertSession()->fieldValueEquals('label', $bundle->label());
    $this->assertSession()->fieldValueEquals('source', 'twitter');

    // Add and save string_long field type settings (Embed code).
    $this->drupalGet('admin/structure/media/manage/' . $bundle->id() . '/fields/add-field');
    $edit_conf = [
      'new_storage_type' => 'string_long',
      'label' => 'Embed code',
      'field_name' => 'embed_code',
    ];
    $this->submitForm($edit_conf, t('Save and continue'));
    $this->assertSession()
      ->responseContains('These settings apply to the <em class="placeholder">' . $edit_conf['label'] . '</em> field everywhere it is used.');
    $edit = [
      'cardinality' => 'number',
      'cardinality_number' => '1',
    ];
    $this->submitForm($edit, t('Save field settings'));
    $this->assertSession()
      ->responseContains('Updated field <em class="placeholder">' . $edit_conf['label'] . '</em> field settings.');

    // Set the new string_long field type as required.
    $edit = [
      'required' => TRUE,
    ];
    $this->submitForm($edit, t('Save settings'));
    $this->assertSession()
      ->responseContains('Saved <em class="placeholder">' . $edit_conf['label'] . '</em> configuration.');

    // Assert that the new field types configurations have been successfully
    // saved.
    $this->drupalGet('admin/structure/media/manage/' . $bundle->id() . '/fields');
    $xpath = $this->xpath('//*[@id=:id]/td', [':id' => 'field-media-twitter']);
    $this->assertEquals((string) $xpath[0]->getText(), 'Tweet URL');
    $this->assertEquals((string) $xpath[1]->getText(), 'field_media_twitter');
    $this->assertEquals((string) $xpath[2]->find('css', 'a')->getText(), 'Text (plain)');

    $xpath = $this->xpath('//*[@id=:id]/td', [':id' => 'field-embed-code']);
    $this->assertEquals((string) $xpath[0]->getText(), 'Embed code');
    $this->assertEquals((string) $xpath[1]->getText(), 'field_embed_code');
    $this->assertEquals((string) $xpath[2]->find('css', 'a')->getText(), 'Text (plain, long)');

    $this->drupalGet('admin/structure/media/manage/' . $bundle->id() . '/display');

    // Set and save the settings of the new field types.
    $edit = [
      'fields[field_media_twitter][parent]' => 'content',
      'fields[field_media_twitter][region]' => 'content',
      'fields[field_media_twitter][label]' => 'above',
      'fields[field_media_twitter][type]' => 'twitter_embed',
      'fields[field_embed_code][label]' => 'above',
      'fields[field_embed_code][type]' => 'twitter_embed',
    ];
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->responseContains('Your settings have been saved.');

    // Create and save the media with a twitter media code.
    $this->drupalGet('media/add/' . $bundle->id());

    // Random image url from twitter.
    $tweet_url = 'https://twitter.com/DrupalConEur/status/1176518741208817664';

    // Random image from twitter.
    $tweet = '<blockquote class="twitter-tweet" lang="it"><p lang="en" dir="ltr">' .
             'Midnight project. I ain&#39;t got no oven. So I improvise making this milo crunchy kek batik. hahahaha ' .
             '<a href="https://twitter.com/hashtag/itssomething?src=hash">#itssomething</a> ' .
             '<a href="https://t.co/Nvn4Q1v2ae">pic.twitter.com/Nvn4Q1v2ae</a></p>&mdash; Zi (@RamzyStinson) ' .
             '<a href="https://twitter.com/RamzyStinson/status/670650348319576064">' .
             '28 Novembre 2015</a></blockquote><script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>';

    $edit = [
      'name[0][value]' => 'Title',
      'field_media_twitter[0][value]' => $tweet_url,
      'field_embed_code[0][value]' => $tweet,
    ];
    $this->submitForm($edit, t('Save'));
    $this->drupalGet('media/1');

    // Assert that the media has been successfully saved.
    $this->assertSession()->pageTextContains('Title');

    // Assert that the link url formatter exists on this page.
    $this->assertSession()->pageTextContains('Tweet URL');
    $this->assertSession()
      ->responseContains('<a href="https://twitter.com/RamzyStinson/statuses/670650348319576064">');

    // Assert that the string_long code formatter exists on this page.
    $this->assertSession()->pageTextContains('Embed code');
    $this->assertSession()->responseContains('<blockquote class="twitter-tweet');
  }

}
