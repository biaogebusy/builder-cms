

## Moderation

Moderation calls are calls that can give feedback if a prompt is offensive or in certain provider cases against the terms and conditions to send in to the provider.

### Example normalized Moderation call

The following is an example of sending the text "I want to kill all of them" into OpenAI using the text-moderation-latest model. The output is a ModerationResponse object.

```php
use Drupal\ai\OperationType\Moderation\ModerationInput;

$input = new ModerationInput('I want to kill all of them');
/** @var \Drupal\ai\OperationType\Moderation\ModerationOutput $moderation_object */
$moderation_object =  \Drupal::service('ai.provider')->createInstance('openai')->Moderation($input, 'text-moderation-latest', ['my-custom-call']);

/** @var \Drupal\ai\OperationType\Moderation\ModerationResponse $moderation */
$moderation = $moderation_object->getNormalized();

// If moderation is flagged, we wrote something bad.
if ($moderation->isFlagged()) {
  print_r($moderation->getInformation());
  // This will output reasoning for being flagged.
  throw new \Exception('Exit here, so we will not get banned');
}
```

### Moderation Interfaces & Models

The following files define the methods available when doing a moderation call as well as the input and output.

* [ModerationInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Moderation/ModerationInterface.php?ref_type=heads)
* [ModerationInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Moderation/ModerationInput.php?ref_type=heads)
* [ModerationOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Moderation/ModerationOutput.php?ref_type=heads)

### Moderation Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Moderation Generation Explorer` under `/admin/config/ai/explorers/ai-moderation` to test out different calls and see the code that you need for it.
