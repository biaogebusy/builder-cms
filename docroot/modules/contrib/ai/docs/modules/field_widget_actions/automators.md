# Field Widget Actions with Automators
## What is the Field Widget Actions module?
The Field Widget Actions module provides an easy way to attach automator-based action buttons to form widgets.

For example you could add an action button labelled «Generate tags» to the Tags field (field_tags) in the Article content type. When editing an article node, clicking this button would trigger the LLM Taxonomy automator associated with the field widget action button.

## Dependencies
The Field Widget Actions module requires the AI Core module to be enabled and configured with at
least one AI Provider enabled and installed that provides a moderation
operation. For more information on AI Providers and Operation Types, please see
[the documentation](https://project.pages.drupalcode.org/ai/developers/base_calls/).

## How to configure a Field Widget Action (FWA)
Configuring a «Generate tags» in the Article content type is a 2 step process.

Step #1: Manage fields

1. Go to/admin/structure/types/manage/article/fields
2. In the Operations column, click the Edit button of field_tags
3. Check «Enable AI Automator»
4. In Choose AI Automator Type, select LLM: Taxonomy
5. In AI Automator Settings, use the following settings
   - Automator Input Mode: Base Mode
   - Automator Base Field: Body
   - Automator Prompt: Please provide a comma separated list of 3 keywords that best encapsulate {{ context }}.
   - Advanced Settings:
      - Automator Worker: Field Widget
      - AI Provider: select your provider
6. Save settings

Step #2: Manage form display

1. Go to /admin/structure/types/manage/article/form-display
2. Open up field_tags settings
3. In the Field Widget Actions field-set
   - Add New Action: Automator Taxonomy
   - Click Add action
   - Generate Tags (Automator Taxonomy)
      - Check Enable Automators
      - Button label: «Generate tags»
      - Update
   - Save

Now when you edit an article, you should now see the «Generate tags» action button under the Tags field.

## Field Widget Action Examples

For detailed, step-by-step examples of configuring specific Field Widget Actions with AI Automators, see the [AI Automators Examples](../ai_automators/examples/index.md) section:

- [FAQ Field + `faqfield_default` widget](../ai_automators/examples/faq-field.md) - Generate FAQ entries from content using the `LLM: FAQ Field` automator
- [Chart Field + `chart_config_default` widget](../ai_automators/examples/chart-field.md) - Generate chart data from content using the `LLM: Chart From Text` automator
- [Text to Audio Media](../ai_automators/examples/text_to_audio_media_automator.md) - Generate audio from content using the `LLM: Media Audio Generation` automator
- [Text to Image Field](../ai_automators/examples/text_to_image_automator.md) - Generate image from content using the `LLM: Image Generation` automator
- [Text to Image Media Field](../ai_automators/examples/text_to_image_media_automator.md) - Generate image as Media entity using the `LLM: Media Image Generation` automator
- [Metatag Automator](../ai_automators/examples/metatag_automator.md) - Generate metatags from content using the `LLM: Metatag` automator
- [Text to Address Field](../ai_automators/examples/address_automator.md) - Generate address from content using the `LLM: Address` automator
