# Text-to-Audio (Media) Automator

The Text-to-Audio Automator allows you to automatically generate audio files (Speech) from text content and save them as Media entities. This is useful for creating audio versions of articles, podcasts from text, or accessibility features.

## Prerequisites

1.  **Media Reference Field**: You must have a Media Entity Reference field on your Content Type (e.g., `field_audio_media`).
2.  **Audio Media Type**: You must have a Media Type configured for Audio (e.g., standard `Audio` media type in Drupal).
3.  **AI Provider**: You must have an AI Provider configured that supports **Speech/Audio Generation** (e.g., OpenAI with TTS models).

## Configuration

To configure the Text-to-Audio Automator:

1.  Navigate to the **Manage Fields** tab of your entity type (e.g., `Structure > Content types > Article > Manage fields`).
2.  Click **Edit** on the Media field you want to automate.
3.  Scroll down to the **AI Automator Settings** section.
4.  Check **Enable AI Automator**.
5.  In the **Choose AI Automator Type** dropdown, select **LLM: Media Audio Generation**.

### Media Configuration

*   **Media Type**: **Crucial Step**. You must select the specific Media Type you want to create (e.g., "Audio"). The automator will create a new Media entity of this type and attach the generated audio file to it.

### Base Mode Configuration

**Base Mode** uses one existing text field as the input for speech generation.

1.  **Automator Input Mode**: Select **Base Mode**.
2.  **Automator Base Field**: Choose the text field that contains the text to be spoken (e.g., "Body" or "Title").
3.  **Automator Prompt**: Enter instructions or the context token.
    *   **Standard Usage**: Use `{{ context }}` to simply read the text from the base field.
    *   **Refinement**: You can add instructions like "Read this text in a slow, calm voice: {{ context }}".

**Example Prompt:**
```text
{{ context }}
```

### Advanced Mode (Token) Configuration

**Advanced Mode** allows you to construct the speech input using any Drupal tokens.

1.  **Automator Input Mode**: Select **Advanced Mode (Token)**.
2.  **Automator Prompt (Token)**: Enter the text to be spoken using tokens.

**Example Prompt:**
```text
Title: [node:title]. Summary: [node:summary].
```

## How It Works

1.  When you **Create** or **Update** an entity, the Automator checks if the media field is empty.
2.  If empty, it sends the text (context) to the AI Speech provider.
3.  The AI generates an audio file (MP3/WAV/etc.).
4.  The Automator creates a new Media entity, uploads the audio file to it, and attaches this Media entity to your content.

### "Edit when changed"

If you check **Edit when changed**, the Automator will re-generate the audio if the content of the *Base Field* changes. **Warning**: This will create new Media entities and overwrite the reference.

## Test the functionality

1.  Go to **Content** > **Add content** > **Article** (or the entity type you configured).
2.  In the **Body** field (or your chosen *Base Field*), paste the text you want converted to speech. Use the example below:
    > Welcome to our new podcast episode. Today we are discussing the future of AI in Drupal.
3.  Scroll to the **Audio Media** field.
4.  *Option A (Manual Generation):* If you see the **AI Automator** button (magic wand/robot icon), click it.
    *   Wait for the Ajax loader to finish.
    *   Check the widget. A new Audio media item should be selected/attached.
5.  *Option B (Automatic Generation):* If no button is present, simply click **Save**.
    *   The page will reload (or redirect).
    *   Check the saved content. The Audio player should be visible and playable.
