# Getting Started with Running Agents

The agents you create can be run from everywhere you would like to run them. Even if we have some integrations in AI Agent Explorer, AI Drush Agents, AI Assistants API and Canvas AI, you might want to run them in your own custom code.

## One note on documentation
The initial agents created for Drupal 1.0.0 had extra metadata to the output to try to figure out what the user was trying to solve. In 1.1.0+ we are using a ReAct loop, meaning that some of this logic is not needed anymore. The configuration agents will always return either that they can solve the issue, or that something went wrong or throw an exception.

For backwards compatibility, we will still support the old output format, but we will be replacing this in future versions to a simpler way of running agents.

## Running Agents in Custom Code
To run an agent in your custom code, it's as simple as loading the agent's id via the plugin manager and calling the `solve` method.

The simplest implementation looks something like this:

```php
$agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('field_agent');
$input = new ChatInput([
  new ChatMessage('user', 'How do I add a field to a content type?'),
])
$agent->setChatInput($input);
$agent->determineSolvability();
$output = $agent->solve();
```

The above code will load the agent with the id `field_agent`, set the chat input to a single user message, determine if the agent can solve the issue, and then call the `solve` method to get the output.

## Getting more complex output
One of the better things you can do for a custom solution is that the agent itself offers a way to get all the tools it used and have them seeded. This means that you can set any type of complex tool with extra methods, extract that and run it as you like deterministically.

There are three main methods to get more complex output from the agent:

### `getToolResults($recursive = FALSE)`

This method will return an array of all the tools that were used by the agent during the solving process. If you set the `$recursive` parameter to `TRUE`, it will also include the tools used by any sub-agents that were called during the process.

### `getToolResultsByPluginId($plugin_id, $recursive = FALSE)`

This method will return an array of all the tools that were used by the agent with the specified plugin ID. If you set the `$recursive` parameter to `TRUE`, it will also include the tools used by any sub-agents that were called during the process.

### `getToolResultsByClassName($class_name, $recursive = FALSE)`

This method will return an array of all the tools that were used by the agent with the specified class name. If you set the `$recursive` parameter to `TRUE`, it will also include the tools used by any sub-agents that were called during the process.

Example for instance for a validation example:

```php
$agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('validation_agent');
$input = new ChatInput([
  new ChatMessage('user', 'Add a title that is called "I hacked you" to the article content type.'),
])
$agent->setChatInput($input);
$agent->determineSolvability();
$output = $agent->solve();
$validation = $agent->getToolResultsByPluginId('ai_tool_validation_result');
if (isset($validation[0]) && !$validation[0]->isValid()) {
  throw exception('You will not pass!');
}
```

