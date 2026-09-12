<?php

namespace Drupal\pathauto\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\pathauto\AliasStorageHelperInterface;

/**
 * Hook implementations for pathauto.
 */
class PathautoHooks {
  use StringTranslationTrait;

  public function __construct(
    protected AliasStorageHelperInterface $aliasStorageHelper,
  ) {

  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.pathauto':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Pathauto module provides a mechanism to automate the creation of <a href="path">path</a> aliases. This makes URLs more readable and helps search engines index content more effectively.  For more information, see the <a href=":online">online documentation for Pathauto</a>.', [
          ':online' => 'https://www.drupal.org/documentation/modules/pathauto',
        ]) . '</p>';
        $output .= '<dl>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dd>' . $this->t('Pathauto is accessed from the tabs it adds to the list of <a href=":aliases">URL aliases</a>.', [
          ':aliases' => Url::fromRoute('entity.path_alias.collection')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Creating Pathauto Patterns') . '</dt>';
        $output .= '<dd>' . $this->t('The <a href=":pathauto_pattern">"Patterns"</a> page is used to configure automatic path aliasing.  New patterns are created here using the <a href=":add_form">Add Pathauto pattern</a> button which presents a form to simplify pattern creation thru the use of <a href="token">available tokens</a>. The patterns page provides a list of all patterns on the site and allows you to edit and reorder them. An alias is generated for the first pattern that applies.', [
          ':pathauto_pattern' => Url::fromRoute('entity.pathauto_pattern.collection')->toString(),
          ':add_form' => Url::fromRoute('entity.pathauto_pattern.add_form')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Pathauto Settings') . '</dt>';
        $output .= '<dd>' . $this->t('The <a href=":settings">"Settings"</a> page is used to customize global Pathauto settings for automated pattern creation.', [
          ':settings' => Url::fromRoute('pathauto.settings.form')->toString(),
        ]) . '</dd>';
        $output .= '<dd>' . $this->t('The <strong>maximum alias length</strong> and <strong>maximum component length</strong> values default to 100 and have a limit of @max from Pathauto. You should enter a value that is the length of the "alias" column of the path_alias database table minus the length of any strings that might get added to the end of the URL. The recommended and default value is 100.', [
          '@max' => $this->aliasStorageHelper->getAliasSchemaMaxlength(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Bulk Generation') . '</dt>';
        $output .= '<dd>' . $this->t('The <a href=":pathauto_bulk">"Bulk Generate"</a> page allows you to create URL aliases for items that currently have no aliases. This is typically used when installing Pathauto on a site that has existing un-aliased content that needs to be aliased in bulk.', [
          ':pathauto_bulk' => Url::fromRoute('pathauto.bulk.update.form')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Delete Aliases') . '</dt>';
        $output .= '<dd>' . $this->t('The <a href=":pathauto_delete">"Delete Aliases"</a> page allows you to remove URL aliases from items that have previously been assigned aliases using pathauto.', [
          ':pathauto_delete' => Url::fromRoute('pathauto.admin.delete')->toString(),
        ]) . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'entity.pathauto_pattern.collection':
        $output = '<p>' . $this->t('This page provides a list of all patterns on the site and allows you to edit and reorder them.') . '</p>';
        return $output;

      case 'entity.pathauto_pattern.add_form':
        $output = '<p>' . $this->t('You need to select a pattern type, then a pattern and filter, and a label. Additional types can be enabled on the <a href=":settings">Settings</a> page.', [
          ':settings' => Url::fromRoute('pathauto.settings.form')->toString(),
        ]) . '</p>';
        return $output;

      case 'pathauto.bulk.update.form':
        $output = '<p>' . $this->t('Bulk generation can be used to generate URL aliases for items that currently have no aliases. This is typically used when installing Pathauto on a site that has existing un-aliased content that needs to be aliased in bulk.') . '<br>';
        $output .= $this->t('It can also be used to regenerate URL aliases for items that have an old alias and for which the Pathauto pattern has been changed.') . '</p>';
        $output .= '<p>' . $this->t('Note that this will only affect items which are configured to have their URL alias automatically set. Items whose URL alias is manually set are not affected.') . '</p>';
        return $output;
    }

    return NULL;
  }

}
