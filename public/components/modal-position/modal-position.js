import { openComponent } from '../component-runtime.js';

export function open(options = {}) {
  return openComponent('modal-position', options);
}
