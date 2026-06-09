# Developer Information

> Please make sure you follow the [AI module issue guidelines](issue_guidelines.md).

If you have decided to contribute a bug, task or a feature to the AI module, you will need to follow some guidelines to ensure that your contribution is consistent with the project's standards and practices.

## Issue Tracking
All contributions should be tracked in the [AI module issue queue](https://www.drupal.org/project/issues/ai?categories=All). Before starting work on a new feature or bug fix, please check if there is already an existing issue for it. If not, create a new issue with a clear description of the feature or bug you want to address. This will help maintainers and other contributors understand the context and purpose of your work.

## Assigning Issues
Anyone can assign themselves to an issue in the AI module issue queue. However, it is recommended to first discuss the issue in the comments to ensure that there is no duplication of effort. If you are working on an issue, please update the issue status to "In Progress" and provide regular updates on your progress. Please note that there is no guarantee that your issue will be merged, if it has not been discussed with the maintainers first. This is to ensure that the feature or bug fix is in line with the overall direction of the AI module. If you are unsure, please create a feature request issue first to discuss the feature or bug fix before starting work on it. You can also ask for help in the [Drupal AI-contrib Slack channel](https://www.drupal.org/slack) before starting work on an issue. The community is always willing to help and provide guidance.

## Branching Logic
The branching logic is as follows - we are using that 1.2.0 was the latest stable release in this example:

- **1.2.x**: This branch is for the latest stable release. All bug fixes and minor features that are backward compatible should be merged here and will be merged into the next minor release. If the bug fix is critical, it can also be tagged `Needs backport to 1.1.x` to be included in the previous stable release.
- **1.3.x**: This branch is for the next minor release. New features and changes that are backward compatible and non-breaking should be merged here. This branch will eventually become the next stable release.
- **2.0.x**: This branch is for the next major release. Any breaking changes or major features should be merged here. This branch will eventually become the next major stable release.

When creating a pull request, please ensure that you are merging into the correct branch based on the nature of your contribution. If you are unsure, please ask in the AI-contrib Slack channel or create an issue to discuss it.

## Update Hooks
We follow a specific naming convention for update hooks (`hook_update_N`) to ensure they are ordered correctly and avoid collisions. The naming convention is `MAJOR` + `MINOR` + `three numbers` (001-999).

The structure is: [Major Version][Minor Version][Three-digit sequence].

Examples:
- For version **1.1.x**, the update hooks should be named `11001`, `11002`, etc.
- For version **1.2.x**, the update hooks should be named `12001`, `12002`, etc.
- For version **1.3.x**, the update hooks should be named `13001`, `13002`, etc.
- For version **2.0.x**, the update hooks should be named `20001`, `20002`, etc.

This convention ensures that update hooks in later versions always have a higher number than those in earlier versions.

## Coding Standards
The AI module follows the [Drupal coding standards](https://www.drupal.org/docs/develop/standards). Please ensure that your code adheres to these standards before submitting a pull request. We have a set of automated tools that will help you check your code against these standards, such as [PHP Code Sniffer](https://www.drupal.org/project/coder) and [PHPstan](https://www.drupal.org/project/phpstan). This means that no merge request will be accepted or can be merged if the code does not pass the linters.

## Documentation
All code contributions should be well-documented. This includes inline comments, function and method documentation, and any necessary documentation for new features or changes. Documentation should be written in Markdown format and placed in the appropriate directory within the `docs` folder. This will automatically be built for the correct versions and shown here.

For bugs usually documentation is not needed.

## Testing
All feature contributions should include tests. This includes unit tests, functional tests, and kernel tests as appropriate. The AI module uses [PHPUnit](https://phpunit.de/) for testing, and you should ensure that your tests cover the new functionality or changes you are introducing.

For tasks and bugs, this is usually not needed, but for bugs that are regression bugs, it is highly appreciated if you can add a test that shows the bug and then fix it. This will ensure that the bug does not reappear in the future.

## AI-Contrib Slack Channel
For any questions or discussions related to contributions, you can join the [Drupal AI-contrib Slack channel](https://www.drupal.org/slack). This is a great place to ask for help, share your progress, and get feedback from other contributors and maintainers. The community is active and supportive, and you are encouraged to engage with others to improve your contributions and the AI module as a whole.

## Mentorship
If you are new to contributing to the AI module or Drupal in general, you can request mentorship from contributors or maintainers. Reach out to [marcus_johansson](https://www.drupal.org/u/marcus_johansson) via the contact form on Drupal.org or in the AI-contrib Slack channel. Mentorship can help you understand the contribution process, coding standards, and best practices for contributing to the AI module.
