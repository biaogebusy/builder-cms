# LLM: Audio to Text

## Field it applies to

- **Field type:** `text_long`

## File location

[ai_automators/src/Plugin/AiAutomatorType/LlmAudioToTextLong.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/modules/ai_automators/src/Plugin/AiAutomatorType/LlmAudioToTextLong.php?ref_type=heads)

## Description

**LLM: Audio to Text** enables AI-based transcription of audio files into plain text.
It stores the transcribed text in `text_long` fields, making it ideal for longer content like transcripts or notes.

## Requirements

- Field must be of type `text_long`.
- The entity must have a field referencing audio files (`audio/mpeg`, `audio/aac`, `audio/wav`).

## Configuration options

- **Base field selection:** Choose the field containing audio file references.
- **AI model selection:** Select the AI model used for transcription.

## Behavior

- Processes audio files linked in the configured base field.
- Converts speech to text using an AI provider.
- Stores the resulting text directly in the target field.
- Basic validation ensures the result is a string.

## Example use cases

- Transcribe podcast episodes into text for blog posts.
- Convert customer support calls into detailed text records.
- Generate text content from voice-over files.
- Provide searchable transcripts for video content.
- Produce lecture notes from classroom recordings.
- Document interviews in text form.
- Create written records of conference presentations.
- Automate voice memo transcription.
- Build text archives of radio broadcasts.
- Enable accessibility by generating text from audio clips.

## Notes

- Raw AI outputs are stored cleanly; no intermediate prompt output is shown to users.
- The system ensures only valid audio MIME types are processed.
- Further validation can be added as needed to suit your use case.

---

*This documentation was AI-generated.*
