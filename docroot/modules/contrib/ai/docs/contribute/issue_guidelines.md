# Issue Guidelines

To facilitate collaboration among contributors within the Drupal AI ecosystem, it's important to have clear and descriptive issues. This documentation focuses on content and metadata guidelines when creating and updating AI issues.

See the [List of issue fields](https://www.drupal.org/docs/develop/issues/fields-and-other-parts-of-an-issue/list-of-issue-fields) and [Creating or updating an issue report](https://www.drupal.org/community/contributor-guide/reference-information/quick-info/creating-or-updating-an-issue-report) for general Drupal issue guidelines.

**Table of Contents**

[TOC]

## Issue metadata

### Title

Create a clear, short but complete title with appropriate context.

**Bad examples:**

- Module is broken
- Weird error in chatbot

**Good examples:**

- AI Chatbot: "Undefined" error shown when using Grok4
- AI Translate: Unable to translate from Greek to English

### Project

The project field defaults to whichever project issue queue you are in, e.g., Artificial Intelligence (AI). Given there are many Drupal AI modules, the AI team will change the project to the most appropriate one if necessary.

### Category

Choose the most appropriate category:

- **Bug report**: Appears to be a bug or mistake.
- **Task**: Something that needs to be done that doesn't fall into other categories.
- **Feature request**: A new feature or a feature improvement.
- **Support request**: You need help. Also consider using the `#ai` Drupal Slack channel.
- **Plan**: Used by maintainers for roadmap planning and similar.

### Priority

When creating issues, use `Normal` or `Minor` for priority, and the AI team can adjust these as needed.

- **Critical**: Typically reserved for fatal bugs that need immediate attention.

- **Major**: Work that is prioritized on the roadmap or an important bug that isn't fatal. Major feature requests are the primary focus of the AI Initiative. 
These are typically worked on by dedicated contributors from Drupal AI sponsors, and are actively managed and supported by the AI team.

- **Normal**: This priority is used for most issues. For feature requests, these might be beneficial features, but aren't currently a focus for the AI team.

- **Minor**: Used for small changes or very low priority work.

Note, feature requests will not normally be prioritized as `Critical` though bugs may be. Features may be prioritized as `Minor` by a maintainer, and the issue may be switched to being a `Task` if the effort is small enough.

### Status

When creating or updating issues, update the status per the [issue status documentation](https://www.drupal.org/docs/develop/issues/fields-and-other-parts-of-an-issue/issue-status-field).

- Active
- Needs work ("NW")
- Needs review ("NR")
- Reviewed & tested by the community ("RTBC")
- Patch (to be ported)
- Fixed
- Postponed
- Postponed (maintainer needs more info)
- Closed (duplicate)
- Closed (won't fix)
- Closed (works as designed)
- Closed (cannot reproduce)
- Closed (outdated)
- Closed (fixed)

### Version

The current version is 1.2.x-dev. When in doubt, choose the highest version and it will be adjusted by the maintainers if needed.

### Component

When creating issues, select the most relevant component. If unsure, leave as “...to be triaged”.

- …to be triaged (default)
- AI API Explorer
- AI Assistants API
- AI Automators
- AI Chatbot
- AI CKEditor
- AI Content Suggestions
- AI Core module
- AI ECA
- AI External Moderation
- AI Logging
- AI Search
- AI Test
- AI Translate
- AI Validations
- Field Widget Actions
- Documentation
- Project Management
- Discussion
- Meetings
- Miscellaneous

### Assigned

Only assign issues to yourself if you will be working on it the same day, or you know that no one else plans on working on it. Unassign any issue that you can't work or you have finished working on. If an issue is assigned and no work has happened on it within a couple days, it may be unassigned or reassigned by the AI team.

### Issue tags

Issue tags are a free-tagging field used for purposes such as:

- Tracking issues for initiatives that span multiple projects or components.
- Checking status of project milestones (e.g., issues to be resolved before a release).
- Finding blocked or escalated issues that need immediate attention.

#### Initiative Tag

To indicate the issue is prioritized for the AI Initiative roadmap, the AI team will tag issues with `AI Initiative`.

Note, the wider community should not add this tag to issues. It will be added during triage by the AI team if needed.

#### Time-based Tags

To organize our work by month, issues are tagged with the target month (e.g., `January`, `February`), indicating the end of the month as the goal. As Drupal uses American English as its default, months should use American English, e.g., `October`.

If you will be working on an AI issue yourself and expect to be done at a certain time, you are welcome to add a month tag.

#### Skill-Based Tags

To organize our work around skill requirements, skill tags are added to issues. The AI team will add these tags, and the wider community are welcome to use them as well.

- concept
- design
- UX
- frontend
- backend
- devops
- ...and others as appropriate (use existing tags when possible)

Note, typically only one or two skill tags are necessary for any given issue.

#### Subtask Tags

It is standard practice to tag issues that need a specific next step. The AI team will add these tags, and the wider community are welcome to use them as well.

- Needs accessibility review
- Needs change record
- Needs design
- Needs design review
- Needs documentation
- Needs documentation updates
- Needs followup
- Needs issue summary update
- Needs manual testing
- Needs performance review
- Needs release note
- Needs reroll
- Needs screenshots
- Needs steps to reproduce
- Needs tests
- Needs usability review
- ...and others as appropriate (use [existing tags](https://www.drupal.org/docs/develop/issues/fields-and-other-parts-of-an-issue/issue-tags-special-tags) when possible)

Note, typically only one or two subtask tags are necessary for an issue at any given time.

#### Escalation Tags

If work needs to be escalated, use these tags:

- blocker
- Release blocker
- Needs maintainer review (used for module or submodule maintainer)
- Needs product manager review (used for product manager [Jamie and Christoph])
- Needs release manager review (used for release manager [Marcus and Kevin])

#### Event Tags

For issues that we want to focus on during a specific event’s contribution day, they should be tagged with the event-specific tag. For example, `Vienna2025` for DrupalCon Vienna 2025. People at the event can search for the appropriate tag to find issues. Additionally, to encourage new contributors, issues can be tagged with `Novice`. The `Novice` tag doesn’t mean they are inexperienced developers or open source contributors, but just that they are new to the Drupal contribution process.

## Issue summary & relationships

### Issue summary

#### Problem/Motivation

For bugs:

- What’s happening vs. expected behavior
- Include any error messages, stack traces, or logs
- If it’s a regression, mention when it last worked

For features:

- What is the feature?
- What problem does it solve or what value does it add?
- What is the user story or use case, e.g., `As a [type of user], I want to [goal] so that [benefit].`
- Add mockups, visual references, or examples
- Links to spec, mockups, wireframes, or design files if available
- Note any special testing requirements
- What are the requirements or acceptance criteria, e.g., `When I read the documentation I understand how the feature is supposed to work`

For other:

- What needs to be done and why
- Link to related tickets, technical docs, Slack threads, etc.
- Describe what does “finished” looks like

#### Steps to reproduce

For bugs, the steps to reproduce are required and should include information like:

- Drupal version
- Drupal AI version
- Browser
- AI provider
- Any relevant config or flags
- Provide a screenshot showing the issue
- URL(s) of the affected page(s)

#### Proposed resolution

Provide details, if possible, or leave empty:

- For bugs: Add details if you have ideas of how the bug can be fixed
- For features: Add details if you have ideas of how the feature can be implemented
- For other: Provide any details on how to resolve the issue

#### Remaining tasks

If you know the tasks that need to happen to work on the issue, add them; otherwise, leave empty.

Example for bugs:

- Fix the bug
- Add a test
- Provide testing instructions in Drupal AI DDEV environment
- Testing

Example for feature requests:

- Create concept
- Implement
- Provide testing instructions in Drupal AI DDEV environment
- Provide documentation
- Testing

For others:

- Describe what needs to be done
- Break down into actionable steps if helpful

#### Optional: Other details as applicable

If this affects user interface, API, data model, components, third-party libraries, etc, add details; otherwise, leave empty.

### Parent issue

If this should be a child issue, add the parent issue.

### Related issues

Add any relevant related issues.

## Finding Issues

### For Contributors

**Option 1:** To find issues to work on, there is an async weekly meeting in the `#ai-contrib` Drupal Slack channel that lists high priority issues. You can look for the most recent meeting and look at those issues.

**Option 2:** If none of those issues are good for you to work on, shout out in the `#ai-contrib` Drupal Slack channel that you want to help and are looking for an issue to work on and explain your skills and time constraints, e.g., `I'm a backend developer with about 5 hours to contribute this week on AI issues`

**Option 3:** Contributors can also find issues by following these steps:

- Go to the [advanced issue search](https://www.drupal.org/project/issues/search/ai).
- Filter by status, e.g., `- Open issues -`, `Needs work`
- Optionally, filter by category, e.g., `Bug report`, `Feature request`
- Optionally, filter by skill tags, e.g., `design`, `frontend`, `backend`

Note, if no issues are found, remove some of the filters.

**Example:** To search for frontend bugs in the AI module that need work, filter by:

- **Category:** `Bug report`
- **Status:** `Needs work`
- **Issue tags:** `frontend`

### For Organizers

Organizers can search for issues based on the following criteria:

- **Initiative:** Search for issues tagged by initiative (e.g., `AI Initiative`).
- **Priority:** Focus on issues marked as `Major`
- **Month:** Search for issues tagged by month (e.g., `June`).
- **Status:** Filter by specific statuses (e.g., `Needs work`, `Needs review`).

**Example:** To find all higher priority tasks the AI Initiative is focusing on in June, search by:

- **Category:** `Task'
- **Status:** `Major'
- **Issue tags:** `June`, `AI Initiative`
