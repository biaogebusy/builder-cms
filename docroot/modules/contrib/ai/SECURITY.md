# Security Policy

## How to report a security finding

Please do **not** report security vulnerabilities in the public GitLab issue queue or drupal.org issues.

If you have a Drupal account, the preferred method for reporting security vulnerabilities is to use the [form on drupalcode.org](https://git.drupalcode.org/project/ai/-/work_items/new?issue%5Bconfidential%5D=true&issue%5Bdescription%5D=Keep%20%E2%80%9CTurn%20on%20confidentiality%E2%80%9D%20checked%20to%20report%20a%20potential%20security%20issue.%0A%0AThis%20module%20has%20a%20%7Binsert%20type%7D%20vulnerability.%0A%0AYou%20can%20see%20this%20vulnerability%20by%3A%0A1.%20Enabling%20the%20module%0A2.%20As%20a%20user%20with%20%7Bpermission%20name%7D%20permission%20do%20%7Bsome%20step%7D%0A3.%20%7Blist%20more%20steps%20as%20necessary%7D) with the "confidential" option checked. This will ensure that the issue is only visible to project maintainers and the Drupal Security Team.

If you cannot use the form or do not want to create a Drupal account, report them directly to the Drupal Security Team's standard private reporting channel at **security@drupal.org** per Drupal.org's contributed module security policy.

When reporting an issue, please include the following information:

* The affected version(s) of the AI module
* Detailed steps to reproduce the vulnerability
* The potential impact or risk
* A suggested fix or mitigation (if known)

## Disclosure policy

All security issues are handled via coordinated disclosure managed by the Drupal Security Team.

To protect users, please refrain from any public discussion of unfixed vulnerabilities in the GitLab issue queue, drupal.org issues, or other public forums.

Reporters will be credited in the public Security Advisory once a fix has been developed and released, unless they request to remain anonymous.

## Supported versions

The following branches of the AI module currently receive security fixes. Please note that experimental sub-modules may not be covered until they reach stable status.

| Version | Supported |
| ------- | --------- |
| 1.2.x   | Yes       |
| 1.3.x   | Yes       |
| 1.4.x   | Yes       |
| 1.x     | No        |
| 2.x     | No        |
