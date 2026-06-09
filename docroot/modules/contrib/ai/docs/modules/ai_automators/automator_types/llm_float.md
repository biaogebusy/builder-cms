# LLM: Float

## Field it applies to

- **Field type:** `float`
- **Target:** *(none)*

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmFloat.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmFloat.php?ref_type=heads)

## Description

**LLM: Float** generates float (decimal) values using AI based on the provided context.
It is useful for deriving numeric data from descriptive text.

## Requirements

- The field must be of type `float`.
- The AI-generated value must be numeric and within any configured min/max constraints.

## Behavior

- Generates float numbers using AI from context text.
- Validates the value to ensure:
  - It is numeric.
  - It satisfies any configured minimum or maximum constraints on the field.
- Stores the generated float value in the field.

## Example use cases

- Derive numerical ratings from descriptive feedback.
- Calculate scores or measurements from AI interpretation of context.

## Notes

- The automator enforces compliance with field validation rules.
- No explanations or prompt output are exposed to users.
- The stored value is directly set on the field.

---

*This documentation was AI-generated.*
