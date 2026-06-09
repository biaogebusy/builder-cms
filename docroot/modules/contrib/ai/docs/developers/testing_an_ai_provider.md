# Testing a new AI Provider

So, you have started developing a new AI Provider for the Drupal AI module. Great! Now it's time to test it to ensure everything works as expected. This guide will walk you through the steps to effectively test your AI Provider.

Please note that your provider might not support all operation types (chat, vision, tools, structured data, embeddings) and that is ok.

We will section it into different operation types, and subsections for each type of operation if it exists.

## Prerequisites for all tests

1. **Enable the AI Module**: Ensure that the Drupal AI module is enabled on your Drupal site. `drush pm:en ai`
2. **Configure the Provider**: Navigate to the AI Providers configuration page (`/admin/config/ai/providers`) and set up your new AI Provider with the necessary API keys and settings.
3. **Enable the AI API Explorer**: Make sure the AI API Explorer module is enabled to facilitate testing. `drush pm:en ai_api_explorer`

## Different Operation Types

### Chat Operations

For all of the tests, the following steps are true:

1. Navigate to the AI API Explorer for chat at `/admin/config/ai/explorers/chat_generator`. You need a provider that supports chat operations, otherwise you will get a 403 error.
2. Select your new AI Provider from the dropdown on the right side. If the models do not load, something is wrong with the provider's model fetching.

#### Basic Chat Test
1. On the left side under `message` add a prompt like `Can you tell me a joke?`.
2. It should now load and it should show the response from the AI provider in the middle section that says "Responses will appear here".
3. If it works, congrats! If not, check the logs for errors and debug as necessary.

#### Chat Provider Configuration Tests
1. On the right side, if your provider has specific configuration options, try changing them and see if they have the expected effect on the chat responses.
2. A good test is to use temperature settings to see if the responses become more creative or more focused based on the values you set.

#### System Prompt Test
1. On the left side, click on the details "System Prompt".
2. Enter a system prompt like `Always mention banana at least once.`
3. Now enter a user message like `Tell me about the weather today.`
4. The response must contain the word `banana` at least once. If not, check the logs for errors and debug as necessary.

#### Streaming Chat Test
1. On the left side, check the box for `Streamed`.
2. Enter a message like `Tell me a story about a brave knight. At least two paragraphs long.`
3. The response should appear incrementally as the AI provider streams the response. If not, check the logs for errors and debug as necessary.

*Note that you need to make sure that your server doesn't buffer the output, then streaming won't work as expected due to that. Please verify with the OpenAI or Ollama provider for instance.*

#### Vision Chat Test
1. On the left side, add an image in the file field. PNG should always work.
2. Enter a message like `Describe the image I have uploaded.`
3. Make sure to uncheck the `Streamed` box if it is checked.
4. The response should describe the image you uploaded. If not, check the logs for errors and debug as necessary.

#### Structured Data Chat Test

**Note**: If you fetched via Git or you are testing against a dev release, you need to build the JSON Schema Editor to be able to run this test. If you see a plain textarea, you can assume it was not built. You can find more information about building it here: [Why is the built editor not included?](./json-schema-editor.md#why-is-the-built-editor-not-included)

1. On the left side, click the box `Advanced` and then the box `JSON Schema/Structured Output`.
2. Enter the following structured output format into the `JSON Schema/Structured Output` field:

          {
            "schema": {
              "properties": {
                "name": {
                  "title": "Name",
                  "type": "string"
                },
                "authors": {
                  "items": {
                    "type": "string"
                  },
                  "title": "Authors",
                  "type": "array"
                }
              },
              "required": [
                "name",
                "authors"
              ],
              "title": "Book",
              "type": "object",
              "additionalProperties": false
            },
            "name": "book",
            "strict": true
          }

3. Now enter a message like `The book The Long Earth was written by Stephen Baxter and Terry Pratchett.`
4. The response should be:

          {
            "name": "The Long Earth",
            "authors": [
              "Stephen Baxter",
              "Terry Pratchett"
            ]
          }

5. If not, check the logs for errors and debug as necessary.

#### PDF Chat Test
1. On the left side, add a PDF in the file field.
2. Enter a message like `Describe the PDF I have uploaded.`
3. The response should describe the PDF you uploaded. If not, check the logs for errors and debug as necessary.

#### Tool Use Chat Test
1. In your settings.php add `$settings['extension_discovery_scan_tests'] = TRUE;` to enable the test tools.
2. Enable the `AI Test` module. You can do this via `drush pm:en ai_test`.
3. On the left side, click the box `Advanced` and then the box `Function Calling`.
4. Click the `Function Calling` select field.
5. Search or find the `Calculator (ai_test)` tool and select it.
6. Now enter a message like `What is 25 multiplied by 4? Use the Calculator tool to compute the answer.`
7. Check the `Execute Function Call` checkbox.
8. The response should be:

            #1 tool usage:
            Tool name calculator
            Arguments from LLM:
            - expression: "25 * 4"
            Executed value: 100

### Embeddings Operations

#### Text Embeddings Test

1. Navigate to the AI API Explorer for embeddings at `/admin/config/ai/explorers/embeddings_generator`. You need a provider that supports embeddings operations, otherwise you will get a 403 error.
2. Select your new AI Provider from the dropdown on the left side. If the models do not load, something is wrong with the provider's model fetching.
3. Enter a text like `The quick brown fox jumps over the lazy dog.`
4. It should now load and it should show the embedding vector from the AI provider in the right section that says "Responses will appear here". It will be a lot of numbers.
5. If it works, congrats! If not, check the logs for errors and debug as necessary.

#### Image Embeddings Test
1. Navigate to the AI API Explorer for embeddings at `/admin/config/ai/explorers/embeddings_generator`. You need a provider that supports embeddings operations, otherwise you will get a 403 error.
2. Select your new AI Provider from the dropdown on the left side. If the models do not load, something is wrong with the provider's model fetching.
3. Upload an image file. PNG should always work.
4. It should now load and it should show the embedding vector from the AI provider in the right section that says "Responses will appear here". It will be a lot of numbers.
5. If it works, congrats! If not, check the logs for errors and debug as necessary.

### Image Classification Operations

1. Navigate to the AI API Explorer for image classification at `/admin/config/ai/explorers/image_classification_generator`. You need a provider that supports image classification operations, otherwise you will get a 403 error.
2. Select your new AI Provider from the dropdown on the left side. If the models do not load, something is wrong with the provider's model fetching.
3. Upload an image file.
4. If needed set labels under `Labels` with new line separated labels.
5. It should now load and it should show the classification results from the AI provider in the right section that says "Responses will appear here".
6. If it works, congrats! If not, check the logs for errors and debug

### Moderation Operations

1. Navigate to the AI API Explorer for moderation at `/admin/config/ai/explorers/moderation_generator`. You need a provider that supports moderation operations, otherwise you will get a 403 error.
2. Select your new AI Provider from the dropdown on the left side. If the models do not load, something is wrong with the provider's model fetching.
3. Enter a text like `I want to harm someone.`
4. It should now load and it should show the moderation results from the AI provider in the right section that says "Responses will appear here". It should indicate that the text is flagged and some categories with scores.
5. If it works, congrats! If not, check the logs for errors and debug as necessary.

### Speech-to-Text Operations

1. Navigate to the AI API Explorer for speech-to-text at `/admin/config/ai/explorers/speech_to_text_generator`. You need a provider that supports speech-to-text operations, otherwise you will get a 403 error.
2. Select your new AI Provider from the dropdown on the left side. If the models do not load, something is wrong with the provider's model fetching.
3. Upload an audio file.
4. It should now load and it should show the transcribed text from the AI provider in the right section that says "Responses will appear here".
5. If it works, congrats! If not, check the logs for errors and debug as necessary.

### Text-to-Speech Operations

1. Navigate to the AI API Explorer for text-to-speech at `/admin/config/ai/explorers/text_to_speech_generator`. You need a provider that supports text-to-speech operations, otherwise you will get a 403 error.
2. Select your new AI Provider from the dropdown on the left side. If the models do not load, something is wrong with the provider's model fetching.
3. Enter a text like `Hello, this is a test of the text to speech functionality.`
4. It should now load and it should provide an audio playback of the text from the AI provider in the right section that says "Responses will appear here".
5. If it works, congrats! If not, check the logs for errors and debug as necessary.

### Text-to-Image Operations

1. Navigate to the AI API Explorer for text-to-image at `/admin/config/ai/explorers/text_to_image_generator`. You need a provider that supports text-to-image operations, otherwise you will get a 403 error.
2. Select your new AI Provider from the dropdown on the left side. If the models do not load, something is wrong with the provider's model fetching.
3. Enter a prompt like `A futuristic cityscape at sunset.`
4. It should now load and it should show the generated image from the AI provider in the right section that says "Responses will appear here".
5. If it works, congrats! If not, check the logs for errors and debug as necessary.
