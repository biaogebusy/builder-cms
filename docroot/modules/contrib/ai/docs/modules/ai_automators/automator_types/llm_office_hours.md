# LLM: Office Hours

## Field it applies to

- **Field type:** `office_hours`
- **Potential target:** (none)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmOfficeHours.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmOfficeHours.php?ref_type=heads)

## Description

The **LLM: Office Hours** rule is designed for fields capturing structured office hours data.
It extracts and normalizes office hours from free-text context using AI chat models.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure)

## Form fields required

- `Prompt` or `Token` input
- AI model selection

Only provide days when the office is open.

## Example use cases

1. Automatically extract office hours from website content or descriptions.
2. Normalize inconsistent time formats into a structured office hours field.
3. Populate office hours based on transcribed text or user input.

---

*This documentation was AI-generated.*
