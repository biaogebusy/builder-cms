# LLM: Boolean

## Field it applies to

- **Field type:** `boolean`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmBoolean.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmBoolean.php?ref_type=heads)

## Description

**LLM: Boolean** enables AI-powered extraction of boolean (true/false) values from unstructured text content.
It generates structured JSON output that is stored in boolean fields based on customizable prompts.

NOTE - this is not recommended to use, since it only produces values on creation - if a checkbox is not filled in and saved, that means the value is now false, not null. Thus the automator will not run anymore. Use list options instead.

## Requirements

- Field must be of type `boolean`.
- The entity should contain relevant context data for boolean decision making.

## Configuration options

- **Prompt customization:** Ability to define specific conditions for true/false evaluation using tokens like `{{ true }}` and `{{ false }}`.
- **Token replacements:** Support for dynamic values in prompts (e.g., TRUE, FALSE).

## Behavior

- Generates a prompt asking the AI to evaluate the context and respond with `TRUE` or `FALSE`.
- Normalizes and stores AI output as boolean values in the entity field.
- Validates that the result matches accepted boolean representations (e.g., TRUE, FALSE, 1, 0).

## Example use cases

- Determine if text mentions a specific brand (e.g., "Is Apple mentioned in the content?").
- Check if a document references a geographic location (e.g., "Is New York mentioned?").
- Verify if safety instructions are present in a product description.
- Identify if the text contains a privacy policy statement.
- Confirm if a review talks about customer service quality.
- Detect if a news article mentions climate change.
- Determine if a support ticket describes an urgent issue.
- Check if an event description includes a registration requirement.
- Verify if a blog post references a competitor.
- Identify if promotional text mentions a discount or offer.

## Notes

- The AI-generated boolean result is consumed internally; raw output is not exposed to end users.
- Extend `verifyValue` or `storeValues` for stricter validation or custom storage logic as needed.

---

*This documentation was AI-generated.*
