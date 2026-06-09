/**
 * @file Implements a balloon menu for AI assistant that appears when text is selected.
 */

import { Plugin } from 'ckeditor5/src/core';
import { addListToDropdown, createDropdown } from 'ckeditor5/src/ui';
import { ContextualBalloon, clickOutsideHandler } from 'ckeditor5/src/ui';
import icon from '../../../../icons/sparkles.svg';
import { Collection } from 'ckeditor5/src/utils';

export default class AiBalloonMenu extends Plugin {
  static get requires() {
    return [ContextualBalloon];
  }

  init() {
    const config = this.editor.config;
    const options = config.get('ai_ckeditor_ai');

    if (!options) {
      return;
    }

    // The balloon plugin creates its own UI component.
    this._balloon = this.editor.plugins.get(ContextualBalloon);

    // Create the balloon panel view.
    this._createMenuView();

    // Show the balloon menu when text is selected.
    this._enableBalloonMenuOnSelection();
  }

  /**
   * Creates a menu view inside the balloon.
   */
  _createMenuView() {
    const editor = this.editor;
    const config = this.editor.config;
    const options = config.get('ai_ckeditor_ai');
    const locale = editor.locale;

    // Create a collection for menu items.
    const items = new Collection();

    // Add all enabled plugins to the collection.
    if (typeof options.plugins !== 'undefined') {
      Object.keys(options.plugins).forEach(function (plugin_id) {
        if (options.plugins[plugin_id].enabled) {
          items.add({
            type: 'button',
            model: {
              isEnabled: options.plugins[plugin_id].enabled,
              label: options.plugins[plugin_id].meta.label,
              withText: true,
              command: 'AiDrupalDialog',
              group: 'ai_ckeditor_ai',
              plugin_id: plugin_id
            }
          });
        }
      });
    }

    // Create a dropdown inside the balloon.
    this.menuView = createDropdown(locale);

    // Add a heading for the menu.
    this.menuView.buttonView.set({
      label: 'AI Assistant',
      withText: true,
      tooltip: false,
      icon
    });

    // Add menu items to the dropdown.
    addListToDropdown(this.menuView, items);

    // Handle clicks on dropdown items.
    this.listenTo(this.menuView, 'execute', (event) => {
      this.editor.execute(event.source.command, event.source.group, event.source.plugin_id, event.source.label);

      // Hide the balloon menu after executing a command.
      this._hideMenu();
    });

    // Register a click handler for outside the balloon.
    clickOutsideHandler({
      emitter: this.menuView,
      activator: () => this._balloon.visibleView === this.menuView,
      contextElements: [this._balloon.view.element],
      callback: () => this._hideMenu()
    });
  }

  /**
   * Enables the balloon menu to appear when text is selected.
   */
  _enableBalloonMenuOnSelection() {
    const editor = this.editor;
    const selection = editor.model.document.selection;

    // Update on selection change.
    this.listenTo(selection, 'change:range', () => {
      // Get the command to check if it's enabled.
      const aiDialogCommand = editor.commands.get('AiDrupalDialog');

      // Only show if we have a non-empty selection and the command is enabled.
      if (selection.hasOwnRange && !selection.isCollapsed && aiDialogCommand.isEnabled) {
        // Small delay to avoid showing the balloon during rapid selection changes.
        setTimeout(() => {
          if (selection.hasOwnRange && !selection.isCollapsed) {
            this._showMenu();
          }
        }, 200);
      } else {
        this._hideMenu();
      }
    });
  }

  /**
   * Shows the balloon menu.
   */
  _showMenu() {
    // Don't add the menu twice.
    if (this._balloon.hasView(this.menuView)) {
      return;
    }

    const selection = this.editor.model.document.selection;

    // If there's no selection or it's collapsed, don't show the menu.
    if (!selection.hasOwnRange || selection.isCollapsed) {
      return;
    }

    // Add the menu to the balloon.
    this._balloon.add({
      view: this.menuView,
      position: this._getBalloonPositionData()
    });
  }

  /**
   * Hides the balloon menu.
   */
  _hideMenu() {
    if (this._balloon.hasView(this.menuView)) {
      this._balloon.remove(this.menuView);
    }
  }

  /**
   * Gets the position for the balloon relative to the selection.
   */
  _getBalloonPositionData() {
    const editor = this.editor;
    const view = editor.editing.view;
    const viewDocument = view.document;
    const targetSelection = view.domConverter.viewRangeToDom(viewDocument.selection.getFirstRange());

    return {
      target: targetSelection
    };
  }
}
