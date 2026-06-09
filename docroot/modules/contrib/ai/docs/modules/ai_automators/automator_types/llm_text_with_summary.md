# LLM: Text

## Field it applies to

- **Field type:** `text_with_summary`
- **Potential target:** *none*

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmTextWithSummary.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmTextWithSummary.php?ref_type=heads)

## Description

The **LLM: Text** rule generates text content for fields that support both body and summary.
It leverages an AI chat model to produce structured text in RFC8259-compliant JSON format.
The rule allows configuration of text format and joiner behavior when combining outputs from multiple prompts.

## Requirements

- A configured AI chat provider (e.g., OpenAI, Azure OpenAI).
- The cron job should specify a text format if running as an anonymous user.

## Form fields required

- **Use text format**
  Selects which text format to use when storing generated text.
  Important for cron jobs where the anonymous user’s permissions apply.

- **Joiner** (via advanced options)
  Defines how multiple generated values should be combined (e.g., line break, space, custom string).

## Example use cases

1. Automatically summarize long articles with both full text and summary.
2. Generate product descriptions for e-commerce items.
3. Compose blog posts from outline data.
4. Create press release content.
5. Assist with writing educational materials.
6. Auto-generate news snippets for newsletters.
7. Produce SEO-optimized content blocks.
8. Draft text for social media sharing.

---

*This documentation was AI-generated.*
