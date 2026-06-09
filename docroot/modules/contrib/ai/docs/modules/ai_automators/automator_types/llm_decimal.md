# LLM: Decimal

## Field it applies to

- **Field type:** `decimal`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmDecimal.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmDecimal.php?ref_type=heads)

## Description

**LLM: Decimal** generates and stores decimal values for decimal fields using AI.
It ensures values are numeric and within configured min/max limits when applicable.

## Requirements

- Field must be of type `decimal`.

## Configuration options

There are no specific configuration options beyond the standard automator settings.

## Behavior

- Produces numeric values from provided context text.
- Validates the values to ensure:
  - They are numeric.
  - They meet min/max constraints defined on the field (if any).
- Stores the values directly in the decimal field.

## Example use cases

- Automatically generate price estimates from descriptive text.
- Populate rating fields or similar decimal-based data.

## Notes

- The automator does not expose AI prompt output to the end user.
- Values are strictly validated before being stored.
- Min/max tokens are available for prompt templates if needed.

---

*This documentation was AI-generated.*
