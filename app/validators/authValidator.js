import { body } from 'express-validator';

/**
 * O login aceita e-mail, nome de utilizador ou telefone (como a versão PHP).
 * Aceita os nomes de campo `identifier`, `user_email`, `email` e `username`.
 */
export const pickLoginIdentifier = (payload = {}) => String(
  payload.identifier ?? payload.user_email ?? payload.email ?? payload.username ?? '',
).trim();

export const loginValidator = [
  body('email').custom((_value, { req }) => {
    if (!pickLoginIdentifier(req.body)) {
      throw new Error('Indique o e-mail, utilizador ou telefone.');
    }
    return true;
  }),
  // Sem mínimo de caracteres no login: contas antigas podem ter palavras-passe curtas.
  body('password').isString().notEmpty().withMessage('Indique a palavra-passe.'),
];

export const registerValidator = [
  body('name').trim().isLength({ min: 2 }).withMessage('O nome deve ter pelo menos 2 caracteres.'),
  body('email').isEmail().withMessage('O email deve ser válido.'),
  body('password').isLength({ min: 8 }).withMessage('A palavra-passe deve ter pelo menos 8 caracteres.'),
];
