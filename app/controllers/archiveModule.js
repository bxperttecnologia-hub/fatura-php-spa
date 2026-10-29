import { QueryTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const response = (statusCode, payload) => ({ statusCode, payload });

export const companyIdFromRequest = (req) => {
  const value = Number(req.session?.user?.company_id);
  return Number.isSafeInteger(value) && value > 0 ? value : null;
};

export const userIdFromRequest = (req) => {
  const value = Number(req.session?.user?.id);
  return Number.isSafeInteger(value) && value > 0 ? value : null;
};

export const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};

export const idFromRequest = (req) => req.params?.id
  ?? req.body?.id
  ?? req.body?.invoice_id
  ?? req.body?.guide_id
  ?? req.body?.credit_note_id
  ?? req.query?.id
  ?? req.query?.invoice_id
  ?? req.query?.guide_id
  ?? req.query?.credit_note_id;

export const jsonHandler = (handler) => async (req, res, next) => {
  try {
    const { statusCode, payload } = await handler(req);
    return res.status(statusCode).json(payload);
  } catch (error) {
    return next(error);
  }
};

export const requireCompany = (req) => {
  const companyId = companyIdFromRequest(req);
  return companyId
    ? { companyId }
    : {
      statusCode: 401,
      payload: { success: false, message: 'Empresa não encontrada na sessão.' },
    };
};

export const notImplemented = (
  moduleName,
  operation,
  { publicAccess = false, requiresId = false, requiresBody = false } = {},
) => (
  jsonHandler(async (req) => {
    if (!publicAccess) {
      const auth = requireCompany(req);
      if (auth.statusCode) return response(auth.statusCode, auth.payload);
    } else {
      const token = String(req.query?.token ?? req.params?.token ?? '');
      if (!/^[A-Za-z0-9._~-]{16,256}$/.test(token)) {
        return response(422, { success: false, message: 'Token público inválido.' });
      }
    }
    if (requiresId && !positiveId(idFromRequest(req))) {
      return response(422, { success: false, message: 'ID inválido.' });
    }
    if (requiresBody && (!req.body || typeof req.body !== 'object' || Array.isArray(req.body)
      || Object.keys(req.body).length === 0)) {
      return response(422, { success: false, message: 'Dados obrigatórios não enviados.' });
    }
    return response(501, {
      success: false,
      message: `${moduleName}.${operation} ainda não foi migrado.`,
      todo: true,
    });
  })
);

export const select = (sql, replacements) => sequelize.query(sql, {
  replacements,
  type: QueryTypes.SELECT,
});

export const sanitizePlainText = (value) => String(value ?? '')
  .replace(/<[^>]*>/g, ' ')
  .replace(/\s+/g, ' ')
  .trim();

export const formatListDate = (value) => {
  if (!value) return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date.toISOString().slice(0, 10);
};
