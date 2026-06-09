# Contributing to Drupal AI

The Drupal AI ecosystem encompasses thousands of lines of code with many moving components. It's a complex module that balances stability with bleeding-edge innovation. A thriving AI community is vital for Drupal's success, and since AI technology evolves rapidly, we need collective effort to keep pace with change.

Contributing to Drupal is a well-documented process, and this guide focuses specifically on contributing to the Drupal AI ecosystem, which includes the AI module, its submodules, and related AI projects. Below you'll find a streamlined overview of the contribution workflow with links to more detailed documentation where needed.

## Prerequisites

Before you begin contributing, ensure you have:

*   [DDEV](https://ddev.readthedocs.io/) installed locally
*   A [Drupal.org](https://www.drupal.org) account
*   Git configured on your machine
*   Basic familiarity with Drupal development

## Before You Start

When creating or updating issues, use clear metadata including a descriptive title, steps to reproduce, component, priority, and relevant tags. Please follow the [AI module issue guidelines](issue_guidelines.md) to ensure efficient issue management.

## Getting Started with Code Contributions

### Finding Issues to Work On

There are two main ways to find issues:

**Option 1: Browse the Drupal AI Project Issue Queue**

Visit the [Drupal AI project issue queue](https://www.drupal.org/project/issues/ai) and browse available issues. Read the [issue guidelines](issue_guidelines.md#finding-issues) for detailed filtering and prioritization information.

**Option 2: Join an AI Initiative Sprint**

Look for issues tagged with "[AI Initiative Sprint](https://www.drupal.org/project/issues/search?issue_tags_op=%3D&issue_tags=AI+Initiative+Sprint)" across all Drupal projects.

These sprint issues are specially curated for collaborative contribution events and often include extra guidance.

**Tip:** Before starting work, assign the issue to yourself and comment with your planned delivery timeline. This prevents duplicate efforts and allows maintainers to provide guidance. Please use the following syntax for estimated delivery time so it can be processed automatically:

* ETD 1d
* ETD 2d
* ...

### Setting Up Your Local Environment

Use DDEV to quickly set up a development environment with Drupal AI:

```bash
# Create and configure project
mkdir drupal-ai-dev
cd drupal-ai-dev/
ddev config --project-type=drupal11 --docroot=web
ddev start

# Install Drupal
ddev composer create-project "drupal/recommended-project:^11"
ddev composer require drush/drush
ddev drush site:install --account-name=admin --account-pass=admin -y

# Install Drupal AI, Key and OpenAI provider.
# OpenAI is an example here, you can use other providers as well.
ddev composer require drupal/ai drupal/key drupal/ai_provider_openai
ddev drush cr
ddev drush en ai_provider_openai -y

# Launch your site
ddev launch
```

After installation…

1. Add your API key to the Key module at `/admin/config/system/keys/add`.
2. Visit `/admin/config/ai/providers/openai` and link the key you created.
3. Once your AI Provider(s) is configured, the AI module will select appropriate
   defaults for the various [Operation Types](../developers/base_calls.md#the-operation-types-and-how-to-use-them).
   You can make your own selections by visiting `/admin/config/ai/settings` and
   updating the settings manually.
4. Enable your desired submodules for specific functionality.

For advanced setup options, see the [DDEV documentation for Drupal AI](../developers/ddev.md).

### Creating an Issue Fork and Merge Request

Drupal uses GitLab for code collaboration.


1.  **Create an issue fork:**

    ![Create issue fork button](https://www.drupal.org/files/no-fork-yet.png)
    
    *   Navigate to the issue you're working on and click "Create issue fork" in the issue sidebar
    *   For detailed instructions, see [Creating Issue Forks](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/creating-issue-forks)
    *   Clone the fork to your local machine using the commands provided

    **Get push access to an existing fork**

    *   If you did not create the fork, you may need to click the “Get push access” button after the “Issue fork project-3393112” text (above the merge request).
        For more information about editing an existing fork, see [Editing a GitLab merge request](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/editing-an-existing-fork)


2.  **Follow the issue-specific setup instructions:**
    *   After creating your fork, the issue will provide specific Git commands and setup steps
    *   Follow these instructions to check out the correct branch and set up your environment
    *   Each issue may have unique requirements, so carefully read the provided guidance

3.  **Make your changes:**
    *   Write clean, well-documented code following [Drupal coding standards](https://www.drupal.org/docs/develop/standards)
    *   Test your changes thoroughly

4.  **Push and create a Merge Request:**

    ```bash
    git add .
    git commit -m "Issue #[number]: [Brief description]"
    git push origin [your-branch-name]
    ```

    *   In GitLab, create a Merge Request targeting the appropriate branch and reference the issue number in your MR title
    *   For more details, see the [Merge Request guide](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/creating-merge-requests)

### Documenting Your Work

Clear documentation helps maintainers review and merge your contribution faster.

**In Your Merge Request:**

*   **Title:** Use format `Issue #[number]: [Clear description]`
*   **Description:** Include:
    *   What problem you're solving
    *   Your approach/solution
    *   Testing steps (how reviewers can verify your fix)
    *   Before/after screenshots (for UI changes)
    *   Any edge cases or known limitations

**In the Issue Queue:**

*   **Update the issue status** to "Needs review" when your MR is ready
*   **Comment on the issue** with:
    *   Link to your MR
    *   Summary of changes made
    *   Any questions for maintainers
    *   Dependencies or related issues
*   **Respond to feedback** promptly and update your MR as needed

**Commit Messages:**

Follow this format:

```
Issue #[number] by [username]: [Brief description]

[Detailed explanation of changes if needed]
```

## Requesting Specialist Support

During development, you can request help from specialist support teams by adding the appropriate tags to your issue:

* **Needs QA**: Add this tag when you need quality assurance testing or review of your contribution
* **Needs UX review**: Add this tag when you need user experience design review or guidance

## Non-code Ways to Contribute

Beyond code contributions, there are many other valuable ways to help:

- **Bug reporting and fixing**: Help us find and report bugs in the Drupal AI ecosystem. This is crucial for maintaining quality and stability. Read more in the [Contribute Bug Reports](bug_finding.md) section.

- **Feature requests and implementation**: If you have an idea for a new feature or improvement, create a feature request in the [AI Issue queue](https://www.drupal.org/project/issues/ai?categories=All). If you can implement it, please create a merge request. Read more in the [Contribute Features](features.md) section.

- **Testing**: Help us test the Drupal AI ecosystem, either manually or by writing automated tests. This ensures the module works across different environments and use cases. Read more in the [Contribute Testing](testing.md) section.

- **Documentation**: Help improve documentation for the Drupal AI ecosystem. This is crucial for helping users and developers understand how to use and contribute effectively. Read more in the [Contribute Documentation](documentation.md) section.

- **Issue triage**: Issue metadata (status, component, priority, tags) are important for efficient issue management. Help by reviewing issues and updating metadata when needed. Read more in the [Issue Guidelines](issue_guidelines.md) section.

- **Developer information**: Learn more about the module's architecture and development practices in the [Developer Information](developer_information.md) section.

## Getting Help

*   **Documentation:** [Drupal AI project pages](https://project.pages.drupalcode.org/ai/1.2.x/)
*   **Community:** Join the `#ai` channel on [Drupal Slack](https://www.drupal.org/slack)
*   **Contributor Guide:** [Drupal.org Contributor Guide](https://www.drupal.org/community/contributor-guide)

## Code of Conduct

All contributions must follow the [Drupal Code of Conduct](https://www.drupal.org/dcoc). Be respectful, inclusive, and constructive in all interactions.

## Video Tutorial

Watch this [step-by-step guide to contributing to Drupal](https://www.youtube.com/watch?v=NVlrFPdF1jk) for a visual walkthrough of the contribution process.
