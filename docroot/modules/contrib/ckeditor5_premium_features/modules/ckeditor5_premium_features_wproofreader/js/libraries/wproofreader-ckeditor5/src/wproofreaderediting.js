import { Plugin } from 'ckeditor5/src/core.js';

import WProofreaderToggleCommand from './wproofreadertogglecommand.js';
import WProofreaderSettingsCommand from './wproofreadersettingscommand.js';
import WProofreaderDialogCommand from './wproofreaderdialogcommand.js';

/**
 * The {@code WProofreaderEditing} plugin. It introduces all {@code WProofreader} commands.
 */
export default class WProofreaderEditing extends Plugin {
	/**
	 * @inheritDoc
	 */
	static get pluginName() {
		return 'WProofreaderEditing';
	}

	/**
	 * Initializes the {@code WProofreaderEditing} plugin.
	 * @public
	 */
	init() {
		this._addCommands();
	}

	/**
	 * Initializes the {@code WProofreaderEditing} plugin in the third initialization stage.
	 * @public
	 */
	afterInit() {
		this._enableInModes([
			{ modeName: 'TrackChanges', editingName: 'TrackChangesEditing' },
			{ modeName: 'RestrictedEditingMode', editingName: 'RestrictedEditingModeEditing' }
		]);
	}

	/**
	 * Adds the {@code WProofreader} commands to the editor.
	 * @private
	 */
	_addCommands() {
		this.editor.commands.add('WProofreaderToggle', new WProofreaderToggleCommand(this.editor));
		this.editor.commands.add('WProofreaderSettings', new WProofreaderSettingsCommand(this.editor));
		this.editor.commands.add('WProofreaderDialog', new WProofreaderDialogCommand(this.editor));
	}

	/**
	 * Enables the {@code WProofreader} commands in a certain CKEditor 5 modes.
	 * @private
	 */
	_enableInModes(modes) {
		modes.forEach((mode) => {
			this._enableInMode(mode.modeName, mode.editingName);
		});
	}

	/**
	 * Enables the {@code WProofreader} commands in a certain CKEditor 5 mode.
	 * @private
	 */
	_enableInMode(modeName, editingName) {
		const isModeLoaded = this.editor.plugins.has(modeName);

		if (isModeLoaded) {
			const editing = this.editor.plugins.get(editingName);
			const commands = ['WProofreaderToggle', 'WProofreaderSettings', 'WProofreaderDialog'];

			commands.forEach((command) => editing.enableCommand(command));
		}
	}
}
