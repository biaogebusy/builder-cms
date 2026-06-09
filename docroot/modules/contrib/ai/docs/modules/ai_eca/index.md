# AI ECA integration
## What is the AI ECA integration module?
The AI ECA integration module provides integration with the [ECA: Event - Condition - Action](https://www.drupal.org/project/eca)
module. It provides a number of ECA "Actions" which can be used by the module to
conditionally send and receive data from an LLM. For more information on the ECA
concepts, please see [their documentation](https://ecaguide.org/).

## Dependencies
The AI Content Editing Tools requires the AI Core module to be installed and
configured, and a valid AI Provider module to be enabled and configured.

The module also requires that the ECA module is enabled, and that at least one
workflow is configured to use its Actions.

## Installation & configuration
1. Install the module
2. Follow the instructions in [the ECA documentation](https://ecaguide.org/) for configuring a new workflow.

## Usage
When adding a new ECA workflow, you will see the AI Actions listed amongst the
options for actions to take. Each Action has its own configuration depending on
the task and the Provider being used.
