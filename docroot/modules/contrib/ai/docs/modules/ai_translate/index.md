# AI Translate
## What is the AI Translate module
The AI Translate module integrates with Drupal's content translation system to
allow passing content to an LLM to generate a translation.

## Dependencies
The AI Translate module requires the AI Core module to be installed and
configured, and a valid AI Provider module to be enabled and configured.

The module also requires Drupal's Content Translation module to be enabled and
configured.

## Installation & configuration
**Note**; if you have not set up your translation languages before you configure
this module, you may get unexpected results.

1. Enable the module
2. Navigate to /admin/config/ai/ai-translate
3. Select the model from your providers that you wish to use.
4. Enter a prompt to be sent to the LLM when translations are requested. You
   will have the option to enter a different prompt for each configured
   language: you must enter a prompt for each language - and **update the
   configuration each time a new language is enabled on your site** - or you 
   will get unexpected results.
5. Select the entity types you wish to be translated automatically when linked
   to via an entity reference field on the content.

## Usage
1. Navigate to your translatable content.
2. Click the "Translate" tab
3. Click on the link in the "AI Translations" column for your desired language.
4. Your configured prompt and the default language version of your content will
   be sent to your chosen LLM, and a translation in the selected language
   automatically created. **It is highly recommended that this translation is
   reviewed manually** as the translation is automatically created from the LLM
   response: in cases of errors, this will be the error message sent by the LLM.

## Recommended modules
- If you are using async translations and paragraphs, we recommend the
  [Translate Paragraph Asymetric (with AI)](https://www.drupal.org/project/ai_translate_paragraph_asymetric) module.

## Layout builder
If you are interested in Layout Builder integration with this module, please see [this issue](https://www.drupal.org/project/ai/issues/3467075).

## Similar modules
- https://www.drupal.org/project/ai_tmgmt Ai Translation management tools (use tmgmt with the ai module as a translation provider)
- https://www.drupal.org/project/auto_translation The Auto translate module is very similar to this one.
- https://www.drupal.org/project/ai_translate_textfield Translate textfield also uses AI but the interface is substantially different. You have to translate on a per field basis. The approach is different.
