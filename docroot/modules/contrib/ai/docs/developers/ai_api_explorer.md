# AI API Explorer
## Purpose of this module
This module provides a UI to allow admins to test various LLM prompts against
different LLMs to refine the prompts. The form passes a prompt to an LLM and
displays the result, but in most cases doesn't perform any further actions with
the output (some of the forms that generate file, audio or image outputs do
provide the ability to store the output as a Media item, but it is not intended
to be used for bulk generation).

## Providing your own forms
The forms themselves are provided by a [Drupal plugin](https://www.drupal.org/docs/drupal-apis/plugin-api).
To provide your own plugin, create a directory called AiApiExplorer in your
module's src/Plugin folder and add a plugin file that extends the
AiApiExplorerPluginBase. This implements the AiApiExplorerInterface interface,
which details the required methods. Please be aware that some of these are
implemented by default by the base plugin, but may be overridden.

The plugin will, as a minimum, need to:

1. Have an id, label and description annotations. These will display within the
   automatically generated menu item for the plugin.
2. Implement isActive() to determine if the plugin can be used.
3. Implement the buildForm() method to build the form to show to users. The base
   plugin provides a getFormTemplate() method that will return a bare bones form
   to add elements to. Your form will also need to provide an AJAX submit button
   with the output of $this->getAjaxResponseId() as its callback.
4. Implement the getResponse() method, which will be called during the AJAX
   submission and should send the form's prompt to its chosen LLM operation and
   rebuild the form with the response in the correct section. The method should
   return only the section of the form that will be replaced by the AJAX
   submission.

Any plugin will automatically have a route and menu item generated to show the
form within the AI API Explorer module's admin pages. There is no requirement
for you to add routing or menu files into your module for your form to be used.

The AI API Explorer module implements a number of plugins, which can be used as
examples to aid your development:

- The AI Search module's SearchGenerator plugin demonstrates a more complex
  method of deciding if the plugin should be active.
- The AudioToAudioGenerator plugin demonstrates how to send a file to the LLM,
  and display a file within a media player for the user to interact with.
- The ChatGenerator plugin demonstrates how to display the form within a
  three-column display instead of the default two-column.
- The ChatGenerator plugin also demonstrates how to add debug information to the
  AJAX response to demonstrate to users how to code the call they have just
  performed, and how to handle streamed responses.