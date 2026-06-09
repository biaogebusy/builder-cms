# LLM: Telephone

## Field it applies to

- **Field type:** `telephone`
- **Potential target:** *none*

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmTelephone.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmTelephone.php?ref_type=heads)

## Description

The **LLM: Telephone** rule helps extract telephone numbers from provided text context using an AI chat model.
It ensures numbers are formatted with a plus sign and country code, and outputs values as RFC8259-compliant JSON for structured storage.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure OpenAI).
- Input text must contain or reference phone numbers for extraction.

## Form fields required

- No additional form fields beyond standard automator configuration.

## Example use cases

1. Extract customer service phone numbers from imported text content.
2. Parse phone numbers from user-submitted descriptions.
3. Auto-fill contact fields in CRM or directory entries.
4. Identify and store helpline numbers mentioned in articles.
5. Collect callback numbers from inquiry submissions.
6. Extract event hotline numbers from event descriptions.
7. Parse support numbers from documentation or guides.
8. Auto-detect phone numbers in imported marketing materials.

---

*This documentation was AI-generated.*
