### Writing an AI Automators plugin.
The AI Automators module allows field values to be set based on the output of an
AI Provider Operation. This is done using [Drupal's plugin system](https://api.drupal.org/api/drupal/core%21core.api.php/group/plugin_api/10).

To add new functionality to the module, you will need to implement a Drupal 
plugin that extends the RuleBase entity (`modules/ai_automators/src/PluginBaseClasses/RuleBase.php`).
The ->generate() method is where the output is generated: this does not
necessarily have to use an LLM (for example if your plugin is used to process
output in an AI Automator Chain ready to be passed to an LLM or inserted into a
specific field type) but it MUST return an array of values.

For an example, see any of the plugins in `modules/ai_automators/src/PluginBaseClasses`,
or the Example Plugin at `modules/ai_automators/src/Examples/AiAutomatorType/StripTags.php.example`,
which demonstrates stripping tags from HTML and returning the value without use
of an AI.

### Writing an AI Automator Field Widget Action (FWA) plugin
The AI Automators module integrates with the Field Widget Actions module to provide AI-powered actions directly on field widgets (like a "Generate" button).

Thanks to the `AutomatorBaseAction` base class, adding a new FWA plugin for a standard widget is incredibly easy. For most standard widgets where the form input structure maps directly to the field storage structure (such as simple text fields, booleans, and lists), you do not need to write any custom PHP logic. 

All you need to do is:
1. Create a new class that extends `\Drupal\ai_automators\Plugin\FieldWidgetAction\AutomatorBaseAction`.
2. Add the `#[FieldWidgetAction]` attribute to define the plugin, specifying the supported `widget_types` and `field_types`.

#### Example: A basic text automator FWA plugin
```php
<?php

namespace Drupal\my_module\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\ai_automators\Plugin\FieldWidgetAction\AutomatorBaseAction;

/**
 * An example automator action for text fields.
 */
#[FieldWidgetAction(
  id: 'my_module_automator_text',
  label: new TranslatableMarkup('Generate text'),
  widget_types: ['string_textfield', 'text_textarea'],
  field_types: ['string', 'text_long'],
  category: new TranslatableMarkup('AI Automators'),
)]
class MyTextAction extends AutomatorBaseAction {

}
```

#### Advanced FWA plugins
The `AutomatorBaseAction` handles the AJAX callback, form rebuild, and populating values into `$form_state->setUserInput()` using a standardized `setFormInput` contract.

If you are dealing with complex widgets where the form structure differs from the field storage (e.g., media library widgets, compound fields):
- Override `transformFormInput(ComplexDataInterface $item)` to map the stored field item values into the shape expected by the widget's user input.
- Override `setFormInput()` if your widget uses `multiple_values=TRUE` and expects a flat array rather than per-delta arrays.
- Refer to the plugins in `modules/ai_automators/src/Plugin/FieldWidgetAction/` for examples of handling more complex structures.