import bcrypt from 'bcryptjs';
import { Op, fn, col, where as sqlWhere } from 'sequelize';
import { validationResult } from 'express-validator';
import { Company, CompanyHasUser, User } from '../models/index.js';
import { pickLoginIdentifier } from '../validators/authValidator.js';
import { SESSION_REMEMBER_MAX_AGE } from '../../config/session.js';

const invalidCredentials = (res) => res.status(401).json({
  success: false,
  message: 'Utilizador ou palavra-passe inválidos.',
  errors: ['Invalid credentials'],
});

/** Mesmo comportamento do PHP: e-mail, username ou telefone (últimos 9 dígitos). */
const findUserByIdentifier = (identifier) => {
  if (identifier.includes('@')) {
    return User.findOne({ where: { email: identifier.toLowerCase() } });
  }

  const conditions = [{ username: identifier }];
  const digits = identifier.replace(/\D+/g, '');
  if (digits.length >= 9) {
    conditions.push(sqlWhere(
      fn('RIGHT', fn('REGEXP_REPLACE', col('phone'), '[^0-9]', ''), 9),
      digits.slice(-9),
    ));
  }
  return User.findOne({ where: { [Op.or]: conditions } });
};

const regenerateSession = (req, userData, rememberMe) => new Promise((resolve, reject) => {
  req.session.regenerate((regenerateError) => {
    if (regenerateError) {
      reject(regenerateError);
      return;
    }

    req.session.userId = userData.id;
    req.session.user = userData;
    if (rememberMe && req.session.cookie) {
      req.session.cookie.maxAge = SESSION_REMEMBER_MAX_AGE;
    }
    req.session.save((saveError) => {
      if (saveError) {
        reject(saveError);
        return;
      }
      resolve();
    });
  });
});

const requireActiveUsers = () => ['1', 'true', 'yes'].includes(String(process.env.AUTH_REQUIRE_ACTIVE || '').toLowerCase());

export class AuthController {
  static async login(req, res, next) {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(422).json({
          success: false,
          message: 'Dados de login inválidos.',
          errors: errors.array().map((error) => error.msg),
        });
      }

      const identifier = pickLoginIdentifier(req.body);
      const { password } = req.body;
      const rememberMe = [true, 1, '1', 'true', 'on'].includes(req.body.remember_me);
      const user = await findUserByIdentifier(identifier);

      if (!user) {
        return invalidCredentials(res);
      }

      const validPassword = await bcrypt.compare(String(password), user.password);
      const isBlocked = Number(user.blocked) === 1;
      // users.is_active fica a 0 nos registos criados pelo fluxo PHP (register/OTP) e a versão
      // PHP nunca o verificava no login; só é exigido com AUTH_REQUIRE_ACTIVE=true.
      const inactiveRejected = requireActiveUsers() && Number(user.is_active) !== 1;
      if (!validPassword || isBlocked || inactiveRejected) {
        return invalidCredentials(res);
      }

      const membership = await CompanyHasUser.findOne({
        where: { user_id: user.id },
        order: [['created_at', 'ASC'], ['id', 'ASC']],
      });
      const company = membership
        ? await Company.findByPk(membership.company_id, { attributes: ['id', 'name', 'registration_number', 'email'] })
        : null;

      const userData = {
        id: user.id,
        name: user.name,
        email: user.email,
        ...(user.username != null ? { username: user.username } : {}),
        ...(user.phone != null ? { phone: user.phone } : {}),
        ...(user.address != null ? { address: user.address } : {}),
        ...(user.country != null ? { country: user.country } : {}),
        ...(user.address_district != null ? { address_district: user.address_district } : {}),
        ...(user.image != null ? { image: user.image } : {}),
        role: membership?.role ?? (user.adm ? 'admin' : null),
        lang: user.lang,
        ...(user.currency != null ? { currency: user.currency } : {}),
        ...(Number(user.must_change_password) === 1 ? { must_change_password: true } : {}),
        company_id: membership?.company_id ?? null,
        ...(company ? {
          companyName: company.name,
          company_name: company.name,
          name_company: company.name,
          registration_number: company.registration_number,
          email_company: company.email,
        } : {}),
      };
      await regenerateSession(req, userData, rememberMe);

      return res.status(200).json({
        success: true,
        message: 'Login realizado com sucesso.',
        data: {
          user: userData,
        },
      });
    } catch (error) {
      next(error);
      return null;
    }
  }

  static async logout(req, res) {
    req.session.destroy((error) => {
      if (error) {
        return res.status(500).json({
          success: false,
          message: 'Não foi possível encerrar a sessão.',
          errors: [error.message],
        });
      }

      res.clearCookie(process.env.SESSION_NAME || 'dashboard.sid');
      return res.status(200).json({
        success: true,
        message: 'Sessão encerrada com sucesso.',
        data: null,
      });
    });
  }

  static async me(req, res) {
    const user = req.session.user;

    if (!user) {
      return res.status(401).json({
        success: false,
        message: 'Não autenticado.',
        errors: ['Unauthorized'],
      });
    }

    return res.status(200).json({
      success: true,
      message: 'Dados do utilizador carregados.',
      data: { user },
    });
  }
}
