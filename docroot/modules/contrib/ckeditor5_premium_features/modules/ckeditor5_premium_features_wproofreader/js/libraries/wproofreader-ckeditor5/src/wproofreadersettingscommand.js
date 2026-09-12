import { Command } from 'ckeditor5/src/core.js';

/**
 * The {@code WProofreaderSettingsCommand} to open the {@code WProofreader} settings.
 */
export default class WProofreaderSettingsCommand extends Command {
	/**
	 * Executes the {@code WProofreaderSettingsCommand}.
	 * @public
	 * @inheritDoc
	 */
	execute() {
		const wproofreader = this.editor.plugins.get('WProofreader');

		wproofreader.openSettings();
	}
}
