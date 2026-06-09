# LLM: Text

## Field it applies to

- **Field type:** `text_long`
- **Potential target:** Any long text field on content entities

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmTextLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmTextLong.php?ref_type=heads)

## Description

The **LLM: Text** rule enables automatic generation of long text field content using a large language model (LLM).
It can compose complex text responses, join multiple values into a single output if configured, and stores generated content with the appropriate text format for display.
The output is structured as JSON and inserted cleanly into the field.

Unlike **LLM: Text (simple)**, this rule can generate multiple values, process them, and join them according to your configuration rather than taking the output directly as-is.

## Form fields required

- **Prompt**
  A text area or input field where the instruction or request to the AI is entered.

- **Use text format**
  Type: `select`
  Description: Select a specific text format for the generated content. This is required for cron jobs, since cron runs as an anonymous user.
  Default (if not saved): `None`

- **Joiner**
  Allows specification of how multiple generated values are combined into a single output.

## Example use cases

1. Generate product descriptions for e-commerce items.
2. Create summaries for news articles or press releases.
3. Draft answers for FAQ sections.
4. Compose introductory paragraphs for event pages.
5. Generate opening lines for blog posts.
6. Write standard policy or legal text snippets.
7. Produce user biography text for profiles.
8. Create step-by-step instructions for recipes.
9. Compose course or training session descriptions.
10. Draft long-form commentaries or reviews.

---

*This documentation was AI-generated.*
