import { QueryTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

const result = (status, body) => ({ status, body });
const respond = (handler) => async (req, res, next) => {
  try {
    const output = await handler(req);
    return res.status(output.status).json(output.body);
  } catch (error) {
    return next(error);
  }
};
const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const updateProfile = async (req) => {
  const userId = positiveId(req.session?.user?.id ?? req.session?.userId);
  if (!userId) return result(401, { status: 'error', message: 'Sessão expirada. Faça login novamente.' });
  const allowed = ['username', 'name', 'phone', 'email', 'address', 'country', 'lang'];
  const values = {};
  for (const field of allowed) {
    const value = req.body?.[field];
    if (typeof value === 'string' || typeof value === 'number') {
      const clean = String(value).trim();
      if (clean) values[field] = clean;
    }
  }
  if (values.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) {
    return result(422, { status: 'error', message: 'Email inválido.' });
  }
  if (req.session?.user?.image) values.image = req.session.user.image;
  const entries = Object.entries(values);
  if (!entries.length) return result(422, { status: 'error', message: 'Nenhum dado válido para atualizar.' });

  const replacements = { userId };
  const assignments = entries.map(([key, value], index) => {
    const param = `value${index}`;
    replacements[param] = value;
    return `\`${key}\`=:${param}`;
  });
  const [, affectedRows] = await sequelize.query(
    `UPDATE users SET ${assignments.join(', ')} WHERE id=:userId`,
    { replacements, type: QueryTypes.UPDATE },
  );
  for (const [key, value] of entries) {
    if (key !== 'image' && req.session.user) req.session.user[key] = value;
  }
  return result(200, { status: 'success', rowsAffected: Number(affectedRows ?? 0) });
};

const upload = async (req) => {
  if (!positiveId(req.session?.user?.id ?? req.session?.userId)) {
    return result(401, { success: false, message: 'Sessão expirada. Faça login novamente.' });
  }
  return result(501, {
    success: false,
    message: 'Upload de imagem ainda não foi migrado: o projeto não tem middleware de upload configurado.',
    todo: true,
  });
};

export class PerfilController {
  static handlers = {
    updateProfile: respond(updateProfile),
    upload: respond(upload),
  };
}
