# Getting Started with AI Agent Test

## What is AI Agent Test?
AI Agent Test is a module that allows you to test the AI Agents decision making process over and over again, using a known input context against any provider and model combination.

It is very easy to be tricked when you use the Agent Explorer on some random samples of data to think that you have created a production ready agent, but the real world is much more complex. If you develop an agent you have domain knowledge about how to instruct it correctly, and your inputs will be very biased with correct vocabularies and context as compared to the real world.

The AI Agent Test module allows you to create a test suite of actual prompts that testers/end-users used that can be run against any agent, so you can be sure that it will work in production.

## Installation
The AI Agent Test module can be found on [Drupal.org](https://www.drupal.org/project/ai_agent_test). To install it, you just need to do the normal steps to install a Drupal module:

1. `composer require drupal/ai_agent_test`
2. Enable the module via the Drupal admin interface or using Drush: `drush pm:en ai_agent_test`

## Test parts
The test suite depends on two main components:

* Tests - these are the single tests that can run against one agent with a known input context. All tests are based on a known state of the website, meaning that if you for instance want to first add a vocabulary in a test, and then ask a question about that vocabulary, you can do that by creating two tests and run them in a group.
* Test Groups - these are just combinations of tests that can be set with a percentage success threshold. Agents are almost never 100% accurate, so you can set a threshold for how many of the tests in the group that should pass for the group to be considered successful, and then if you run them X amount of times, the average threshold should at least be that percentage for it to be considered successful or ready for production.

## Important to know
These tests are meant to be testing the agent's decision making process, not the actual deterministic tools that it uses. Any tool testing should use "normal" unit, kernel or function tests, and not the AI Agent Test module. The AI Agent Test module, might catch these errors, but it is not meant to be used for that purpose.

Because of this, the tests are not deterministic, and you should not expect the same results every time you run them, but rather that its probability of success is above the threshold you set for the test group.

## Try it out in Drupal CMS on DrupalForge
If you want to test the test (heh) without having to set up a Drupal site or set up tests yourself, [DrupalForge](https://drupalforge.org) has a lot of demos you can run with one click and get access to test and develop on.

Included there is a demo with all the tests written for Drupal CMS. You can find the demo for [Drupal CMS AI Agents Testing Framework](https://www.drupalforge.org/template/drupal-cms-ai-agents-testing-framework).
