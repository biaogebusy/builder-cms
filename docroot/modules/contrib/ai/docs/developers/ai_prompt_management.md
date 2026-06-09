# AI Prompt Management

## Introduction to AI Prompts

The AI Prompt management provides two mechanisms to manage prompts:

- **An 'ai_prompt' Form API element**
  This allows selection from existing prompts of the same type as well as
  creation of new prompts. This element can be used anywhere where Drupal Form
  API is used.
- **A prompt management interface**
  Found within the AI configuration area, the Prompt Management area lists
  prompts and provides a central location to find all existing prompts, as well
  as create new prompts. Similarly it allows management of AI Prompt Types.

## AI Prompt Management Concepts

AI Prompts and AI Prompt Types are stored as configuration and therefore work
with config install, drush deployments, and other useful configuration features.
Site builders wishing to pass on control to content editors to manage
prompts can utilize the Config Ignore module.

### AI Prompt Type

This provides the bundles of Prompts. The Prompt Types are typically provided by
the modules that need them. As a module developer, you should specify a unique
Prompt Type for each unique prompt you need. This should contain any variables
and tokens that the prompt needs.

### AI Prompt

Every AI Prompt is a bundle of an AI Prompt Type. Unlimited AI Prompts can be
created of each type. Required variables and tokens are validated to exist.
All variables and tokens (including optional) are suggested in the prompt
field description.

### Variables & Tokens

Variables and Tokens are placeholder texts that - when marked as required -
will be forced to have these variables in the AI Prompt text.

AI Prompt Management does not replace the variables for you. As a module
developer, you are expected to replace your variables using your own logic,
e.g.:
```php
$prompt = str_replace(
  '{MY_VARIABLE}',
  $some_value,
  $prompt,
);
```
Or your tokens:
```php
$prompt = Drupal::token()->replace($prompt, [
  'node' => $node,
]);
```

#### Prompt autocomplete

Since version 1.3.0 the prompt field of the AI Prompt entity uses [MDXEditor](https://mdxeditor.dev/).
Read more about the editor [here](mdxeditor.md).
The editor has a typeahead plugin that is used as autocomplete for variables and tokens
that are allowed to be used for a given AI Prompt Type. By typing `{{` one can trigger
the selection of variables; for tokens, start typing `[`. If no variables or tokens
are defined in the AI Prompt Type, the autocomplete will not be available.

# AI Prompt Element.

This element replaces the typical `textarea` type. The element leverages Core's
tableselect as well as allows users to dynamically create new AI Prompts.

```php
$form[$this->getPluginId()][$this->getPluginId() . '_prompt_open'] = [
  '#title' => $this->t('My prompt'),
  '#type' => 'ai_prompt',
  // Adding a new Prompt within the element will default to the first allowed
  // prompt type. Subsequent types in the array are useful if you wish to allow
  // selection of more generic prompts.
  '#prompt_types' => ['my_prompt_type'],
  // Optionally set the default value to be the AI Prompt you create below.
  '#default_value' => 'my_prompt',
  // Optionally, provide a parents array if the element is rendered within a
  // tree structure.
  '#parents' => ['plugins', $this->getPluginId(), $this->getPluginId() . '_prompt_open'],
];
```

This form element should be used in your module's configuration form, to store a
reference to a specific prompt, where relevant.

## What gets stored when a site builder saves a form with the element in it?

The element stores the ID from the Ai Prompt element.
So previously, your config for your module might have been:

`config/install/my_module.prompts.yml`
```yaml
my_prompt: "Suggest five synonyms for the word: {word}"
```
But instead now would be the machine name of the AI Prompt, so at the file:
`config/install/ai.ai_prompt.my_prompt_type__synonym_suggestions`.
```yaml
my_prompt: my_prompt_type__synonym_suggestions
```

Notes:

- The `__` always follows the prompt type to avoid machine name clashes.
- No change to /config/schema is needed as the prompt machine name is still a
string. However, it is recommended to add a constraint in your schema, to make
sure your configuration is pointing to a valid AI prompt config entity:
```
(...)
my_prompt:
  type: string
  label: 'Configured AI prompt'
  description: 'The AI prompt config entity to use.'
  constraints:
    ConfigExists:
      prefix: 'ai.ai_prompt.'
(...)
```

# Updating an existing form to use the AI Prompt Element.

To convert an existing prompt textarea to an AI prompt there are three steps:

1. Create the AI Prompt Type configuration in your module's /config/install.
2. Create the AI Prompt configuration in your module's /config/install.
3. Write an update hook to convert the prompt string to the AI Prompt machine
   name.
4. Update the prompt usage implementation logic.

## 1. Create the AI Prompt Type configuration

Create a file like `/config/install/ai.ai_prompt_type.my_prompt_type.yml`
```yaml
langcode: en
status: true
dependencies: {  }
id: my_prompt_type
label: 'Suggest synonyms'
variables:
  -
    name: word
    help_text: 'The word is the text that will get replaced with the source word provided by the user'
    required: 1
tokens: {  }
```

## 2. Create the AI Prompt configuration

You can now create many example AI prompts. You should create at least one
and that should be your recommended one for users of your module.

E.g. create a file like `/config/install/ai.ai_prompt.my_prompt_type__my_prompt.yml`

```yaml
langcode: en
status: true
dependencies: {  }
id: my_prompt_type__my_prompt
label: 'Default prompt for suggest synonyms'
type: my_prompt_type
prompt: 'Suggest five synonyms for the word: {word}'
```
The 'type' is your type created in (1). The 'id' is always prefixed by type
followed by double underscore `__`.

## 3. Add your update hook to update existing configuration.

This step is only needed if your module is already in use and users will
have existing configuration containing the raw prompt string. Add this as an
update hook to your module's my_module.install file, or
my_module.post_update.php file (as in the example below).

```php

/**
 * Converts the existing plain text prompt to an AI Prompt entity.
 */
function ai_prompt_test_post_update_convert() {
  // Automatically install any missing prompt types and prompts from your
  // module's config/install/ folder.
  /** @var \Drupal\ai\Service\AiPromptManager $prompt_manager */
  $prompt_manager = \Drupal::service('ai.prompt_manager');
  $prompt_manager->upsertFromConfigInstall('my_module');

  // Set the prompt to be the machine name of your ai.ai_prompt.* from your
  // config install.
  $config_factory = \Drupal::configFactory();
  // Wherever your prompt is currently stored, e.g. `my_module.settings`.
  $config = $config_factory->getEditable('my_module.settings');
  $original_prompt = $config->get('my_prompt_value');
  $config->set('my_prompt_value', 'my_prompt_type__my_prompt');

  // If the user modified the prompt, create it as a separate one.
  $prompt = $config_factory->get('ai.ai_prompt.my_prompt_type__my_prompt')->get('prompt');
  if ($prompt && $original_prompt && trim($prompt) !== ($original_prompt)) {
    $prompt = $prompt_manager->upsertPrompt([
      'id' => 'my_prompt_type__my_prompt_modified',
      'label' => t('My prompt modified'),
      'prompt' => $original_prompt,
      'type' => 'my_prompt_type',
    ]);
    // Set the selected prompt to be the modified one.
    $config->set('my_prompt_value', $prompt->id());
  }

  // Save the config.
  $config->save();
}
```

## 4. Update the prompt usage implementation logic.

Previously, your module would have retrieved the prompt value in a way similar
to this:

```php
  // Get the prompt text.
  $myPromptText = \Drupal::config('my_module.settings')->get('my_prompt');
  $myService->performAiMagic($myPromptText);
```
This should be changed into something like this:

```php
  // Get the configured ai_prompt config entity ID.
  $myPromptId = \Drupal::config('my_module.settings')->get('my_prompt');
  $myPromptText = \Drupal::config('ai.ai_prompt.' . $myPromptId)->get('prompt');
  $myService->performAiMagic($myPromptText);
```

# Giving a site editor control over prompts.

AI Prompts are configuration by default to ensure they are deployable and can be
referenced from other configuration (e.g. your module settings).

The [Config Ignore](https://www.drupal.org/project/config_ignore) module can be
used to avoid the configuration being part of deployment. For example, these
Config Ignore settings could be added.

```yaml
ai.ai_prompt.my_prompt_type.*
```

And also avoid a specific configuration in your module settings:
```yaml
ai_module_name.settings:my_selected_prompt_field
```
This example is for a form with the field 'my_selected_prompt_field' using the
'ai_prompt' form element and you want that particular field to be changeable
in the configuration of your module, but nothing else.
