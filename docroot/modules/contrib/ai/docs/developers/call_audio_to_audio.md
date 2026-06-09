

## Audio-To-Audio

Audio-To-Audio calls are calls that can take an audio file and convert it to another audio file. For instance, Auphonic is a provider that can fix audio levels and background noise on audio using AI.

### Example normalized Audio-To-Audio call

The following is an example of sending the file hello_there.mp3 into Auphonic's model/preset default and getting the audio file back.

```php
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\AudioToAudio\AudioToAudioInput;

$audio_binary = file_get_contents('hello_there.mp3');
$normalized_file = new AudioFile($audio_binary, 'audio/mp3', 'hello_there.mp3');
$input = new AudioToAudioInput($normalized_file);
/** @var \Drupal\ai\OperationType\AudioToAudio\AudioToAudioOutput $return_audio */
$return_audio =  \Drupal::service('ai.provider')->createInstance('auphonic')->audioToAudio($input, 'default', ['my-custom-call']);
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

### Audio-To-Audio Interfaces & Models

The following files define the methods available when doing an Audio-To-Audio call as well as the input and output.

* [AudioToAudioInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/AudioToAudio/AudioToAudioInterface.php?ref_type=heads)
* [AudioToAudioInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/AudioToAudio/AudioToAudioInput.php?ref_type=heads)
* [AudioToAudioOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/AudioToAudio/AudioToAudioOutput.php?ref_type=heads)

### Audio-To-Audio Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Audio-To-Audio Generation Explorer` under `/admin/config/ai/explorers/ai-audio-to-audio` to test out different calls and see the code that you need for it.
