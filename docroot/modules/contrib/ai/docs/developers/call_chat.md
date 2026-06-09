## Chat

Chat is probably the most used by far - this is the "ChatGPT call" or usually how you interact with an LLM. So it takes a message conversation back and forth and returns the message from the service.

### Example normalized Chat call

The following is an example of sending the message "Hello" into OpenAI using the gpt-4o model.

```php
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

$messages = new ChatInput([
  new ChatMessage('user', 'Hello!'),
]);
// Set a system message.
$messages->setSystemPrompt('You are a really rude assistant that will not greet people.');
$provider =  \Drupal::service('ai.provider')->createInstance('openai');
/** @var \Drupal\ai\OperationType\Chat\ChatOutput $response */
$response = $provider->chat($messages, 'gpt-4o', ['my-custom-call']);
/** @var \Drupal\ai\OperationType\Chat\ChatMessage $return_message */
$return_message = $response->getNormalized();
echo $return_message->getText();
// Returns something like "hmmmpf"
```

Note that if a model takes images, you can give that as the third parameter of the input.

### Chat Interfaces & Models

The following files define the methods available when doing a chat call as well as the input and output.

* [ChatInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Chat/ChatInterface.php?ref_type=heads)
* [ChatInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Chat/ChatInput.php?ref_type=heads)
* [ChatOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Chat/ChatOutput.php?ref_type=heads)

### Setting system messages.
There is an abstracted way to set system messages for the providers that allow for it, the method is called `setSystemPrompt` on the `ChatInput` and it just takes the system role you want to set. Note that different providers weight these instructions more or less, so in certain cases it might make more sense to use two user messages instead.

### Structured output

To request that the model returns a response in a specific JSON structure (e.g. for parsing or validation), use the **StructuredOutputSchema** DTO and pass it to `ChatInput::setChatStructuredJsonSchema()`. The schema defines `name`, optional `description`, `strict` (whether the provider must follow the schema strictly), and the schema content (the JSON Schema that describes the expected shape of the response). The array shape uses the key **`schema`** for the schema content (documented format used by most providers); **`json_schema`** is accepted on input for backwards compatibility.

**Using the DTO (recommended):**

```php
use Drupal\ai\Dto\StructuredOutputSchema;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

$schema = new StructuredOutputSchema(
  name: 'weather_response',
  description: 'Structured weather data',
  strict: TRUE,
  json_schema: [
    'properties' => [
      'temperature' => ['type' => 'number'],
      'location' => ['type' => 'string'],
    ],
  ],
);

$input = new ChatInput([new ChatMessage('user', 'What is the weather in Paris?')]);
$input->setChatStructuredJsonSchema($schema);

$response = $provider->chat($input, 'gpt-4o');
```

You can also create the schema from an array with `StructuredOutputSchema::fromArray([...])`, or pass a plain array directly to `setChatStructuredJsonSchema()` (keys: `name`, `description`, `strict`, and `schema` or `json_schema` for the schema content). The DTO validates the schema (e.g. `name` must be lowercase letters, numbers, underscores, hyphens; schema content must be a non-empty array). See `\Drupal\ai\Dto\StructuredOutputSchema` and `ChatInput::setChatStructuredJsonSchema()` in the codebase for full usage and validation details.

### Streaming vs Non-Streaming output.
There is a helper method when using the chat providers that makes it possible to stream the output, if the chat provider has the possibility to do so. This can be set via the method `$input->setStreamedOutput(TRUE);` on the ChatInput object. This will give you back an iterator or generator that you can do a foreach on and flush the output buffers each time. See the section in [Develop Third Party module](develop_third_party_module.md/#streaming-chat) about how to add checks for it.

### Chat Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Chat Generation Explorer` under `/admin/config/ai/explorers/chat-generation` to test out different calls and see the code that you need for it.

Here are some examples of different types of levels you might want to integrate the module at. In these examples the code is loaded as a static, for best practice use Dependency Injection.

### Running in Fibers
Some of the providers support running the requests in Fibers, meaning that you can run multiple requests in parallel. This is very useful when you have multiple calls that are not dependent on each other. There is an AiProviderCapability enum that you can check if the provider supports it.

The providers that support this will realize that they are running in a fiber and start a fiber for each request. See the tests/src/AiLlm/FiberTest.php for an example of how to do this.

### Getting rate limits from the provider
If your provider has rate limits, you can set them on the `ChatOutput` object returned by the `chat` method using `setRateLimits()`. This lets your provider return a `ChatProviderLimitsDto` object with values such as maximum requests, remaining requests, and reset time. Consumers can then read this data with `getRateLimits()` to manage and display provider rate limit status.
