# Contribute Testing

> Please make sure you follow the [AI module issue guidelines](issue_guidelines.md).

This section covers how you can contribute by testing the AI module. Testing is crucial for ensuring the quality and stability of the AI module, especially close to a new release. It helps identify bugs, verify fixes, and ensure that new features work as intended.

## Manual Testing

Anytime we come close to a new release, we will ask for help in the [AI-contrib Slack channel](https://www.drupal.org/slack) to test the module and submodules under certain circumstances, like different Drupal versions, within Drupal CMS etc. This is a great way to contribute if you are not a developer or do not have the time to write code.

We will create issues with test plans, that you simply have to follow and report back on the issue with your findings. This helps us ensure that the module works as expected across different environments and use cases. Anyone helping out with testing will be credited for their contribution, even if they do not find any bugs.

## Automated Testing

The AI module was developed at a rapid pace, where results were priority 1, 2 and 3. This means that we have a lot of code that is not tested, and we are working on adding tests to the module. Any type of kernel or functional test, based on how the features are supposed to behave would be great to add. The main maintainers do have limited time and a lot of time initially is spent on feature development.

The AI space is still moving at such a rapid pace that we are still pumping out new features and improvements, so we are not yet at a point where we can focus on writing tests for all the code. This means that any help with writing tests is greatly appreciated.

## Type of Tests

We see that kernel and functional tests are the most useful for the AI module, as they test the functionality of the module in a real-world scenario. Unit tests are also useful, but they are usually written by the developer who created the code. However, if you find a bug and can write a unit test that shows the bug and then fix it, that would be greatly appreciated. This will ensure that the bug does not reappear in the future.

## I want to contribute tests, what do I do?

First of all, thank you for wanting to contribute tests! To contribute tests that you have verified are not already covered by existing tests, please follow these steps:

1. Create a ticket in our [issue queue](https://www.drupal.org/project/issues/ai?categories=All) with a description of the tests you want to contribute. This helps maintainers understand what you are working on and ensures that there is no duplication of effort.
2. Click `Get Push Access` to create a repo fork.
3. Download this fork and add your tests in the appropriate directory within the `tests` folder.
4. Push your changes back and create a merge request.
5. We will merge your tests into the main branch and you will be credited for your contribution.

## I want to help with testing, but I do not know which tests are missing.

Please join the [AI-contrib Slack channel](https://www.drupal.org/slack) and ask for help. The community is active and supportive, and you can get guidance on what tests are needed or how to write tests for specific features. You can also reach out to [marcus_johansson](https://www.drupal.org/u/marcus_johansson) via the contact form on Drupal.org for mentorship or guidance on writing tests or what tests are needed.

## More information on testing

For more information on how to write tests for the AI module, please refer to the [Developer Information](developer_information.md) section. It contains guidelines on coding standards, testing requirements, and how to contribute code to the AI module.

You can also see the current tests in the [tests](https://git.drupalcode.org/project/ai/-/tree/1.1.x/tests) directory of the AI module repository. This will give you an idea of how the tests are structured and what types of tests are already in place.
