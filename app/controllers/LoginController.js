import { buildArchiveHandlers, sixDigitCodeField } from '../services/archiveMigrationService.js';

const required = (name) => ({ name });
const publicAction = (action, legacyName, fields = [], extra = {}) => ({
  action,
  method: 'POST',
  apiPath: `/${action.replaceAll('_', '-')}`,
  legacyPath: `/ajax/${legacyName}.php`,
  access: 'public',
  required: fields,
  ...extra,
});

export const loginSpecs = [
  publicAction('process_login', 'process_login', [required('user_email'), required('password')]),
  publicAction('forgot_send', 'forgot_send', [required('method'), required('identifier')]),
  publicAction('forgot_verify', 'forgot_verify', [sixDigitCodeField]),
  publicAction('forgot_reset', 'forgot_reset', [required('password'), required('password_confirm'), sixDigitCodeField]),
  publicAction('notify', 'notify', [required('code'), required('destination')]),
];

export class LoginController {
  static handlers = buildArchiveHandlers('login', loginSpecs);
}
