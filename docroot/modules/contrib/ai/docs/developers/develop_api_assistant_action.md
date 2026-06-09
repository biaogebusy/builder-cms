## API Assistant Actions
### What is an API Assistant Action?
AI Assistant Actions implement Drupal's inbuilt [Plugin API](https://www.drupal.org/docs/drupal-apis/plugin-api/plugin-api-overview)
to provide ways for a configured AI Assistant to interact with the Drupal site
it has been installed on. Given that a mis-coded plugin could compromise the
security of the site it is installed on, or cause legal issues for the site
owners or even the original developers of the plugin, **it is recommended that
API Assistant Action plugins are only implemented by developers with experience
with both Drupal and LLMs**.

### The ApiAssistantActionInterface
The plugin itself is a Drupal configurable plugin with a form. Advanced users
can create a new plugin in their module's /src/Plugin/AiAssistantAction folder
that implements the ApiAssistantActionInterface for complete control of the
process. However, the majority of the plugins will share a number of
requirements - the ability to access the configured AI Assistant, for example -
so the AiAssistantActionBase has been created to help reduce duplication. In
most cases, we would expect developers to extend this base class to provide new
action plugins.

### The AiAssistantActionBase class
Developers can create a new plugin in their module's
/src/Plugin/AiAssistantAction folder that extends the AiAssistantActionBase base
class. This class implements the ApiAssistantActionInterface and provides a
Container Factory to allow additional required services to be injected as
required by developers.

If the plugin requires specific configuration to be set by site builders, the
buildConfigurationForm(), validateConfigurationForm() and
submitConfigurationForm() methods will probably need to be overridden in your
plugin.

The plugin itself can implement multiple actions, to prevent duplication between
actions that require similar coding. The listActions() method should be
implemented to provide details of each action that the plugin can perform,
including a unique id and user-readable label.

The unique id will be passed to the triggerAction() method when the AI Assistant
API triggers the action, allowing the method to perform whatever action is
desired. The action should set the output context when it runs, using the
setOutputContext() method. This will allow the API to pass results to the LLM
and to the user as required.

The plugin must also implement the provideFewShotLearningExample() method. This
is used to provide a Few-Shot Learning (FSL) example to the AI on how to trigger
this action. It should give back one or more examples in an array and the AI
will learn from this. **Implementing this may require advanced knowledge of LLMs
and their workings**.

### Examples
For working examples, please see the code of the [AI search](../modules/ai_search/index.md)
sub-module or the [AI Agents](https://www.drupal.org/project/ai_agents) module.

For an example of how to provide a user-facing interface for an AI Assistant,
please see the AI Chatbot module's code: this provides a user-facing block
plugin that uses a configured AI Assistant to allow users to interact with an
AI Assistant and see responses from the LLM.
