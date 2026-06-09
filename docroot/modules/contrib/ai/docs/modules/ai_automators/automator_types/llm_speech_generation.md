# LLM: Audio Generation

## Field it applies to

- **Field type:** `file`
- **Potential target:** `file` (audio file, e.g. MP3)

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmSpeechGeneration.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmSpeechGeneration.php?ref_type=heads)

## Description

The **LLM: Audio Generation** rule converts provided text into speech using a language model's text-to-speech capability.
It generates audio files (e.g. MP3) and attaches them to a file field.

## Requirements

- A configured AI text-to-speech provider (e.g., Elevenlabs, OpenAI Whisper, Azure Speech).

## Form fields required

- `Prompt` or `Token` (depending on configuration).
- AI model selection.

## Example use cases

1. Automatically generate narration for articles or blog posts.
2. Produce audio versions of product descriptions.
3. Generate spoken versions of form submissions (e.g. feedback or contact form).
4. Create audio for accessibility purposes (screen reader support).
5. Produce audio messages or announcements.
6. Convert summaries into audio format for podcast intros.
7. Provide voice-over for generated video clips.
8. Create audio prompts or instructions for user onboarding.

---

*This documentation was AI-generated.*
