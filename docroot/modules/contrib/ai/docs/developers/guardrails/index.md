# Guardrails Overview

Guardrails let you add safety and validation checks on AI inputs and outputs. They run automatically before and after AI generation, and can pass, stop, or rewrite content based on your rules.

The system is built on Drupal's plugin architecture, so you can create custom guardrail plugins, group them into sets, and configure stop thresholds through the admin UI.

---

## Built-in Guardrail Plugins

The AI module includes the following built-in guardrails:

*   **[Input Length Limit](input_length_limit.md)**: Blocks input that exceeds a configurable character or token count limit.
*   **[Regexp Guardrail](regexp_guardrail.md)**: Deterministic check that matches the last chat message against a configurable regular expression pattern.
*   **[Restrict to Topic](restrict_to_topic.md)**: Non-deterministic check using an LLM to verify whether the input matches allowed or disallowed topics.

For developers looking to implement their own checks, see the guide on **[Writing a Custom Guardrail Plugin](custom_guardrail.md)**.

---

## Architecture Overview

The Guardrails system has these main components:

- **Guardrail plugins** implement `AiGuardrailInterface` and contain the actual validation logic.
- **Guardrail entities** (`AiGuardrail` config entities) wrap a plugin with admin-configurable settings.
- **Guardrail sets** (`AiGuardrailSet` config entities) group guardrails into pre-generation and post-generation lists with a stop threshold.
- **`GuardrailsEventSubscriber`** listens to `PreGenerateResponseEvent` and `PostGenerateResponseEvent` and runs the configured guardrails.
- **`AiGuardrailHelper`** provides a convenience method to attach a guardrail set to an AI input.

### How Guardrails Execute

```
Input created
    │
    ▼
AiGuardrailHelper::applyGuardrailSetToChatInput()
    │  attaches a guardrail set to the input
    ▼
PreGenerateResponseEvent fires
    │
    ▼
GuardrailsEventSubscriber::applyPreGenerateGuardrails()
    │  runs each pre-generate guardrail plugin
    │  aggregates StopResult scores
    │  if score >= stop_threshold → forces output, skips AI call
    │  if RewriteInputResult → rewrites the last message
    ▼
AI provider generates response
    │
    ▼
PostGenerateResponseEvent fires
    │
    ▼
GuardrailsEventSubscriber::applyPostGenerateGuardrails()
    │  runs each post-generate guardrail plugin
    │  if score >= stop_threshold → replaces output
    │  if RewriteOutputResult → rewrites the response
    ▼
Final output returned
```

---

## Result Types

Every guardrail plugin returns a `GuardrailResultInterface` from its `processInput()` and `processOutput()` methods. There are four result types:

| Result | `stop()` | Effect |
|--------|----------|--------|
| `PassResult` | `false` | Input/output passes without changes. |
| `StopResult` | `true` | Signals the input/output should be blocked. Carries a `score` (default `1.0`) that is aggregated across guardrails. |
| `RewriteInputResult` | `false` | Replaces the last chat message text with the result's message (pre-generation only). |
| `RewriteOutputResult` | `false` | Replaces the AI response text with the result's message (post-generation only). |

All result types extend `AbstractResult` and take three constructor arguments:

```php
new StopResult(
  message: 'This content violates the regexp pattern.',
  guardrail: $this,          // The guardrail plugin instance.
  context: [],               // Optional context array.
  score: 1.0,                // StopResult only: the severity score.
);
```

---

## Score Aggregation and Stop Threshold

Each `AiGuardrailSet` has a **stop threshold** (a float). When guardrails in a set run, the subscriber aggregates the `score` values from all `StopResult` instances. If the aggregated score reaches or exceeds the stop threshold, execution stops and the AI call is either skipped (pre-generation) or the output is replaced (post-generation).

This lets you combine multiple guardrails where each one contributes a partial score. For example, three guardrails each returning a `StopResult` with score `0.4` would aggregate to `1.2` — exceeding a threshold of `1.0`.

---

## Guardrail Modes

Guardrails can run at three points in the AI generation lifecycle, defined by `AiGuardrailModeEnum`:

| Mode | Enum Value | When |
|------|-----------|------|
| Pre-generate | `pre` | Before the AI provider call. Can stop or rewrite the input. |
| Post-generate | `post` | After the AI provider returns. Can stop or rewrite the output. |
| During-generate | `during` | Mid-stream evaluation via `StreamableGuardrailInterface`. Registered on the post-generate list; runs inside the stream iterator as chunks arrive. |

---

## Applying Guardrails to AI Input

Use `AiGuardrailHelper::applyGuardrailSetToChatInput()` to attach a guardrail set to any input before making an AI call:

```php
// In a service or controller with dependency injection:
$guardrail_helper = \Drupal::service('ai.guardrail_helper');

$input = new ChatInput([
  new ChatMessage('user', 'Tell me about Drupal.'),
]);

// Attach the guardrail set by its machine name.
$input = $guardrail_helper->applyGuardrailSetToChatInput('my_guardrail_set', $input);

// Make the AI call as usual. Guardrails run automatically via events.
$response = $provider->chat($input, $model_id, ['my_module']);
```

The method clones the input and calls `addGuardrailSet()` on it. When the AI provider fires its pre/post-generation events, the `GuardrailsEventSubscriber` iterates every attached set and runs its configured guardrails.

### Attaching Multiple Guardrail Sets

An input may carry more than one guardrail set — e.g., one attached by the caller and one by middleware. Call `applyGuardrailSetToChatInput()` repeatedly, or use the input API directly:

```php
$input->addGuardrailSet($set_a);
$input->addGuardrailSet($set_b);
// Or replace the whole list:
$input->setGuardrailSets([$set_a, $set_b]);
```

Sets are keyed by ID; re-adding the same ID via `addGuardrailSet()` replaces that entry in place. `setGuardrailSets()` replaces the entire list in one call and accepts either a list or a keyed map — keys are ignored and re-derived from each set's ID.

Each set's `stop_threshold` is evaluated independently — scores are not aggregated across sets. If any set crosses its own threshold, processing of remaining sets is short-circuited and the stop message is returned as the output.

The legacy single-set methods `setGuardrailSet()` / `getGuardrailSet()` are deprecated — use `addGuardrailSet()` / `getGuardrailSets()` instead.

### Global Guardrails

Site administrators can configure one or more guardrail sets to be applied to **every** AI request, regardless of whether the caller opted in. Configure them at *Configuration → AI → AI Guardrails → Global guardrails* (`/admin/config/ai/guardrails/global`). The selected IDs are stored under `ai.settings:global_guardrails`.

Under the hood, `GlobalGuardrailsEventSubscriber` listens on `PreGenerateResponseEvent` at priority `100` (before the regular `GuardrailsEventSubscriber`). It **prepends** each configured global set to the input via `setGuardrailSets()`, so global safety/PII checks always evaluate the original prompt and the raw provider output before any caller-attached guardrail can rewrite them.

Important consequences of that ordering:

- A global set that crosses its `stop_threshold` short-circuits the pipeline before any caller-attached set runs. Global stops are non-negotiable.
- If a caller and a site-wide config both reference the same guardrail set ID, the global wins and the set sits at the front — the caller's ordering intent is intentionally overridden by the site-wide configuration.

If you build your own pre-request subscriber and need to attach a set from code, subscribe at any priority `> 0` and call `$event->getInput()->addGuardrailSet($set)` (append) or `$event->getInput()->setGuardrailSets($yourSets + $event->getInput()->getGuardrailSets())` (prepend, same pattern as the global subscriber).

---

## Managing Guardrails in the UI

Guardrails are managed at **Administration > Configuration > AI > Guardrails** (`/admin/config/ai/guardrails`).

- **Guardrails** tab: Create and configure individual guardrail entities, each wrapping a guardrail plugin with specific settings.
- **Guardrail Sets** tab (`/admin/config/ai/guardrails/guardrail-sets`): Create sets that group guardrails into pre-generation and post-generation lists, and set the stop threshold.

### Required Permissions

- `administer guardrails` for managing individual guardrail entities.
- `administer guardrail sets` for managing guardrail sets.
