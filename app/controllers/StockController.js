import { QueryTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

const result = (status, body) => ({ status, body });
const companyIdFor = (req) => {
  const id = Number(req.session?.user?.company_id ?? req.session?.user?.companyId);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const respond = (handler) => async (req, res, next) => {
  try {
    const output = await handler(req);
    return res.status(output.status).json(output.body);
  } catch (error) {
    return next(error);
  }
};
const todo = (name) => result(501, {
  success: false,
  message: `${name} ainda não foi migrado com fidelidade.`,
  todo: true,
});
const protectedTodo = (name) => respond(async (req) => companyIdFor(req)
  ? todo(name)
  : result(401, { success: false, message: 'Empresa não identificada.' }));
const requiredIdTodo = (name, ...keys) => respond(async (req) => {
  if (!companyIdFor(req)) return result(401, { success: false, message: 'Empresa não identificada.' });
  if (!keys.some((key) => positiveId(req.body?.[key] ?? req.query?.[key]))) {
    return result(422, { success: false, message: 'ID inválido.' });
  }
  return todo(name);
});

const list = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return result(401, { success: false, message: 'Empresa não identificada.' });
  const stocks = await sequelize.query(
    `SELECT s.id, s.name, s.description, s.icon, s.address, s.address_number, s.city,
            s.state, s.country, s.location_detail, s.color,
            COUNT(DISTINCT si.item_id) AS total_items,
            COALESCE(SUM(si.quantity), 0) AS total_quantity,
            COALESCE(SUM(i.unit_price * si.quantity), 0) AS total_stock_value,
            COUNT(DISTINCT CASE WHEN si.quantity <= si.min_quantity THEN si.item_id END) AS low_stock_items
       FROM stocks s
       LEFT JOIN stock_items si ON si.stock_id=s.id
       LEFT JOIN items i ON i.id=si.item_id AND i.company_id=s.company_id
      WHERE s.company_id=:companyId
      GROUP BY s.id ORDER BY s.id`,
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  return result(200, { success: true, stocks });
};

const get = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return result(401, { success: false, message: 'Empresa não identificada.' });
  const id = positiveId(req.query.id ?? req.body?.id);
  if (!id) return result(422, { success: false, message: 'ID inválido.' });
  const rows = await sequelize.query(
    'SELECT * FROM stocks WHERE id=:id AND company_id=:companyId LIMIT 1',
    { replacements: { id, companyId }, type: QueryTypes.SELECT },
  );
  if (!rows.length) return result(404, { success: false, message: 'Stock não encontrado.' });
  return result(200, { success: true, stock: rows[0] });
};

const update = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return result(401, { success: false, message: 'Empresa não identificada.' });
  const id = positiveId(req.body?.id);
  const name = String(req.body?.name ?? '').trim();
  if (!id || !name) return result(422, { success: false, message: 'ID e nome são obrigatórios.' });
  const existing = await sequelize.query(
    'SELECT id FROM stocks WHERE id=:id AND company_id=:companyId LIMIT 1',
    { replacements: { id, companyId }, type: QueryTypes.SELECT },
  );
  if (!existing.length) return result(404, { success: false, message: 'Stock não encontrado.' });
  await sequelize.query(
    `UPDATE stocks SET name=:name, description=:description, color=:color, icon=:icon,
       address=:address, address_number=:addressNumber, latitude=:latitude, longitude=:longitude,
       updated_by=:updatedBy, updated_at=NOW()
     WHERE id=:id AND company_id=:companyId`,
    {
      replacements: {
        id, companyId, name, description: req.body.description ?? '',
        color: req.body.color ?? '#4e73df', icon: req.body.icon ?? 'box',
        address: req.body.address ?? null, addressNumber: req.body.address_number ?? '',
        latitude: req.body.latitude ?? null, longitude: req.body.longitude ?? null,
        updatedBy: req.session.user.name ?? 'Sistema',
      },
      type: QueryTypes.UPDATE,
    },
  );
  return result(200, { success: true });
};

const handlers = {
  list: respond(list),
  get: respond(get),
  update: respond(update),
  companyLogo: respond(async (req) => {
    const companyId = companyIdFor(req);
    if (!companyId) return result(401, { success: false, message: 'Empresa não identificada.' });
    const rows = await sequelize.query(
      'SELECT id, name, logo_url FROM companies WHERE id=:companyId LIMIT 1',
      { replacements: { companyId }, type: QueryTypes.SELECT },
    );
    if (!rows.length) return result(404, { success: false, message: 'Empresa não encontrada.' });
    return result(200, { success: true, company: rows[0] });
  }),
  create: respond(async (req) => {
    if (!companyIdFor(req)) return result(401, { success: false, message: 'Empresa não identificada.' });
    if (!String(req.body?.name ?? '').trim()) {
      return result(422, { success: false, message: 'Nome do stock é obrigatório.' });
    }
    return todo('Criação de stock (campos fiscais/localização obrigatórios)');
  }),
  delete: requiredIdTodo('Eliminação de stock', 'id', 'stock_id'),
  verifyEmpty: requiredIdTodo('Verificação de stock vazio', 'id', 'stock_id'),
  listTransfer: protectedTodo('Listagem de stock para transferência'),
  saveItems: respond(async (req) => {
    if (!companyIdFor(req)) return result(401, { success: false, message: 'Empresa não identificada.' });
    if (!positiveId(req.body?.stock_id ?? req.query?.stock_id)) {
      return result(422, { success: false, message: 'ID do stock inválido.' });
    }
    return todo('Gestão legada de itens de stock: esquema PHP não corresponde a stock_items');
  }),
  deleteItem: respond(async (req) => {
    if (!companyIdFor(req)) return result(401, { success: false, message: 'Empresa não identificada.' });
    if (!positiveId(req.body?.id) || !positiveId(req.body?.stock_id)) {
      return result(422, { success: false, message: 'IDs inválidos.' });
    }
    return todo('Eliminação legada de item de stock: esquema PHP não corresponde a stock_items');
  }),
  moveItem: respond(async (req) => {
    if (!companyIdFor(req)) return result(401, { success: false, message: 'Empresa não identificada.' });
    if (!positiveId(req.body?.item_id) || !positiveId(req.body?.from_stock)
      || !positiveId(req.body?.to_stock) || !positiveId(req.body?.quantidade)) {
      return result(422, { success: false, message: 'Dados inválidos.' });
    }
    return todo('Transferência legada de itens de stock: esquema PHP não corresponde a stock_items');
  }),
};

export class StockController {
  static handlers = handlers;
}
