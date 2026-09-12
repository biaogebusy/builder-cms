import { Command } from 'ckeditor5/src/core.js';

/**
 * The {@code WProofreaderToggleCommand} to toggle the {@code WProofreader}.
 */
export default class WProofreaderToggleCommand extends Command {
	/**
	 * Executes the {@code WProofreaderToggleCommand}.
	 * @public
	 * @inheritDoc
	 */
	execute() {
		const wproofreader = this.editor.plugins.get('WProofreader');

		wproofreader.toggle();
	}
}
