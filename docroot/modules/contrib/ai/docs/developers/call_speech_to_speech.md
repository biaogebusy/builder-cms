

## Speech-To-Speech

Speech-To-Speech calls are calls that can take an audio file with speech and convert it to another audio file with the same speech, but other voices.

### Example normalized Speech-To-Speech call

The following is an example of sending the file hello_there.mp3 into Elevenlabs with the martin voice and getting the audio file back.

```php
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput;

$audio_binary = file_get_contents('hello_there.mp3');
$normalized_file = new AudioFile($audio_binary, 'audio/mp3', 'hello_there.mp3');
$input = new SpeechToSpeechInput($normalized_file);
/** @var \Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechOutput $return_audio */
$return_audio =  \Drupal::service('ai.provider')->createInstance('elevenlabs')->speechToSpeech($input, 'martin', ['my-custom-call']);
/** @var \Drupal\ai\OperationType\GenericType\AudioFile */
$audio = $return_audio->getNormalized();

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

### Speech-To-Speech Interfaces & Models

The following files define the methods available when doing a Speech-To-Speech call as well as the input and output.

* [SpeechToSpeechInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/SpeechToSpeech/SpeechToSpeechInterface.php?ref_type=heads)
* [SpeechToSpeechInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/SpeechToSpeech/SpeechToSpeechInput.php?ref_type=heads)
* [SpeechToSpeechOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/SpeechToSpeech/SpeechToSpeechOutput.php?ref_type=heads)

### Speech-To-Speech Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Speech-To-Speech Generation Explorer` under `/admin/config/ai/explorers/ai-speech-to-speech` to test out different calls and see the code that you need for it.
