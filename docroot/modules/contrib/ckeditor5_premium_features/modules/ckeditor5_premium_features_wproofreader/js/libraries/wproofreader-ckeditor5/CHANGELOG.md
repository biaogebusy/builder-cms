# WProofreader plugin for CKEditor 5 Changelog

## 2.3.1 – 2023-06-29

* Increase the distance from the badge to the edges of the editor. [#71](https://github.com/WebSpellChecker/wproofreader-ckeditor5/issues/71).

## 2.3.0 – 2023-06-13

* Enabled all the actions such in the badge similarly to ones in the toolbar icon. [#67](https://github.com/WebSpellChecker/wproofreader-ckeditor5/issues/67).
* Improved text hint for the WProofreader toolbar icon.  [#68](https://github.com/WebSpellChecker/wproofreader-ckeditor5/issues/68).

## 2.2.1 – 2023-05-10

Internal changes only (updated dependencies, documentation, etc.).

## 2.2.0 – 2022-10-11

* Make WProofreader work with Restricted Editing mode. [#31](https://github.com/WebSpellChecker/wproofreader/issues/31).

The new version of the plugin is compatible with WebSpellChecker v5.25.0 and higher.

## 2.1.0 – 2022-05-04

* Added functionality that properly disables the plugin for certain editor's modes.

## 2.0.6 – 2021-06-15

Internal changes only (updated dependencies, documentation, etc.).

## 2.0.5 – 2020-05-25

### Bug fixes

* WProofreader UI can't be opened if the tracking changes mode is toggled on. [#27](https://github.com/WebSpellChecker/wproofreader-ckeditor5/issues/27).

## 2.0.4 – 2021-04-29

Internal changes only (updated dependencies, documentation, etc.).

## 2.0.3 – 2021-04-29

Internal changes only (updated dependencies, documentation, etc.).

## 2.0.2 – 2021-02-12

### Bug fixes

* If the backend is unavailable for more than 5 requests, the instances states won't be synchronized. [#18](https://github.com/WebSpellChecker/wproofreader-ckeditor5/issues/18).

This fix is bundled with the release of [WebSpellChecker v5.10.0](https://webspellchecker.com/release-notes/v5-10-0/) and higher.

## 2.0.1 – 2020-12-16

Internal changes only (updated dependencies, documentation, etc.).

## 2.0.0 – 2020-12-16

* Added a WProofreader button with a dropdown menu to the CKEditor 5 toolbar where users can access settings, open proofread in dialog mode or turn the functionality on/off. All those action items were moved from the floating badge.
* The badge is kept there to indicate a number of errors found and show the progress of text checking (loader). If the plugin is in the disabled state (off), the badge will be hidden.
* Implemented synchronization of the plugin state in the case of the CKEditor 5 multi-root mode.

The new version of the plugin is compatible with [WebSpellChecker v5.8.0](https://webspellchecker.com/release-notes/v5-8-0/) and higher.

## 1.0.6 – 2020-12-02

### Bug fixes

* Destroying the CKEditor before it is completely created causes the plugin to fail. [#13](https://github.com/WebSpellChecker/wproofreader-ckeditor5/issues/13).

## 1.0.5 – 2020-07-31

Internal changes only (updated dependencies, documentation, etc.).

## 1.0.4 – 2020-07-21

Internal changes only (updated dependencies, documentation, etc.).

## 1.0.3 – 2020-07-21

Internal changes only (updated dependencies, documentation, etc.).

## 1.0.2 – 2020-07-20

Internal changes only (updated dependencies, documentation, etc.).

## 1.0.1 – 2020-07-20

Internal changes only (updated dependencies, documentation, etc.).

## 1.0.0 – 2020-07-17

This is a pilot version of the WProofreader plugin for CKEditor 5. The main differences between plugin and classic WProofreader are:

* Shipped and configured as a plugin for CKEditor 5.
* Tailored theme based on styles of CKEditor 5.
* Compatible with the [multi-root editor](https://ckeditor.com/docs/ckeditor5/latest/examples/framework/multi-root-editor.html).
* Automatically disabled the dialog mode of WProofreader when the real-time collaboration features or multi-root editor are turned on.
