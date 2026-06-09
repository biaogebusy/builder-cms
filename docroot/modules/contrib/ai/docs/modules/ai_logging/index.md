# AI Logging
## What is the AI Logging module
The AI logging module allows developers to capture prompts sent to and outputs
received from LLMs by other modules and store them as entities to allow for
review and debugging. Whilst this module is safe to use on a production
environment, it stores a large amount of data into the database and so is
**recommended only to aid local development**.

## Dependencies
This module requires:

1. The AI Base module to be enabled and configured.
2. The core Views module to be enabled (for displaying the log messages).

This module sends no data to LLMs itself, so whilst it does not list any other
modules as dependencies, it will provide no functionality unless at least one 
Provider module is enabled and configured and at least one module providing AI
functionality is also enabled.

## How to configure the AI Logging module
The module should be enabled as normal, which will add the bundlable AI Log
entity to the site. However, due to the large amount of data logging produces,
no logging will begin until the module is configured:

1. Visit /admin/config/ai/logging/settings
2. Check the "Log requests" checkbox to enable logging of requests.
3. If you also wish to log the response from the LLM, check the "Log response"
   checkbox.
4. All requests sent to an LLM are tagged with the operation type being used: if
   you only wish to log the request and/or response from specific operations,
   enter their names in the "Request Tags" box. Multiple tags should be added as
   a comma-separated list. To find out more about the Operation Types, please
   refer to the [Making AI Base Calls](https://project.pages.drupalcode.org/ai/developers/base_calls/)
   section of the documentation.
5. Enter the maximum number of log entities to retain: once the number of log
   entries exceeds this limit, the earliest logs will be deleted.
6. Enter the number of **days** to keep log messages for. Once any log message
   exceeds this age, it will be deleted regardless of the maximum retained
   number of logs.
7. Save the configuration, and logging will begin with the next request sent to
   an LLM. Logs can be viewed at /admin/config/ai/logging/collection.

## Using the logs
The request log entities will store the following data:

1. **The provider**: The name of the provider used by the call. 
2. **The model**: The ID of the model used.
3. **The operation type**: The operation type used by the provider.
4. **The configuration**: The configuration of the specific model used.
5. **The tags**: Any tags associated with the response: by default this will be the operation type.
6. **The prompt**: The actual prompt sent to the LLM.
7. **Extra data**: Any relevant additional data that may assist with debugging.
8. **The response**: If configured, a JSON encoded copy of the raw LLM response will be stored.
9. **The dates**: The underlying entity will store the created and modified dates for the record.

The logs are intended to be used short-term to assist developers with crafting
their own prompts or custom code using the AI Base module's services. More
general logging of LLM requests may have performance, data protection and privacy
implications that developers should consider before enabling this module on a
public-facing site.

## The AI Log entity
The log entity is fieldable and bundlable, and can be edited and configured by
administrators with the correct permissions. However, the AI Logging module will
not be aware of any additional fields or bundles added to the entity: if you
wish your log items to use these, you will need to use your own code to create
or populate them.

The AI Logging module utilises the AI Base module's PostGenerateResponseEvent to
log information from an LLM request: to use a bundle other than the default
"generic" log type you will need to add your own custom event subscriber that
duplicates the functionality of the LogPostRequestEventSubscriber. To prevent
duplicate log entities, logging should then be disabled in the settings for the
module. 

Depending on the data required by additional fields, it may be possible to
update the generic AI Log entity using a pre-save or update hook.

## AI Logging tags
The tags added to AI Log entities are added in code during the call to the
provider's operation type. Because of this, they are currently hardcoded in the
existing AI-related modules and cannot be altered or deleted. To implement
custom tags, a developer will need to write their own code to call a provider's
operations using the desired tags.

[This issue](https://www.drupal.org/project/ai/issues/3485686) provides a method
of altering, deleting or adding tags before the LLM request is logged.