# Text-to-Image Automator

The Text-to-Image Automator allows you to automatically generate images for an Image field based on text content from other fields in your entity. This is useful for generating placeholder images, illustrative content for articles, or visual representations of data.

Distinction from the "Text to Image" *Action*: The Automator runs automatically on entity save (or via a "Generate" button if configured), whereas the Action is a manual button on the content edit form. This guide focuses on the **Automator**.

## Prerequisites

1.  **Image Field**: You must have an Image field on your Content Type, Taxonomy Term, or other entity.
2.  **AI Provider**: You must have an AI Provider configured that supports **Image Generation** (e.g., OpenAI with DALL-E, Stability AI, etc.).

## Configuration

To configure the Text-to-Image Automator:

1.  Navigate to the **Manage Fields** tab of your entity type (e.g., `Structure > Content types > Article > Manage fields`).
2.  Click **Edit** on the Image field you want to automate.
3.  Scroll down to the **AI Automator Settings** section.
4.  Check **Enable AI Automator**.
5.  In the **Choose AI Automator Type** dropdown, select **LLM: Image Generation**.

### Base Mode Configuration

**Base Mode** is the simplest way to generate an image. It uses one existing text field as the source of inspiration.

1.  **Automator Input Mode**: Select **Base Mode**.
2.  **Automator Base Field**: Choose the text field that will provide the context (e.g., "Body" or "Title").
3.  **Automator Prompt**: Enter instructions for the AI on how to generate the image.
    *   **Crucial Step**: You **must** include the `{{ context }}` placeholder in your prompt. This placeholder is replaced by the content of your selected *Automator Base Field*.

**Example Prompt:**
```text
A realistic photo representing the following scene: {{ context }}. High quality, 4k.
```

### Advanced Mode (Token) Configuration

**Advanced Mode** gives you full control using Drupal Tokens. This allows you to combine multiple fields or use system values to construct your prompt.

1.  **Automator Input Mode**: Select **Advanced Mode (Token)**.
2.  **Automator Prompt (Token)**: Enter your prompt using any available Drupal tokens.

**Example Prompt:**
```text
A digital illustration of [node:title] with the following details: [node:field_description]. Style: [node:field_style].
```

## How It Works

1.  When you **Create** or **Update** an entity, the Automator checks if the image field is empty.
2.  If empty, it initiates the AI request using your configured prompt and the current entity data.
3.  The AI generates an image, which is then downloaded and saved to your site's file system.
4.  The image is automatically attached to the field.

### "Edit when changed"

If you check **Edit when changed**, the Automator will re-generate the image if the content of the *Base Field* changes, even if the image field is already populated. Use this with caution as it will overwrite existing images.

## Test the functionality

1.  Go to **Content** > **Add content** > **Article** (or the entity type you configured).
2.  In the **Body** field (or your chosen *Base Field*), paste text that describes the desired image. Use the example below:
    > A calm morning by a lake with mountains in the background, realistic style (or whatever your prompt expects).
3.  Scroll to the **Image** field.
4.  Click the **AI Automator** button (magic wand or robot icon) next to the field.
    *   Wait for the Ajax loader to finish.
    *   Check the Image widget. You should see the generated image uploaded and selected.



