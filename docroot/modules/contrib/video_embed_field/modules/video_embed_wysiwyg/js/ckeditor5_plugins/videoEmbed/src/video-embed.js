import { Plugin } from 'ckeditor5/src/core';
import VideoEmbedEditing from './video-embed-editing';
import VideoEmbedUI from './video-embed-ui';

export default class VideoEmbed extends Plugin {
  static get requires() {
    return [VideoEmbedEditing, VideoEmbedUI];
  }
}
