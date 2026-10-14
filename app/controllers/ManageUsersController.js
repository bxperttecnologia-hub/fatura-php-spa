import { QueryTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

const result = (status, body) => ({ status, body });
const userFor = (req) => req.session?.user;
const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const respond = (handler) => async (req, res, next) => {
  try {
    const value = await handler(req);
    return res.status(value.status).json(value.body);
  } catch (error) {
    return next(error);
  }
};
const todo = (name) => result(501, {
  success: false, message: `${name} ainda não foi migrado com fidelidade.`, todo: true,
});

const adminRole = async (userId, companyId) => {
  const rows = await sequelize.query(
    'SELECT role FROM company_has_user WHERE user_id=:userId AND company_id=:companyId LIMIT 1',
    { replacements: { userId, companyId }, type: QueryTypes.SELECT },
  );
  return rows[0]?.role ?? null;
};

const list = async (req) => {
  const requester = userFor(req);
  if (!requester?.id) return result(401, { success: false, message: 'Utilizador não autenticado.' });
  const companyId = positiveId(req.query.company_id);
  if (!companyId) return result(422, { success: false, message: 'company_id é obrigatório.' });
  const role = await adminRole(requester.id, companyId);
  if (!['owner', 'admin'].includes(role)) return result(403, { success: false, message: 'Acesso negado.' });
  const users = await sequelize.query(
    `SELECT u.id, u.name, u.username, u.email, u.is_active, u.image, c.role
       FROM users u JOIN company_has_user c ON c.user_id=u.id
      WHERE c.company_id=:companyId ORDER BY u.name`,
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  return result(200, { success: true, users });
};

const checkUser = async (req) => {
  if (!userFor(req)?.id) return result(401, { success: false, message: 'Utilizador não autenticado.' });
  const companyId = positiveId(req.query.company_id);
  const email = String(req.query.email ?? '').trim().toLowerCase();
  if (!companyId || !email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return result(422, { success: false, message: 'company_id e email válidos são obrigatórios.' });
  }
  const role = await adminRole(userFor(req).id, companyId);
  if (!['owner', 'admin'].includes(role)) return result(403, { success: false, message: 'Acesso negado.' });
  const rows = await sequelize.query(
    `SELECT u.id, u.name, u.email FROM users u
      WHERE LOWER(u.email)=:email LIMIT 1`,
    { replacements: { email }, type: QueryTypes.SELECT },
  );
  if (!rows.length) return result(404, { success: false, message: 'Utilizador não encontrado.' });
  const linked = await sequelize.query(
    'SELECT id FROM company_has_user WHERE company_id=:companyId AND user_id=:userId LIMIT 1',
    { replacements: { companyId, userId: rows[0].id }, type: QueryTypes.SELECT },
  );
  return result(200, { success: true, user: rows[0], linked: linked.length > 0 });
};

const targetAndRole = async (req) => {
  const requesterId = positiveId(userFor(req)?.id);
  if (!requesterId) return { error: result(401, { success: false, message: 'Utilizador não autenticado.' }) };
  const companyId = positiveId(req.body?.company_id);
  const targetId = positiveId(req.body?.user_id);
  if (!companyId || !targetId) {
    return { error: result(422, { success: false, message: 'Parâmetros inválidos.' }) };
  }
  const requesterRole = await adminRole(requesterId, companyId);
  if (!['owner', 'admin'].includes(requesterRole)) {
    return { error: result(403, { success: false, message: 'Acesso negado.' }) };
  }
  const targetRole = await adminRole(targetId, companyId);
  if (!targetRole) {
    return { error: result(404, { success: false, message: 'Utilizador não está vinculado a esta empresa.' }) };
  }
  if (requesterRole === 'admin' && targetRole === 'owner') {
    return { error: result(403, { success: false, message: 'Administrador não pode modificar um owner.' }) };
  }
  return { requesterId, companyId, targetId, requesterRole, targetRole };
};

const updateRole = async (req) => {
  const details = await targetAndRole(req);
  if (details.error) return details.error;
  const role = String(req.body.role ?? '').trim();
  if (!['owner', 'admin', 'employee', 'viewer'].includes(role)) {
    return result(422, { success: false, message: 'Role inválida.' });
  }
  await sequelize.query(
    `UPDATE company_has_user SET role=:role, updated_at=CURRENT_TIMESTAMP
      WHERE user_id=:targetId AND company_id=:companyId`,
    { replacements: { ...details, role }, type: QueryTypes.UPDATE },
  );
  return result(200, { success: true });
};

const unlink = async (req) => {
  const details = await targetAndRole(req);
  if (details.error) return details.error;
  await sequelize.query(
    'DELETE FROM company_has_user WHERE user_id=:targetId AND company_id=:companyId',
    { replacements: details, type: QueryTypes.DELETE },
  );
  return result(200, { success: true });
};

const handlers = {
  list: respond(list),
  checkUser: respond(checkUser),
  updateRole: respond(updateRole),
  unlink: respond(unlink),
  createCollaborator: respond(async (req) => userFor(req)?.id
    ? todo('Criação de colaborador e convite por email')
    : result(401, { success: false, message: 'Utilizador não autenticado.' })),
};

export class ManageUsersController {
  static handlers = handlers;
}
