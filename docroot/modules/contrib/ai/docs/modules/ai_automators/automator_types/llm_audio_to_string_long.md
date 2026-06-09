# LLM: Audio to Text

## Field it applies to

- **Field type:** `string_long`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmAudioToStringLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmAudioToStringLong.php?ref_type=heads)

## Description

**LLM: Audio to Text** allows AI-driven transcription of audio files into plain text.
The transcribed text is stored in `string_long` fields, suitable for medium-length content.

## Requirements

- Field must be of type `string_long`.
- The entity must have a field referencing audio files (`audio/mpeg`, `audio/aac`, `audio/wav`).

## Configuration options

- **Base field selection:** Choose the audio file reference field.
- **AI model selection:** Select the AI model to use for transcription.

## Behavior

- Processes audio files from the configured base field.
- Transcribes speech to text using an AI provider.
- Stores the text in the target `string_long` field.
- Validates that the output is a string.

## Example use cases

- Transcribe short audio memos into text.
- Convert voicemail recordings into readable format.
- Document short interviews or testimonials.
- Generate text for short video captions.
- Turn voice notes into structured text entries.
- Create summaries from brief meeting audio clips.
- Capture spoken instructions as written text.
- Produce transcripts of social media audio clips.
- Archive radio jingles or promos as text.
- Enable search indexing of short audio snippets.

## Notes

- Raw AI output is stored directly in the field without exposing intermediate prompt data to users.
- Only valid audio formats are accepted for processing.
- Further validation or formatting logic can be added if required.

---

*This documentation was AI-generated.*
