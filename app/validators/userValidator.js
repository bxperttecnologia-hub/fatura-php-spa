import { body, param } from 'express-validator';

export const userCreateValidator = [
  body('name').trim().isLength({ min: 2 }).withMessage('O nome deve conter pelo menos 2 caracteres.'),
  body('email').isEmail().withMessage('O email deve ser válido.'),
  body('role').optional().isIn(['super_admin', 'admin', 'user']).withMessage('Papel inválido.'),
];

export const userUpdateValidator = [
  param('id').isInt({ min: 1 }).withMessage('ID inválido.'),
  body('name').optional().trim().isLength({ min: 2 }).withMessage('O nome deve conter pelo menos 2 caracteres.'),
  body('email').optional().isEmail().withMessage('O email deve ser válido.'),
  body('role').optional().isIn(['super_admin', 'admin', 'user']).withMessage('Papel inválido.'),
];
