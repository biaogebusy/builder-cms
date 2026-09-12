# Restrict to Topic Guardrail

The **Restrict to Topic** guardrail (`restrict_to_topic`) is a non-deterministic guardrail that uses an AI provider (LLM) to classify whether the user's message matches configured lists of allowed or disallowed topics. It implements both `NonDeterministicGuardrailInterface` and `NonStreamableGuardrailInterface`.

## When to Use

Use this guardrail to:
- Confine a chatbot to specific subject areas (e.g., support for a particular product only).
- Proactively block off-topic queries or attempts to distract the model (prompt injection, roleplay, etc.).
- Blocklist specific sensitive or forbidden subjects.

## Configurable Fields

| Field | Key | Type | Default | Description |
|-------|-----|------|---------|-------------|
| **Valid Topics** | `valid_topics` | Textarea | *None* | List of allowed topics (one per line). If any are configured, the message must relate to at least one of these topics to pass. |
| **Invalid Topics** | `invalid_topics` | Textarea | *None* | List of disallowed topics (one per line). If any match, the message is blocked. |
| **Message to send if invalid topics are present** | `invalid_topics_present_message` | Textarea | `The text contains invalid topics` | The violation message displayed when the input matches one of the disallowed topics. |
| **Message to send if no valid topics are found** | `valid_topics_missing_message` | Textarea | `The text does not contain any of the valid topics` | The violation message displayed when the input does not match any of the allowed topics (only evaluated if Valid Topics is not empty). |
| **AI Provider** | `llm_provider` | Select | *None* | The AI provider (e.g., OpenAI, Anthropic) used to classify the text's topics. |
| **AI Model** | `llm_model` | Select | *None* | The specific model used for the topic classification query. |

> [!NOTE]
> Under the hood, this guardrail issues a classification prompt to the selected LLM. If no provider is configured, it falls back to the site's default chat provider configured in the AI module settings.

## Example Configuration

Below is an example configuration restricting a chatbot to customer support topics while disallowing financial advice:

```yaml
id: restrict_to_topic
valid_topics: |
  shipping questions
  product returns
  billing issues
  general inquiries
invalid_topics: |
  financial advice
  investment strategies
  stock market tips
invalid_topics_present_message: "We cannot discuss financial advice or investments here."
valid_topics_missing_message: "Please ask a question related to shipping, returns, billing, or general store support."
llm_provider: openai
llm_model: gpt-4o-mini
```
