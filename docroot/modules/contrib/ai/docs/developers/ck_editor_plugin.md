# Developing for AI CKEditor

The widgets that you can see in CKEditor after the AI CKEditor button has been installed on the toolbar can be extended with any type of feature that you would like to add using the Drupal plugin system.

## How does it work in the background

The CKEditor's own system for extending and writing plugins can be quite daunting, especially if you are coming from a backend first background. Because of this we have built a system where you will be able to add a plugin using mainly PHP code.

The idea is that everything that one feature for the AI CKEditor does is take a known input, that can be anything or nothing - this is exposed via Drupal forms api and then produce via Ajax an HTML output to one response form element. The response form elements output can then be used to append or replace marked text in the main CKEditor window.

## What to think about
One plugin can offer multiple features, this means that you can also dynamically create features on the fly depending on database or some other context. See how the [AI Automators](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_automators/src/Plugin/AiCKEditor/AiAutomatorsCKEditor.php?ref_type=heads) implement their solution.

Make sure that you also look through the general tips for [third-party modules](develop_third_party_module.md), since many of them might be connected to creating this plugin.

Also note that the [AI Automators](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_automators/src/Plugin/AiCKEditor/AiAutomatorsCKEditor.php?ref_type=heads) expose a plugin that can run a disposable AI Automator Type. This means that you can set up the coolest workflows just using site building and share them via recipes. [Example video](https://youtu.be/vz0hIVpKYOQ). Don't write code unless you have to or unless you don't want to install the AI Automators!

## Build your first plugin

In your custom module, you will need to add a file to the plugin directory called AiCKEditor. So your file would be put into `src/Plugin/AiCKEditor/{filename}`.

This file should implement the interface [AiCKEditorPluginInterface](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_ckeditor/src/PluginInterfaces/AiCKEditorPluginInterface.php?ref_type=heads) in how it creates this file, but to help you out we have an abstract base class called [AiCKEditorPluginBase](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_ckeditor/src/AiCKEditorPluginBase.php?ref_type=heads) that you can extend on.

The attribute you need to add for discovery is called [AiCKEditor](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_ckeditor/src/Attribute/AiCKEditor.php?ref_type=heads).

This means that you should start with a skeleton that looks like this:

```php
<?php

namespace Drupal\my_custom_module\Plugin\AICKEditor;

use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;

/**
 * Plugin to do something custom.
 */
#[AiCKEditor(
  id: 'custom_feature',
  label: new TranslatableMarkup('My Custom Feature'),
  description: new TranslatableMarkup('This is my custom feature for AI CKEditor.'),
)]
final class MyCustomFeatureCKEditor extends AiCKEditorPluginBase {

}
```

### What does the different methods do.

#### buildConfigurationForm
Since this is built on top of the PluginFormInterface we have a configuration form for setting up the feature. This means that you can ask for which provider or model they should use here or other settings.

If no setting is needed and you used the base class, you can skip this method.

If you are going to expose providers and use the AIRequestCommand - see below, you can use the following code:

```php
$options = $this->aiProviderManager->getSimpleProviderModelOptions('chat');
array_shift($options);
array_splice($options, 0, 1);
$form['provider'] = [
  '#type' => 'select',
  '#title' => $this->t('AI provider'),
  '#options' => $options,
  "#empty_option" => $this->t('-- Default from AI module (chat) --'),
  '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
  '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
];
```

#### validateConfigurationForm
Is only needed if you need some specific configuration validation from this form. Can otherwise be skipped if you use the base class.

#### submitConfigurationForm
This needs to be added if you added fields so they are stored in the configuration.

#### defaultConfiguration
Set up the default configuration before the initial setup is done.

#### buildCkEditorModalForm
This is the form that the end user will be exposed to. There are some special tricks here to think about.

Always start by inheriting from the parent if you want to use a simpler method we provide for you.

The response_text form item should really always be there and have the prefix and suffix that wraps it in `<div id="ai-ckeditor-response"></div>`. It should also set the inherited #allowed_formats and #formats. So it would look something like this.

```php
$editor_id = $this->requestStack->getParentRequest()->get('editor_id');
$form['response_text'] = [
  '#type' => 'text_format',
  '#title' => $this->t('Suggested markup'),
  '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the main editor.'),
  '#prefix' => '<div id="ai-ckeditor-response">',
  '#suffix' => '</div>',
  '#default_value' => '',
  '#allowed_formats' => [$editor_id],
  '#format' => $editor_id,
];
```

The Completion plugin requires the sourceEditing plugin to be enabled in order
for it to work: this is done automatically in an element pre-render hook. If you
also require the plugin, you can add ```'#ai_ckeditor_response' => TRUE,``` to
your text_format field definition and the pre-render plugin will add it for you.

This is to make sure that the value can be transferred back to the main CKEditor window and that it follows the same editor rules (which will be validated)

The form also has a storage section where you can get selected text from the main CKEditor window. So if the user clicks and holds and marks some text.

This can be fetched like this:

```php
$storage = $form_state->getStorage();
$selected_text = $storage['selected_text'] ?? '';
```

If your feature requires marked text, you can validate for this already here and give back an error message. Note that marked text will be replaced as it is right now.

#### validateCkEditorModalForm
If you want some custom validation on the fields you provide. Can be left empty if you use the base class.

#### submitCkEditorModalForm
This is to make sure that form state is kept if you have other ajax functionalities on the form, like a manage file field for instance. This can be left empty though in case you use the base class.

#### ajaxGenerate
So this is not an actual class that is needed by the interface, but unless you want to make a custom Ajax solution, this gets triggered by default and in here there is another helper layer you can use.

There is a custom JS Command called [AiRequestCommand](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_ckeditor/src/Command/AiRequestCommand.php?ref_type=heads) that takes care of taking a prompt, handing it over to your provider that you setup earlier (if you did) and getting the response back in a streaming manner if available from provider and your server.

This is triggered like this:
`new AiRequestCommand($prompt, $editor_id, $plugin_id, $element_id)`.

#### availableEditors
These are all available features you have. If you only offer one, you can just leave it empty.

If you want to offer many you will return an associative array with an id that uses the following formatting for the key `{plugin_id}__{unique_id}` and then a human readable name. this will be given back to you in the settings array in buildCkEditorModalForm.

Check how  [AI Automators](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_automators/src/Plugin/AiCKEditor/AiAutomatorsCKEditor.php?ref_type=heads) solves it for instance.

### Example

We want to have a plugin that replaces all mean words with nice words, it would look something like this.

~~~php
<?php

namespace Drupal\my_module\Plugin\AICKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;

/**
 * Plugin to make the selected text nicer.
 */
#[AiCKEditor(
  id: 'do_not_be_mean',
  label: new TranslatableMarkup('Nicefy'),
  description: new TranslatableMarkup('Remove all the mean words.'),
)]
final class Nicefy extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'provider' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Let the user make a provider choice.
    $options = $this->aiProviderManager->getSimpleProviderModelOptions('chat');
    array_shift($options);
    array_splice($options, 0, 1);
    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider'),
      '#options' => $options,
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];

    return $form;
  }

    /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Store the provider.
    $this->configuration['provider'] = $form_state->getValue('provider');
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    // Check the storage for selected text.
    $storage = $form_state->getStorage();
    // Get the parent editor id.
    $editor_id = $this->requestStack->getParentRequest()->get('editor_id');

    // If no selected text exists, fail.
    if (empty($storage['selected_text'])) {
      return [
        '#markup' => '<p>' . $this->t('You must select some text before you can summarize it.') . '</p>',
      ];
    }

    // Get the parent.
    $form = parent::buildCkEditorModalForm($form, $form_state);

    // The input is the selected text.
    $form['selected_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Selected text to summarize'),
      '#disabled' => TRUE,
      '#default_value' => $storage['selected_text'],
    ];

    // The response to handle over.
    $form['response_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Suggested summary'),
      '#description' => $this->t('The response from AI will appear in the box above. You can edit and tweak the response before saving it back to the main editor.'),
      '#prefix' => '<div id="ai-ckeditor-response">',
      '#suffix' => '</div>',
      '#default_value' => '',
      '#allowed_formats' => [$editor_id],
      '#format' => $editor_id,
    ];

    $form['actions']['generate']['#value'] = $this->t('Summarize');

    return $form;
  }

  /**
   * Generate text callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The result of the AJAX operation.
   */
  public function ajaxGenerate(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    try {
      // The actual prompt blending with the selected text.
      $prompt = 'Look at this text and remove all mean words with nice words, but keep it as it is:\r\n"' . $values["plugin_config"]["selected_text"];
      $response = new AjaxResponse();
      $values = $form_state->getValues();
      // Set the ajax command to run.
      $response->addCommand(new AiRequestCommand($prompt, $values["editor_id"], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      // Trigger it.
      return $response;
    }
    catch (\Exception $e) {
      $this->logger->error("There was an error in the Nicefy AI plugin for CKEditor.");
      return $form['plugin_config']['response_text']['#value'] = "There was an error in the Nicefy AI plugin for CKEditor.";
    }
  }

}
~~~
