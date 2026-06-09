

## Text-To-Speech

Text-To-Speech calls are calls that can generate streaming audio or audio files from a textual prompt. Famous providers are Elevenlabs and OpenAI.

### Example normalized Text-To-Speech call

The following is an example of sending the text "Hello, my name is Martin" into Elevenlabs using the martin voice model. The output is an array of AudioFile.

```php
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;

$input = new TextToSpeechInput('Hello, my name is Martin');
/** @var \Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput $return_audios */
$return_audios =  \Drupal::service('ai.provider')->createInstance('elevenlabs')->TextToSpeech($input, 'martin', ['my-custom-call']);
/** @var \Drupal\ai\OperationType\GenericType\AudioFile */
$audio = $return_audios[0];
// You can do many cool things now.

// Examples Possibility #1 - get binary from the audio.
$binaries = $audio->getAsBinary();
// Examples Possibility #2 - get as base 64 encoded string from the audio.
$base64 = $audio->getAsBase64EncodedString();
// Examples Possibility #3 - get as generated media from the audio.
$media = $audio->getAsMediaEntity("audio", "", "audio.mp3");
// Examples Possibility #4 - get as file entity from the audio.
$file = $audio->getAsFileEntity("public://", "audio.mp3");
```

### Text-To-Speech Interfaces & Models

The following files define the methods available when doing a text-to-speech call as well as the input and output.

* [TextToSpeechInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TextToSpeech/TextToSpeechInterface.php?ref_type=heads)
* [TextToSpeechInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TextToSpeech/TextToSpeechInput.php?ref_type=heads)
* [TextToSpeechOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TextToSpeech/TextToSpeechOutput.php?ref_type=heads)

### Text-To-Speech Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Text-To-Speech Generation Explorer` under `/admin/config/ai/explorers/ai-text-to-speech` to test out different calls and see the code that you need for it.
