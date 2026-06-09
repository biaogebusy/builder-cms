import AIUI from './aiui';
import { Plugin } from 'ckeditor5/src/core';
import AiNetworkStatus from "./Utility/AiNetworkStatus";

export default class AiCKEditor extends Plugin {
  static get requires() {
    return [AIUI, AiNetworkStatus];
  }
}
