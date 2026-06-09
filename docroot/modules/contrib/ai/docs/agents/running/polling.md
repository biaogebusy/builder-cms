# Polling for Agent Progress

When you are setting up a UI for agents, one of the worst things that can happen is that the user has to wait for minutes for some complex agent and the only thing they see is a loading spinner. Many users will assume that something is broken and leave the page.

Another issue is that we might want to debug what the agent is doing, while we are developing for it. Any debug tool, needs to be able to show the progress of the agent, what tools it has used, what contexts it has added and so on.

A third use case is that you might want to log the progress of the agent, so that you can see what it did, and if something went wrong, you can figure out what happened.

We have a progress service for this!

## The Poller Service
The progress service is a service that you can use to poll for the progress of an agent. It is a simple service that you call with some unique identifier when you start an agent and then you can poll for the progress of that agent using the same identifier.

The poller service is granular, meaning that you can tell the service before you start the agent, what type of progress you want to track. All events have some base data like the microtime started, runner id of the current agent, the caller id of a sub-agent. You can track the following events in order of when they occur:

* **AgentIterationExecutionInterface** - This gets called every time the agent starts a new iteration in the loop.
* **AgentChatHistoryInterface** - This gets the chat history of the input and the loop number.
* **SystemMessageInterface** - This gets called when the system adds a system message.
* **ToolStartedExecutionInterface** - This gets called when the tool starts executing. This only happens when a tool is selected by the agent on the previous iteration.
* **ToolFinishedExecutionInterface** - This gets called when the tool has finished executing and has the output. This only happens when a tool is selected by the agent on the previous iteration.
* **AiProviderRequestInterface** - This is called right before we call the LLM provider
* **AiProviderResponseInterface** - This is called right after we get the response from the LLM provider and has the whole chat output as an array.
* **TextGeneratedInterface** - This gets the text that was generated from the LLM.
* **ToolSelectedInterface** - This gets the tool that was selected by the agent.
* **AgentResponseExecutionInterface** - This gets the textual response from the LLM for each iteration when the agent is finished or starting another loop.
* **AgentFinishedExecutionInterface** - This gets the final output of the agent when it has finished executing.

### Filtering the progress events
When you start the poller service, there is a method called `setDetailedProgressTracking(array $detailed_progress_tracking)` where you can set what type of events you want to track. By default, all events are tracked, but you can disable some of them if you want to reduce the amount of data that is stored. The array takes an enum from the AiAgentStatusItemTypes class.

### How to setup the poller service when starting an agent
To run the poller service, you need to do one of the following:

* Use the method setProgressThreadId($thread_id) on the agent before you call the determineSolvability() method. This will start the poller service with the given thread id. This is recommended.
* Use the method setProgressTracking(TRUE) on the agent before you call the determineSolvability() method. This will start the poller service with a random thread id that you can get by calling getProgressThreadId() after you have called determineSolvability().

NOTE: The storage of the poller service is an interface, so in the future new ways of storing this might be added. Currently there is only a PrivateTempStore implementation to make sure that the data is not stored permanently and that is secure for the user. This means that this will only work on anonymous users if you start a session for them. This also means that you can have the same thread id for different users, if you are sure that your application will not run in multiple tabs or browsers at the same time. Or with shared sessions/accounts.

Since you normally can't output information, unless you stream it, it is recommended that you use the thread id method, so that you can store the thread id in your UI and poll for it. The other option is that this is stored in a session or cookie.

Here is an example of how to setup the poller service when starting an agent:

```php
// Start the storage and poller service if you reuse thread ids.
$poller_storage = \Drupal::service('ai_agents.private_temp_status_storage');
$poller_storage->deleteStatusUpdate('my-unique-thread-id'); // Delete the status update from the previous run if it exists and you want to reuse the same thread id.

$agent = \Drupal::service('plugin.manager.ai_agents')->createInstance('field_agent');
$input = new ChatInput([
  new ChatMessage('user', 'How do I add a field to a content type?'),
])
$agent->setChatHistory($input);
$agent->setProgressThreadId('my-unique-thread-id'); // Set a unique thread id
$agent->setDetailedProgressTracking([
  AiAgentStatusItemTypes::Started,
  AiAgentStatusItemTypes::ToolStarted,
]); // Set the detailed progress tracking to something specific.
$agent->determineSolvability();
$output = $agent->solve();
```

### How to poll for the progress of an agent
To poll for the progress of an agent, you need to use the `ai_agents.agent_status_poller` service and call the `getProgress($thread_id)` method. This will return an array with implementation of each of the interfaces mentioned above. All those classes have a toArray() and toJson() method to make it easy to output the data.

Here is an example of how to poll for the progress of an agent:

```php
$poller = \Drupal::service('ai_agents.agent_status_poller');
$progress = $poller->getLatestStatusUpdates('my-unique-thread-id'); // Use the same thread id as when you started the agent.
foreach ($progress->getItems() as $event) {
  // Do something with the event.
  print_r($event->toArray());
}
```

### How to remove the progress of an agent
When you are done with the progress of an agent, you can remove it from the storage by calling the `clearProgress($thread_id)` method on the `ai_agents.agent_status_poller` service. This will remove all the data for that thread id. Since it is PrivateTempStore, it will also be removed automatically after some time.

This is recommended to do if you want to reuse the same thread id for another agent run and to reduce the amount of data stored. One nice way of doing it, is that you remove it when the next agent run starts, instead of directly after the agent is done, in case you want to show the progress of the last run.

## Making nicer output messages
One problem with the current output is that it is very raw - so you could write "Running tool X" or "Calling LLM with Y" - but that is not very user friendly. What exists in the agent form as part of this, is that for each tool you can in the UI, set a feedback message.

When you get the tool events, there is a method called `getToolFeedbackMessage()` that will return the feedback message if it is set. This means that you can set a nice message for the user to see, instead of just the raw tool name.

Notice that the same tool is used in loop multiple times, so a nice way of implementing this is to keep track of the message and only show it the first time it is used, or if the input to the tool has changed.

Here is an example of an ugly controller solution that refreshes itself over and over:

```php
$progress = \Drupal::service('ai_agents.agent_status_poller')->getLatestStatusUpdates('my-unique-thread-id');
$tools = [];
$finished = FALSE;
foreach ($response->getItems() as $item) {
  if ($item->getType() == AiAgentStatusItemTypes::ToolStarted) {
    // Reset tools array when a new tool is picked.
    $name = "Running " . $item->getToolName();
    if ($item->getToolFeedbackMessage()) {
      $name = $item->getToolFeedbackMessage();
    }
    $tools[$item->getToolId()] = [
      'name' => $name,
      'class' => 'running',
    ];
  }
  if ($item->getType() == AiAgentStatusItemTypes::ToolFinished) {
    $tools[$item->getToolId()]['class'] = 'finished';
  }
  if ($item->getType() == AiAgentStatusItemTypes::Finished) {
    $finished = TRUE;
  }
}
echo "<style>li.starting { color: orange; } li.running { color: blue; } li.finished { color: green; }</style>";
echo "<ul>";
$last_status = '';
$shown_tools = [];
foreach ($tools as $tool) {
  if ($last_status != $tool['name']) {
    $shown_tools[] = [
      'name' => $tool['name'],
    ];
  }
  $last_status = $tool['name'];
}
foreach ($shown_tools as $key => $tool) {
  $class = $key == count($shown_tools) - 1 ? 'running' : 'finished';
  if ($finished) {
    $class = 'finished';
  }
  echo "<li class='" . $class . "'>" . $tool['name'] . "</li>";
}
echo "</ul>";
if (!$finished) {
  echo '<meta http-equiv="refresh" content="1">';
}
```

## Letting the user know that the agent is done and will write a response
One issue with the current output is that the user might not know when the agent is done and will write a response. Since the agent can do multiple loops, it might be hard to figure out when the agent is done until it decides to write a text response without calling any tools.

Because of this we have added a tool called "Preparing Response", that you can use and explicitly tell the agent to use in the last iteration, when it is done with all the tools and is ready to write a response. This tool can then be used to show a nice message to the user that the agent is done and will write a response.
