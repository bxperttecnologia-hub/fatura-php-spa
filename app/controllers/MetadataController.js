import { QueryTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

const userIdFor = (req) => {
  const id = Number(req.session?.user?.id ?? req.session?.userId);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};

const respond = (handler) => async (req, res, next) => {
  try {
    const result = await handler(req);
    return res.status(result.status).json(result.body);
  } catch (error) {
    return next(error);
  }
};

const countries = async (req) => {
  if (!userIdFor(req)) {
    return { status: 401, body: { success: false, message: 'Sessão inválida.' } };
  }

  const rows = await sequelize.query(
    'SELECT phone, name FROM countries ORDER BY name',
    { type: QueryTypes.SELECT },
  );
  return { status: 200, body: rows };
};

const currencies = async (req) => {
  if (!userIdFor(req)) {
    return { status: 401, body: { success: false, message: 'Sessão inválida.' } };
  }

  const rows = await sequelize.query(
    'SELECT iso_code, currency, symbol, position FROM currencies ORDER BY currency',
    { type: QueryTypes.SELECT },
  );
  return { status: 200, body: rows };
};

const currency = async (req) => {
  const userId = userIdFor(req);
  if (!userId) {
    return { status: 401, body: { success: false, message: 'Sessão inválida.' } };
  }

  const rows = await sequelize.query(
    `SELECT COALESCE(cur.iso_code, co.currency) AS currency, cur.symbol, cur.position
       FROM company_has_user chu
       JOIN companies co ON co.id = chu.company_id
       LEFT JOIN currencies cur ON cur.iso_code = co.currency
      WHERE chu.user_id = :userId
      ORDER BY CASE WHEN chu.role = 'owner' THEN 0 ELSE 1 END
      LIMIT 1`,
    { replacements: { userId }, type: QueryTypes.SELECT },
  );
  if (!rows.length) {
    return { status: 404, body: { success: false, message: 'Moeda da empresa não encontrada.' } };
  }
  return { status: 200, body: rows[0] };
};

const numberCopies = async (req) => {
  if (!userIdFor(req)) {
    return { status: 401, body: { success: false, message: 'Sessão inválida.' } };
  }

  return {
    status: 200,
    body: [
      { value: 1, label: 'Original', translationKey: 'Original' },
      { value: 2, label: 'Em duplicado', translationKey: 'Em duplicado' },
      { value: 3, label: 'Em triplicado', translationKey: 'Em triplicado' },
    ],
  };
};

export class MetadataController {
  static handlers = {
    countries: respond(countries),
    currencies: respond(currencies),
    currency: respond(currency),
    numberCopies: respond(numberCopies),
  };
}
