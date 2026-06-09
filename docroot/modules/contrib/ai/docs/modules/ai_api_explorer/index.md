# AI API Explorer
## What is the AI API Explorer module?
This module provides a number of forms which a site builder can use to test out
the results of various API calls, and in some cases obtain boilerplate code to
recreate those calls in a module. **This module is only intended to be used as
part of development:** it should only be used to test a given prompt against a
selected LLM.

The forms which will appear depend on the specific modules and providers you
have configured. Other modules may provide additional forms provided as they
require: please see the developers section for more details.

## How to use the module
On your site, visit /admin/config/ai/explorers to see which specific explorers
you have. Clicking on their menu item will take you to a form which will collect
any information required to make your API call: this may include text or file
uploads depending on the nature of the API.

The form will show you any configuration options for the provider being used,
allowing you to test alternative configuration to find which provides the most
suitable results.

When you have entered your data and settings, submitting the form will send your
data to the chosen LLM. The response will be shown within the form once the LLM
responds. Some forms will also provide code examples along with the LLM
response, allowing developers to generate boilerplate code easily.