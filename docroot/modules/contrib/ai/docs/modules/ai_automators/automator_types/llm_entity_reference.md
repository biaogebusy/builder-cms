# LLM: Entity Reference

## Field it applies to

- **Field type:** `entity_reference`
- **Target:** Any entity type except `media`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmEntityReference.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmEntityReference.php?ref_type=heads)

## Description

**LLM: Entity Reference** uses AI to create referenced entities dynamically based on the context text of the parent entity.
It auto-fills configured fields for the referenced entity (e.g., title, description) and establishes the reference.

## Requirements

- Field must be of type `entity_reference` and target **any entity type except media**.
- At least one target entity field must be enabled for generation.

## Configuration options

When configuring the automator:

✅ **Bundle selection:** Choose the target entity bundle for reference creation.
✅ **Field setup:** For each eligible field (e.g., string, text), specify:

- Whether to generate it.
- A sub-prompt describing how AI should generate the field.

## Behavior

- Generates one or more referenced entities based on the context.
- Creates the entities and sets the reference field accordingly.
- Supports text formatting for fields like `text`, `text_long`, and `text_with_summary`.

## Example use cases

- Automatically create tagged content or related articles from a summary text.
- Generate related FAQ items or product specs as referenced nodes.

## Notes

- The automator does not expose prompt output or explanations to the user.
- It produces valid JSON for AI processing, enforcing strict schema compliance.
- No referenced entities are created unless at least one target field is enabled for generation.

---

*This documentation was AI-generated.*
