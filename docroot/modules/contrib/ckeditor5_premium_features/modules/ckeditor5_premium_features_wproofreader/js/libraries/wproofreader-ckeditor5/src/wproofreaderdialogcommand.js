import { Command } from 'ckeditor5/src/core.js';

/**
 * The {@code WProofreaderDialogCommand} to open the {@code WProofreader} dialog.
 */
export default class WProofreaderDialogCommand extends Command {
	/**
	 * Executes the {@code WProofreaderDialogCommand}.
	 * @public
	 * @inheritDoc
	 */
	execute() {
		const wproofreader = this.editor.plugins.get('WProofreader');

		wproofreader.openDialog();
	}
}
