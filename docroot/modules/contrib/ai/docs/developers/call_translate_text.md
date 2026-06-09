

## Translate Text

Translate text calls are calls for translating text from one language to another.
[DeepL](https://www.drupal.org/project/ai_provider_deepl) is the one provider that
implements this call natively.

AI translate module, when enabled, allows using any provider that supports
[chat](call_chat.md) calls, for translation.

### Example normalized text translation call

Using default provider:

```php
      $providerConfig = $this->aiProviderManager->getDefaultProviderForOperationType('translate_text');
      $provider = $this->aiProviderManager->createInstance($providerConfig['provider_id'], $providerConfig);
      $translation = $provider->translateText(
        new TranslateTextInput($input_text, $langFrom?->getId(), $langTo->getId()),
        $providerConfig['model_id']
      );
```

Using non-default provider (for this example to work, you need to install Deepl provider module)

For the following example to work, you need to install [DeepL](https://www.drupal.org/project/ai_provider_deepl) module.

```php
$translator = \Drupal::service('ai.provider')->createInstance('deepl');
// Note that for DeepL, plain 'en' is not supported, you need to use
// 'en-gb' or 'en-us'.
$input = new TranslateTextInput($text, 'fi', 'en-gb');
/** @var \Drupal\ai\OperationType\TranslateText\TranslateTextOutput $translation */
$translation = $translator->translateText($input, 'default', []);
// Will output 'Hello world'.
return $translation->getNormalized();
```

### Text Translation Interfaces & Models

The following files define the methods available when doing a text translation call as well as the input and output.

* [TranslateTextInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TranslateText/TranslateTextInterface.php?ref_type=heads)
* [TranslateTextInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TranslateText/TranslateTextInput.php?ref_type=heads)
* [TranslateTextOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TranslateText/TranslateTextOutput.php?ref_type=heads)

### Text Translation Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Text Translation Explorer` under `/admin/config/ai/explorers/ai-translate-text` to test out different calls and see the code that you need for it.
