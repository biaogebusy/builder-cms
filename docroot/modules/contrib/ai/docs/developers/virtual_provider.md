# Mock a Provider

This document will provide information on how you can use one of the providers once and then replay that request over and over while you are developing your module.

This is useful when your biggest pain points are developing your module and the code around that, rather than the API call. This makes it faster to develop, with less waiting time and less costs.

This can also be used for kernel and functional tests, where you can record a request and then replay it in your tests, making it easier to test your module without having to rely on the provider calls.

## How to enable it.

The provider is the so called EchoAI provider that is part of the modules in the test folder. You can find them under tests/modules. It's made by default to echo the request you send it, hence the name EchoAI.

However it can also be used to record a full request, and if the exact same request is made again, it will return the same response as before.

Because it is a test module it will not be visible in the UI or possible to enable via Drush, by default. To be able to install it follow these steps:

1. In your settings.php file add the following line: `$settings['extension_discovery_scan_tests'] = TRUE;`.
2. Clear the cache with `drush cr`.
3. Enable the module with `drush en ai_test` or by looking for `ai testing integration` in the list.

## How to start recording requests.
After you enabled it follow the following steps to start recording requests:

1. Go to the provider settings for AI Test at `/admin/config/ai/providers/ai-test`.
2. Enable the recording by clicking the checkbox for "Catch results".
3. If you want the system to also record the wait time, for working on UX/UI, you can also enable the "Catch processing time" checkbox.
4. Do your normal AI request to any other provider, for instance via the AI API Explorer or the AI Assistant using OpenAI or Anthropic.
5. Turn off the recording by unchecking the "Catch results" checkbox.
6. Visit the list of recorded requests at `/admin/content/ai-mock-provider-result`.

## How to replay requests.
To replay the requests you recorded, you can do the following:

1. Click on Edit on the request you want to replay.
2. Rename it from Unnamed to something more descriptive.
3. Check `Mock enabled`
4. Change anything else with the sleep time, request or response if you would like to
5. Rerun the request that you did on #4 while recording it, but do it this time against the EchoAI provider and gpt-test model.
6. You should now see the same response as you did when you recorded it, but this time it will be much faster and without any costs.

## How to use this for your tests.
If you want to use this for your tests, do the following steps:

1. In the list of requests, click on the dropbutton and click export.
2. Place the file you downloaded in your module under `tests/resources/ai_test/requests/{operation_type}` where operation type most likely is chat.

Now if you do the same request in your tests, it will automatically use the request you recorded and return the response you recorded.
