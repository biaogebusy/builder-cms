## You want to mock a provider call?
One of the problems when working with testing of the AI module is that the provider calls are not always deterministic. This means that the same input can yield different results, which makes it hard to test. It would also cost a lot of money to test the AI module with real provider calls, so we need to spoof the provider calls.

We have added a provider called AI Test, that you can find under tests/modules/ai_test, that is a spoof provider. It has the functionality to look for matching requests and return a predefined response. These requests can be defined in YAML and be added to your contrib module, meaning we have a standardized way to run kernel and functional tests with the AI module.

To read more about how to mock, see the [Mock a Provider](../../developers/virtual_provider.md) documentation.
