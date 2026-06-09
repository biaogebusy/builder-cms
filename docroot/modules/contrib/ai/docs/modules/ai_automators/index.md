# AI Automators
## What is the AI Automators module?
This module offers the possibility for field content to be generated, amended or
evaluated by AI or other tools. The module provides a variety of plugins for
different field types, which can be triggered individually or in sequence
depending on your specific needs.

## Dependencies
1. The AI Core module needs to be installed and configured with a working AI
   Provider.
2. The [Token module](https://www.drupal.org/project/token) is required to
   assist with using tokens in prompt-based AI Automators.
3. The Field UI module needs to be enabled to add or alter AI Automators. Once
   you have your desired AI Automators in place, it can be uninstalled again so
   it will not be automatically enabled when this module is enabled.

### Additional modules
Some of the other sub-modules provided by the AI Core module interact with the
AI Automators, and you may wish to use them depending on how complex your use
case is.

1. The AI CKEditor Integration module can be optionally enabled to allow content
   creators to choose to run selected AI Automators against WYSIWYG fields. See the
   [Advanced section](#advanced-usage) for more details.
2. The AI ECA module integrates AI Core with the [ECA](https://www.drupal.org/project/eca) module
   and can be used to trigger AI Automators within ECA workflows.

## How to use the AI Automators module
The AI Automators module is extremely flexible, and will interact with the
modules installed on your site to allow use in a number of different ways.

### Basic Usage
By default, the module will allow a single AI Automator to be added to a field
on an entity. **In order to add or configure the AI Automator you MUST have the
Field UI module enabled**.

1. Go to the "Manage Fields" tab of your entity type.
2. Select the field you wish to add/configure the AI Automator on and click its
   "edit" operation.
3. The standard field settings edit form will display. If there are AI
   Automators compatible with your field type, a "Enable AI Automator" checkbox
   will be towards the bottom of the form.
4. When you click "Enable AI Automator", a sub form will open showing the
   settings for your available AI Automators. If more than one AI Automator is
   compatible with your field, you will see them listed in the "Choose AI
   Automator Type" dropdown. Select the one you wish to add.
5. The sub form for your selected AI Automator will appear below the dropdown.
   This form may contain different elements depending on the selected AI
   Automator: follow the instructions in the forms to configure your choice.
   Some settings are shared across multiple AI providers, such as:
    1. **Automator Input Mode**: A dropdown to select the method of applying the
       AI Automator. Basic allows you to specify a single field attached to the
       entity to provide the context for the AI Automator; Advanced Mode (Token)
       allow you to use supported tokens (see [the Token module for more information](https://www.drupal.org/project/token))
       to inject multiple conditional values into the context.
    2. **Automator Base Field**: Selects the field to use the value of to provide
       context to the AI.
    3. **Automator Prompt**: Allows you to add a custom prompt to send to the AI
       to explain what you want it to do. For some more complex AI Automators,
       placeholder text will be shown to detail any requirements on the prompt
       structure.
    4. **Automator Prompt (Token)**: As above, except with additional token
       support.
    5. **Edit when changed**: Check to update the generated field's content every
       time the source field is amended. Leave unchecked to generate the field
       only when it is empty.
    6. **Advanced Settings**: Click this to reveal any additional advanced
       settings for the AI Automator (some detailed below).
    7. **Automator Label**: The label that will display in the admin interface
       for this automator.
    8. **Automator Weight**: Controls the order multiple AI Automators on this
       entity will be run in: lower weights will run before higher weights,
       allowing you to generate a field value based on a previously generated
       value.
    9. **Automator Worker**: Control how the automator is run:
        1. Direct: runs the automator and stores the generated result as the
           entity is saved. This provides the result sooner but may slow down the
           save process.
        2. Batch: runs the automator in a batched process controlled by
           javascript. Can prevent issues where there are large numbers of AI
           Automators running on the same entity save, but **will not trigger the
           AI Automator if the entity is saved programmatically**.
        3. Queue/Cron: runs the AI Automator in a cron-controlled queue, meaning
           that the field value is generated the next time cron runs on your site
           after the entity is saved. This prevents issues from large numbers of
           processes running at save, but can cause large delays between save and
           field population, particularly if there are large numbers of AI
           Automators waiting to be processed across the site.
        4. Field Widget: processes the automator when triggered from a
           Field Widget Action button in the content edit form. This allows
           content creators to manually trigger AI generation on-demand via
           AJAX without saving the entity. Use this worker type when you
           want to add a "Generate" button to a field widget using the
           Field Widget Actions module. The generated values are populated
           directly into the form without saving the entity, allowing users
           to review and edit before saving.
    10. **AI Provider**: If you want to use a specific AI Provider to run the AI
        Automator, you can select it here. For more information about AI
        Providers, please see [the documentation here](https://project.pages.drupalcode.org/ai/providers/matris/).
    11. **Provider Configuration**: If you are not using the default AI Provider
        (see above), this is where you will see the specific settings for your
        chosen provider. Please refer to your AI Provider module's instructions
        for more information on filling these in.
    12. **Joiner**: if your AI Automator returns multiple values, you can select
        the method for them to be combined into the field's value: the default is
        to leave the values separate and let the AI Automator decide what to set
        as the value.
6. Save the Field's settings form and your AI Automator's settings will be
   stored with the other settings. Generation for the fields will be triggered
   when new content is saved, based on your chosen settings.

#### AI Automator weights
On save, the AI Automators configured for your entity type will be run according
to the weight value set for them, with lower weights running before higher. You
can set the weight on an individual AI Automator in its settings form, or they
can all be managed together on the AI Automator Run Order tab that can be seen
on the entity type's menu tabs. On this page, you can drag the AI Automators
into your preferred order (with JavaScript enabled) or set their weights in bulk
(with JavaScript disabled).

### Advanced usage
#### AI Automator Chains
One of the limits of the Basic usage of this module is that only one AI
Automator can be added to a single field, making running multiple tasks to
generate a value more difficult. This can be worked around by adding additional
fields to your entity type to carry out each step of the generation and hide
these from display. But this creates a large amount of extra data to be managed
entity by entity and introduces replication if you need to share functionality
between different entity types.

One solution to this issue is to use AI Automator Chains. These can be thought
of as dummy entity types with one field per AI Automator operation that you need
to be carried out. The AI Automator Chain takes a field value from a different
entity and passes it through each of the chain's fields to generate a final
output, which is then returned as the final output.

For example, if you wanted to generate a real world address for a location shown
in a photograph, your AI Automator chain would have a field for the input image,
a text field for the AI generated location the image was of, an address field to
store an address for the location, and lastly a field to store the actual output
to be returned after the chain has run. When run, the AI Automator chain would:

1. Populate the image field with your input image
2. Ask the AI to identify the location
3. Store the response in the text field
4. Use the text field as the input to ask the AI to obtain the address
5. Store the address in the address field
6. Use the address field to generate the final actual return.

In Drupal terms, the Automator Chain is a Bundleable Entity Type that
automatically creates a new entity for your chosen Chain Type and then runs all
the AI Automators configured on it as normal, before passing the value of the
output field to whatever has triggered the chain. The actual Automator Chain
entity is then deleted automatically as it is no longer needed. (Automator
Chains will appear in the listing at /admin/content/automator-chain, but this
is only for debug purposes: under normal operation this list will be empty.)

##### Using AI Automator Chains
Currently, AI Automator Chains are only supported through the AI CKEditor
Integration module or by utilising custom code. As such, they will not be
visible unless the AI CKEditor Integration module is enabled, or a developer has
placed some code in the site's setting.php file (for situations where they are
using Chains in custom code but do not have the AI CKEditor Integration module
enabled).

###### Add an AI Automator Chain
1. Visit the Automator Chain settings page (/admin/structure/ai/automator_chain_types)
2. Add a new AI Automator Chain for each group of processes you wish to run on a
   single input.
3. Add an input field of the correct type for the input that will be sent (for
   example an image field if the input is an image). **Mark the field as required**
   as this is how the AI Automator Chain identifies the input field. This field
   DOES NOT require an AI automator to be configured on it.
4. Add fields to hold each output from the AI Automator. Again, these must be of
   the correct type for the format of the AI response (text for text, address
   for an address, etc). These fields will need an AI Automator set up for them
   as per [the Basic usage instructions](#basic-usage), taking the input from
   the appropriate field before them in the chain.
5. Add an output field, again of the correct type for the AI Response.
6. Sort your AI Automators into the correct order for the chain to process the
   input correctly using the instructions in [the AI Automators weights section](#ai-automator-weights).

###### CKEditor Integration
1. Enable the AI CKEditor Integration module on your site. Please follow [its instructions](https://project.pages.drupalcode.org/ai/modules/ai_ckeditor/)
   for configuring the module.
2. Follow [the instructions for adding an AI Automator Chain](#add-an-ai-automator-chain).
   Note that with the AI CKEditor Integration, the correct format for Step 5
   will always be a WYSIWYG editor field and the AI should be instructed to
   format the field as valid HTML. This is because the output will be
   inserted into a WYSIWYG editor field on the original entity and any other
   format will not display correctly. The **Automator Worker** settings should
   ALWAYS be set to "Direct" for all Automators in your chain or else content
   creators will not receive an output to insert into their content.
3. When all required chain(s) have been added, visit the edit form for the Text
   Format you wish the chain(s) to be available in.
4. In the AI Tools settings, you will see a "AI Automators CKEditor" section
   that you should click to open.
5. Select the "Enabled" selector to turn on the integration for this text
   format.
6. You should then see sections for each of your AI Automator Chains. These will
   need to be enabled individually to become available. Depending on how your
   chain is configured, you will see a settings form for it to allow you to
   specify where the chain should obtain its input from. The current options are
   image or text, and you should choose the correct type for the input field you
   set up on the chain itself. You will also need to select your output field.
7. Save the Text Format. AI Automators will now be an option within the CK
   Editor toolbar when users are creating content using the configured Text
   Format. On selection, users will be presented with an appropriate form which
   will run the selected chain on submission and present them with the output.
   They will then have the option of inserting or discarding it.

If you would like to see a video of this process, please [visit YouTube](https://www.youtube.com/watch?v=PmChGwzilck).

###### Custom code
1. If you do not have the AI CKEditor Integration module enabled, you will need
   to add `$settings['ai_automator_advanced_mode_enabled'] = TRUE;` into your
   settings.php file. This will provide users (with the correct permissions)
   access to see and use the AI Automator Chain user interfaces. Once all chains
   have been created, the setting can be safely disabled again.
2. Follow [the instructions for adding an AI Automator Chain](#add-an-ai-automator-chain).
3. Implement your custom code to obtain an appropriate value, load the
   ai_automator.automate service and use the ->run() method to obtain the
   generated outputs. You will then need to set the values against the
   appropriate fields.

**The following code is intended to be illustrative and no warranty is implied
if you use it on your own site**. Please use it as a basis for developing your
own implementation.

```php
/**
 * Implements hook_entity_presave().
 */
 function MY_MODULE_entity_presave(EntityInterface $entity) {
   $input['AI_AUTOMATOR_CHAIN_INPUT_FIELD'] = $entity->get('MY_SOURCE_FIELD')->getString();

   /** @var \Drupal\ai_automators\Service\Automate $service */
   $service = \Drupal::service('ai_automator.automate');

   try {
     if ($output = $service->run('YOUR_AI_AUTOMATOR_CHAIN_MACHINE_NAME', $input)) {
       if (isset($output['AI_AUTOMATOR_OUTPUT_FIELD'])) {
         $entity->set('MY_DESTINATION_FIELD', $output['AI_AUTOMATOR_OUTPUT_FIELD']);
       }
     }
   }
   catch (Exception $e) {
     // Log error or deploy fallback.
   }
  }
```

## Field Widget Action Examples

For step-by-step examples of configuring AI Automators with Field Widget Actions (FWA) to add "Generate" buttons in content edit forms, see the [Examples](examples/index.md) section:

- [FAQ Field + `faqfield_default` widget](examples/faq-field.md) - Generate FAQ entries from content using the `LLM: FAQ Field` automator
- [Chart Field + `chart_config_default` widget](examples/chart-field.md) - Generate chart data from content using the `LLM: Chart From Text` automator
- [Text to Audio Media](examples/text_to_audio_media_automator.md) - Generate audio from content using the `LLM: Media Audio Generation` automator
- [Text to Image Field](examples/text_to_image_automator.md) - Generate image from content using the `LLM: Media Image Generation` automator
- [Text to Image Media Field](examples/text_to_image_media_automator.md) - Generate image from content using the `LLM: Media Image Generation` automator
- [Text to Address Field](examples/address_automator.md) - Generate address from content using the `LLM: Address` automator
- [Metatag](examples/metatag_automator.md) - Generate metatags for content using the `LLM: Metatag` automator

## Developer documentation
Check the [developers guide](../../developers/writing_an_ai_automators_plugin.md) for
information on how to write a third party module using the AI module.
