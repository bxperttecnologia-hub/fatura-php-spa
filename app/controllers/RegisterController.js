import { buildArchiveHandlers, sixDigitCodeField } from '../services/archiveMigrationService.js';

const required = (name) => ({ name });
const registerAction = (action, legacyName, fields = [], extra = {}) => ({
  action,
  method: 'POST',
  apiPath: `/${action.replaceAll('_', '-')}`,
  legacyPath: `/ajax/${legacyName}.php`,
  access: 'public',
  required: fields,
  ...extra,
});

export const registerSpecs = [
  registerAction('debug_conexao', 'debug_conexao', [], {
    method: 'GET',
    access: 'admin',
    diagnostic: true,
  }),
  registerAction('process_register', 'process_register', [required('name'), required('email'), required('password')]),
  registerAction('send_otp', 'send_otp', [required('name'), required('phone')]),
  registerAction('set_password', 'set_password', [required('password'), required('password_confirm')], { access: 'authenticated' }),
  registerAction('verifica_username', 'verifica_username', [required('username')]),
  registerAction('verify_otp', 'verify_otp', [sixDigitCodeField]),
];

export class RegisterController {
  static handlers = buildArchiveHandlers('register', registerSpecs);
}
