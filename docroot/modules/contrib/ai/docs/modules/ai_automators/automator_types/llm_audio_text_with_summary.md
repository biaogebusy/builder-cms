# LLM: Audio to Text

## Field it applies to

- **Field type:** `text_with_summary`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmAudioToTextWithSummary.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmAudioToTextWithSummary.php?ref_type=heads)

## Description

**LLM: Audio to Text** provides AI-driven transcription of audio files into text.
It supports common audio formats and automatically stores transcribed text into `text_with_summary` fields.

## Requirements

- Field must be of type `text_with_summary`.
- A related field should contain audio files (e.g., `audio/mpeg`, `audio/aac`, `audio/wav`).

## Configuration options

- **Base field selection:** Select the field holding audio file references.
- **AI model selection:** Choose which AI model to use for speech-to-text conversion.

## Behavior

- Processes audio files from the specified base field.
- Sends audio content to the AI provider for transcription.
- Stores the returned text directly in the target field.
- Supports basic validation to ensure the output is a string.

## Example use cases

- Transcribe meeting recordings into text summaries.
- Generate text from podcast audio for SEO purposes.
- Convert lecture recordings into readable notes.
- Provide accessibility transcripts for video/audio content.
- Transform customer support call recordings into documentation.
- Capture voice memos and store them as structured text.
- Archive radio show dialogues as searchable text.
- Automate text extraction from voicemail attachments.
- Generate transcripts from webinar recordings.
- Produce interview transcripts for publication.

## Notes

- The AI-generated text is directly stored without exposing raw outputs to end users.
- Extend validation if additional checks (e.g., minimum length) are desired.
- The plugin skips files that are not valid audio MIME types.

---

*This documentation was AI-generated.*
