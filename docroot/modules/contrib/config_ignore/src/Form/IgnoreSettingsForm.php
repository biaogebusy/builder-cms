<?php

namespace Drupal\config_ignore\Form;

use Drupal\config_ignore\ConfigIgnoreConfig;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a setting UI for Config Ignore.
 *
 * @internal This is not part of the API of config ignore.
 */
class IgnoreSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'config_ignore.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_ignore_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Request $request = NULL) {

    $config = $this->config('config_ignore.settings');
    $ignoreConfig = ConfigIgnoreConfig::fromConfig($config);

    $description = $this->t('One configuration name per line.<br />
Examples: <ul>
<li>user.settings</li>
<li>views.settings</li>
<li>contact.settings</li>
<li>webform.webform.* (will ignore all config entities that starts with <em>webform.webform</em>)</li>
<li>*.contact_message.custom_contact_form.* (will ignore all config entities that starts with <em>.contact_message.custom_contact_form.</em> like fields attached to a custom contact form)</li>
<li>* (will ignore everything)</li>
<li>~webform.webform.contact (will force import for this configuration, even if ignored by a wildcard)</li>
<li>user.mail:register_no_approval_required.body (will ignore the body of the no approval required email setting, but will not ignore other user.mail configuration.)</li>
<li>language.*|* (will ignore all language collections)</li>
<li>language.fr|* (will ignore all fr language collection)</li>
<li>language.fr|field.field.* (will ignore all fr field translations)</li>
<li>~language.fr|field.field.media.file.field_media_file (will force import for certain field translation)</li>
<li>language.*|system.site:name (will ignore just the site name but in all translations)</li>
</ul><br />
By default, the config ignore settings present in the storage to be transformed are used. <br />
That means that when importing config, the config_ignore.settings.yml from the sync storage is used and the active config_ignore.settings is used when exporting.
However, if you ignore config_ignore.settings it will use the other storage, so the active config when importing and the one from the sync directory when exporting.
You can also configure the source in settings.php. Consult the readme for more information.
');

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode of operation'),
      '#default_value' => $config->get('mode') ?? 'simple',
      '#options' => [
        'simple' => $this->t('Simple'),
        'intermediate' => $this->t('Intermediate'),
        'advanced' => $this->t('Advanced'),
      ],
      '#description' => $this->t('This setting controls how complicated the configuration is. If you do not have a use-case for a more advanced set-up the recommendation is to keep it simple.'),
    ];

    $form['description'] = [
      '#markup' => $description,
    ];

    $form['simple'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Simple'),
      '#states' => [
        'visible' => [
          ':input[name="mode"]' => ['value' => 'simple'],
        ],
      ],
    ];
    $form['simple']['ignored_config_entities'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Configuration entity names to ignore'),
      '#description' => $this->t('The configuration ignored in all cases'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('import', 'update')),
      '#size' => 60,
    ];

    $form['intermediate'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Intermediate'),
      '#states' => [
        'visible' => [
          ':input[name="mode"]' => ['value' => 'intermediate'],
        ],
      ],
    ];
    $form['intermediate']['import'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Import'),
      '#description' => $this->t('The configuration ignored when importing <br />This means the active configuration will not be replaced by what is in the sync folder.'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('import', 'update')),
      '#size' => 60,
    ];
    $form['intermediate']['export'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Export'),
      '#description' => $this->t('The configuration ignored when exporting <br />This means the configuration in the sync folder will not be replaced by what is active on the site.'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('export', 'update')),
      '#size' => 60,
    ];

    $form['advanced'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced'),
      '#states' => [
        'visible' => [
          ':input[name="mode"]' => ['value' => 'advanced'],
        ],
      ],
    ];
    $form['advanced']['import'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Import'),
      '#description' => $this->t('Configuration listed here will be ignored during configuration import (e.g. <code>drush config:import</code>). The active site configuration will be preserved instead of being overwritten by the sync folder.'),
    ];
    $form['advanced']['export'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Export'),
      '#description' => $this->t('Configuration listed here will be ignored during configuration export (e.g. <code>drush config:export</code>). The sync folder configuration will be preserved instead of being overwritten by what is active on the site.'),
    ];
    $form['advanced']['import']['import_create'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Ignore create on import'),
      '#description' => $this->t('Configuration that exists in the sync folder but not on the site would normally be created during import. Listing config names here prevents them from being created on the site.<br />For config keys: if an existing config is updated during import and the update would add a new key, listing that key here prevents it from being added.'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('import', 'create')),
      '#size' => 60,
    ];
    $form['advanced']['import']['import_update'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Ignore update on import'),
      '#description' => $this->t('Configuration that exists both on the site and in the sync folder would normally be updated during import. Listing config names here preserves the active site configuration as-is.'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('import', 'update')),
      '#size' => 60,
    ];
    $form['advanced']['import']['import_delete'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Ignore delete on import'),
      '#description' => $this->t('Configuration that exists on the site but not in the sync folder would normally be deleted during import. Listing config names here preserves them on the site.<br />For config keys: if an existing config is updated during import and the update would remove a key, listing that key here prevents it from being removed.'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('import', 'delete')),
      '#size' => 60,
    ];
    $form['advanced']['export']['export_create'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Ignore create on export'),
      '#description' => $this->t('Configuration that exists on the site but not yet in the sync folder would normally be added to the sync folder during export. Listing config names here prevents them from being exported.<br />For config keys: if an existing config is exported and the site version has a key not present in the sync folder, listing that key here prevents it from being added to the sync folder.'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('export', 'create')),
      '#size' => 60,
    ];
    $form['advanced']['export']['export_update'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Ignore update on export'),
      '#description' => $this->t('Configuration that exists both on the site and in the sync folder would normally be updated in the sync folder during export. Listing config names here preserves the sync folder configuration as-is.'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('export', 'update')),
      '#size' => 60,
    ];
    $form['advanced']['export']['export_delete'] = [
      '#type' => 'textarea',
      '#rows' => 25,
      '#title' => $this->t('Ignore delete on export'),
      '#description' => $this->t('Configuration that exists in the sync folder but not on the site would normally be removed from the sync folder during export. Listing config names here preserves them in the sync folder.<br />For config keys: if an existing config is exported and the sync folder has a key not present on the site, listing that key here prevents it from being removed from the sync folder.'),
      '#default_value' => implode(PHP_EOL, $ignoreConfig->getList('export', 'delete')),
      '#size' => 60,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mode = $form_state->getValue('mode');
    switch ($mode) {
      case 'simple':
        $data = self::extractFormValue($form_state->getValue('ignored_config_entities'));
        break;

      case 'intermediate':
        $data = [
          'import' => self::extractFormValue($form_state->getValue('import')),
          'export' => self::extractFormValue($form_state->getValue('export')),
        ];
        break;

      case 'advanced':
        $data = [
          'create' => [
            'import' => self::extractFormValue($form_state->getValue('import_create')),
            'export' => self::extractFormValue($form_state->getValue('export_create')),
          ],
          'update' => [
            'import' => self::extractFormValue($form_state->getValue('import_update')),
            'export' => self::extractFormValue($form_state->getValue('export_update')),
          ],
          'delete' => [
            'import' => self::extractFormValue($form_state->getValue('import_delete')),
            'export' => self::extractFormValue($form_state->getValue('export_delete')),
          ],
        ];
        break;

      default:
        throw new \InvalidArgumentException('Something went wrong with the form submission');
    }

    // Validate and sort the data.
    $ignoreConfig = new ConfigIgnoreConfig($mode, $data);

    $config = $this->config('config_ignore.settings');
    $config->set('mode', $mode);
    $config->set('ignored_config_entities', $ignoreConfig->getFormated($mode));
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Extract the array from the textfield input.
   *
   * @param string $input
   *   The form input.
   *
   * @return string[]
   *   The cleaned up values.
   */
  protected static function extractFormValue(string $input): array {
    return array_values(
      array_filter(
        array_map(fn($l) => trim($l),
          explode("\n", str_replace("\r", "\n", $input)))
      )
    );
  }

}
