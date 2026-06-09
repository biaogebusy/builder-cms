# Versioning

The AI module generally follows the principles of [Semantic Versioning](https://semver.org/) and the [Drupal version number](https://www.drupal.org/docs/getting-started/understanding-drupal/understanding-drupal-version-numbers/what-do-version-numbers-mean-on-contributed-modules-and-themes) system.

This means that version numbers are in the format of `MAJOR.MINOR.PATCH-{stability}`. This means that the version number is made up of three parts:

## MAJOR version
The MAJOR version is incremented when there are incompatible API changes, backward-incompatible changes, full deprecations or significant changes that may require users to make adjustments when upgrading. In these cases we can not always guarantee that you will have a working upgrade path without manual intervention, though we will always try to provide as smooth an upgrade path as possible. Follow the [Breaking Changes document](breaking_changes.md) to get a sense of how to communicate those effectively.

## MINOR version
The MINOR version is incremented when functionality is added in a backward-compatible manner. This includes new features, enhancements, and improvements that do not break existing functionality. Minor versions may also include deprecations, but these should not affect existing functionality.

## PATCH version
The PATCH version is incremented when backward-compatible bug fixes are made. This includes fixes for security vulnerabilities, performance improvements, and other minor changes that do not affect the overall functionality of the module.

## Security releases
Security releases follow the same versioning as patch releases. For example, if the latest release is `1.2.3`, a security release would be `1.2.4`. They would be released on the same schedule as patch releases, but be communicated separately as well via [security advisories](https://www.drupal.org/security).

## Stability suffix
The stability suffix indicates the stability of the release. The AI module uses the following stability suffixes:

- `-dev`: Development version, not recommended for production use. Do not have upgrade paths.
- `-alpha`: Alpha version, may contain incomplete features and bugs. Not recommended for production use. Do not have upgrade paths.
- `-beta`: Beta version, more stable than alpha, but may still contain bugs. Not recommended for production use. Do not have upgrade paths.
- `-rc`: Release candidate, stable version that is almost ready for production use. May still contain minor bugs. Have upgrade paths.
- No suffix: Stable release, recommended for production use. Have upgrade paths.
