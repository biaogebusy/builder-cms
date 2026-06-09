

## Speech-To-Text

Speech-To-Text calls are calls that can take an audio file and listen to it and generate a textual prompt of it. Famous providers are Deepgram or OpenAI. Sometimes called transcribing.

### Example normalized Speech-To-Text call

The following is an example of sending the file hello_there.mp3 into Deepgram's model nova-2 and getting the text back.

```php
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;

$audio_binary = file_get_contents('hello_there.mp3');
$normalized_file = new AudioFile($audio_binary, 'audio/mp3', 'hello_there.mp3');
$input = new SpeechToTextInput($normalized_file);
/** @var \Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput $return_text */
$return_objet =  \Drupal::service('ai.provider')->createInstance('deepgram')->speechToText($input, 'nova-2', ['my-custom-call']);
echo $return_object->getNormalized();
// Will output "Hello there"
```

### Speech-To-Text Interfaces & Models

The following files define the methods available when doing a speech-to-text call as well as the input and output.

* [SpeechToTextInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/SpeechToText/SpeechToTextInterface.php?ref_type=heads)
* [SpeechToTextInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/SpeechToText/SpeechToTextInput.php?ref_type=heads)
* [SpeechToTextOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/SpeechToText/SpeechToTextOutput.php?ref_type=heads)

### Speech-To-Text Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Speech-To-Text Generation Explorer` under `/admin/config/ai/explorers/ai-speech-to-text` to test out different calls and see the code that you need for it.
