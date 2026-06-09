# LLM: Integer

## Field it applies to

- **Field type:** `integer`
- **Target:** N/A

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmInteger.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmInteger.php?ref_type=heads)

## Description

**LLM: Integer** uses AI to extract numeric values from contextual text and store them in `integer` fields. The values are automatically rounded to the nearest whole number before storage.

## Requirements

- The target field must be of type `integer`.
- The AI-generated value must be numeric and within the min/max constraints configured on the field.

## Behavior

- The automator generates prompts using tokens such as `{{ min }}`, `{{ max }}`, and `{{ context }}`.
- The AI output is rounded to a whole number using `round($value, 0)`.
- The output is validated to ensure it is numeric and within field constraints before being stored.

## Example use cases

- Extract numeric ratings, counts, or scores from text descriptions.
- Populate numeric data based on AI analysis of contextual content.

## Notes

- Ensure your prompt guides the AI to return plain numeric values.
- The automator will reject values that are not numeric or violate field min/max settings.
- No explanations or extra text are included in stored values.

---

*This documentation was AI-generated.*
