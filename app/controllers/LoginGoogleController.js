import { buildArchiveHandlers, oauthCodeField } from '../services/archiveMigrationService.js';

const googleBase = (action, filename, method, required) => ({
  action,
  method,
  apiPath: `/${action.replaceAll('_', '-')}`,
  legacyPath: `/${filename}.php`,
  access: 'public',
  required,
});

export const loginGoogleSpecs = [
  googleBase('login_google', 'loginGoogle', 'GET', []),
  googleBase('process_login_google', 'processLoginGoogle', 'GET', [oauthCodeField]),
  googleBase('callback', 'callback', 'GET', [oauthCodeField]),
  googleBase('verify_user', 'verificarUsuario', 'GET', [oauthCodeField]),
];

export class LoginGoogleController {
  static handlers = buildArchiveHandlers('loginGoogle', loginGoogleSpecs);
}
