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