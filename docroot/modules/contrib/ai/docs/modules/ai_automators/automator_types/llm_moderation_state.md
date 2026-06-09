# LLM: Moderation State

## Field it applies to

- **Field type:** `string`
- **Target field name:** `moderation_state` (required)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmModerationState.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmModerationState.php?ref_type=heads)

## Description

The **LLM: Moderation State** rule analyzes content using a large language model (LLM) and automatically determines the appropriate moderation state for an entity.
It supports both advanced and simplified evaluation modes, depending on the AI model selected.

## Requirements

- Content Moderation module enabled (provides moderation states).
- A configured AI provider (e.g., OpenAI).
- The field must be `moderation_state`.

## Form configuration options

- **Trigger on these states** — Select the moderation states that should trigger this automator to run.
- **Lookup for these states** — Choose the moderation states that should be applied if matched.
- **Use small model** — Enable this to use simpler models where output is matched via keyword search rather than structured data.
- **Store explanation** — Optionally save the AI’s reasoning in a selected `string_long` field.

## Example use cases

- Automatically change moderation state to "Needs Review" if specific conditions are met.
- Propose moderation changes during editorial workflows using AI assistance.
- Record explanations for moderation decisions for transparency.

## Notes

- This rule only applies when the field name is exactly `moderation_state`.
- You must configure at least one **trigger state** and one **lookup state**.
- Works with both full JSON-capable models and small models using text matching.

---

*This documentation was AI-generated.*
