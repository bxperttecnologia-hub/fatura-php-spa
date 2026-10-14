import { QueryTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

const response = (status, body) => ({ status, body });
const companyIdFor = (req) => {
  const id = Number(req.session?.user?.company_id ?? req.session?.user?.companyId);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const number = (value, fallback = 0) => {
  if (value == null || value === '') return fallback;
  const parsed = Number(String(value).replace(',', '.'));
  return Number.isFinite(parsed) ? parsed : null;
};
const respond = (handler) => async (req, res, next) => {
  try {
    const result = await handler(req);
    return res.status(result.status).json(result.body);
  } catch (error) {
    return next(error);
  }
};
const list = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return response(401, { success: false, message: 'Sessão inválida.' });
  const items = await sequelize.query(
    `SELECT i.id, i.company_id, i.code, i.name, i.description, i.category, i.brand,
            i.barcode, i.sku, i.item_type, i.unit_measure, i.retention, i.unit_price,
            i.tax, COALESCE(i.cost_price, 0) AS cost_price,
            COALESCE(i.sale_price, 0) AS sale_price, COALESCE(i.pvp, 0) AS pvp,
            i.track_stock, i.global_min_stock, i.status, i.currency,
            si.stock_id, COALESCE(si.quantity, 0) AS quantity,
            COALESCE(si.min_quantity, i.global_min_stock, 1) AS min_quantity
       FROM items i
       LEFT JOIN stock_items si ON si.item_id = i.id
      WHERE i.company_id = :companyId AND i.status <> 'archived'
      ORDER BY i.id DESC`,
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  return response(200, { success: true, total: items.length, data: items });
};

const generateCode = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return response(401, { success: false, message: 'Sessão inválida.' });
  const typeMap = {
    product: 'PROD', service: 'SRV', raw_material: 'RAW',
    finished_good: 'FIN', consumable: 'CON',
  };
  const prefix = typeMap[String(req.query.item_type ?? '')] ?? 'ITM';
  const stockId = positiveId(req.query.stock_id);
  let baseCode = `${companyId}-${prefix}`;
  if (prefix === 'PROD' && stockId) {
    const stocks = await sequelize.query(
      'SELECT id FROM stocks WHERE id = :stockId AND company_id = :companyId LIMIT 1',
      { replacements: { stockId, companyId }, type: QueryTypes.SELECT },
    );
    if (stocks.length) baseCode = `STK${String(stockId).padStart(2, '0')}-${prefix}`;
  }
  const last = await sequelize.query(
    `SELECT code FROM items
      WHERE company_id = :companyId AND code LIKE :prefix
      ORDER BY id DESC LIMIT 1`,
    { replacements: { companyId, prefix: `${baseCode}-%` }, type: QueryTypes.SELECT },
  );
  const lastSequence = Number.parseInt(String(last[0]?.code ?? '').split('-').at(-1), 10) || 0;
  const sequence = String(lastSequence + 1).padStart(6, '0');
  return response(200, {
    success: true,
    generated_code: `${baseCode}-${sequence}`,
    type_used: prefix,
    sequence,
  });
};

const checkCode = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return response(401, { success: false, message: 'Sessão inválida.' });
  const code = String(req.body?.codigo ?? req.body?.code ?? '').trim();
  if (!code) return response(422, { success: false, message: 'Código não informado.' });
  const rows = await sequelize.query(
    'SELECT id FROM items WHERE code = :code AND company_id = :companyId LIMIT 1',
    { replacements: { code, companyId }, type: QueryTypes.SELECT },
  );
  return response(200, { success: true, exists: rows.length > 0 });
};

const save = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return response(401, { success: false, message: 'Sessão inválida.' });
  const body = req.body ?? {};
  const id = positiveId(body.product_id ?? body.item_id ?? body.id);
  const itemCompanyId = positiveId(body.id_company ?? body.company_id) ?? companyId;
  const name = String(body.name ?? '').trim();
  const code = String(body.codigo ?? body.code ?? '').trim();
  const price = number(body.unit_price);
  const cost = number(body.cost_price);
  const sale = number(body.sale_price);
  const pvp = number(body.pvp);
  const retention = number(body.retention, 0);
  const quantity = number(body.quantidade ?? body.quantity, 0);
  const minimum = number(body.min_stock ?? body.min_quantity, 1);
  const tax = String(body.tax_vat ?? body.tax ?? '0').replace('%', '').replace(',', '.');
  const allowedTypes = ['product', 'service', 'raw_material', 'finished_good', 'consumable'];
  const itemType = String(body.item_type ?? 'product');
  const measures = ['unit', 'kg', 'g', 'liter', 'meter', 'box', 'pack', 'service'];
  const measure = String(body.unit_measure ?? 'unit');

  if (itemCompanyId !== companyId) return response(422, { success: false, message: 'Empresa inválida.' });
  if (!name || !code) return response(422, { success: false, message: 'Nome e código são obrigatórios.' });
  if ([price, cost, sale, pvp, retention, quantity, minimum].some((v) => v == null || v < 0)) {
    return response(422, { success: false, message: 'Preço, quantidade ou retenção inválidos.' });
  }
  if (!allowedTypes.includes(itemType) || !measures.includes(measure) || !['0', '5', '7', '14'].includes(tax)) {
    return response(422, { success: false, message: 'Tipo, unidade ou imposto inválido.' });
  }

  const stockId = positiveId(body.stock_id);
  const values = {
    companyId, code, name, description: String(body.descricao ?? body.description ?? ''),
    itemType, measure, category: body.subcategory ?? body.category ?? null,
    currency: String(body.currency ?? 'AOA'), tax, retention, price, cost, sale, pvp,
  };

  if (id) {
    const [, affectedRows] = await sequelize.query(
      `UPDATE items SET code=:code, name=:name, description=:description, item_type=:itemType,
        unit_measure=:measure, category=:category, currency=:currency, tax=:tax, retention=:retention,
        unit_price=:price, cost_price=:cost, sale_price=:sale, pvp=:pvp, updated_at=NOW()
       WHERE id=:id AND company_id=:companyId`,
      { replacements: { ...values, id }, type: QueryTypes.UPDATE },
    );
    if (!affectedRows) {
      const found = await sequelize.query(
        'SELECT id FROM items WHERE id=:id AND company_id=:companyId LIMIT 1',
        { replacements: { id, companyId }, type: QueryTypes.SELECT },
      );
      if (!found.length) return response(404, { success: false, message: 'Item não encontrado.' });
    }
    if (stockId) {
      await sequelize.query(
        `INSERT INTO stock_items (stock_id, item_id, quantity, min_quantity)
         VALUES (:stockId, :id, :quantity, :minimum)
         ON DUPLICATE KEY UPDATE stock_id=VALUES(stock_id), quantity=VALUES(quantity),
         min_quantity=VALUES(min_quantity)`,
        { replacements: { stockId, id, quantity, minimum }, type: QueryTypes.INSERT },
      );
    }
    return response(200, { success: true, message: 'Item atualizado com sucesso.', item_id: id });
  }

  const [insertId] = await sequelize.query(
    `INSERT INTO items
      (company_id, code, name, description, item_type, unit_measure, category, currency, tax,
       retention, unit_price, cost_price, sale_price, pvp, track_stock, global_min_stock, status,
       created_at, updated_at)
     VALUES
      (:companyId, :code, :name, :description, :itemType, :measure, :category, :currency, :tax,
       :retention, :price, :cost, :sale, :pvp, 1, :minimum, 'active', NOW(), NOW())`,
    { replacements: { ...values, minimum }, type: QueryTypes.INSERT },
  );
  const itemId = Number(insertId);
  if (stockId) {
    await sequelize.query(
      `INSERT INTO stock_items (stock_id, item_id, quantity, min_quantity)
       VALUES (:stockId, :itemId, :quantity, :minimum)`,
      { replacements: { stockId, itemId, quantity, minimum }, type: QueryTypes.INSERT },
    );
  }
  return response(201, { success: true, message: 'Item criado com sucesso.', item_id: itemId });
};

const deleteItem = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return response(401, { success: false, message: 'Sessão inválida.' });
  const id = positiveId(req.body?.id ?? req.body?.item_id);
  if (!id) return response(422, { success: false, message: 'ID inválido.' });
  const item = await sequelize.query(
    'SELECT id FROM items WHERE id=:id AND company_id=:companyId LIMIT 1',
    { replacements: { id, companyId }, type: QueryTypes.SELECT },
  );
  if (!item.length) return response(404, { success: false, message: 'Item não encontrado.' });
  const references = await sequelize.query(
    `SELECT (SELECT COUNT(*) FROM proforma_items WHERE item_id=:id) +
            (SELECT COUNT(*) FROM invoice_items WHERE item_id=:id) AS total`,
    { replacements: { id }, type: QueryTypes.SELECT },
  );
  if (Number(references[0]?.total) > 0) {
    return response(422, { success: false, message: 'Item associado a documentos e não pode ser eliminado.' });
  }
  await sequelize.transaction(async (transaction) => {
    await sequelize.query('DELETE FROM stock_items WHERE item_id=:id', { replacements: { id }, transaction });
    await sequelize.query('DELETE FROM items WHERE id=:id AND company_id=:companyId', {
      replacements: { id, companyId }, transaction,
    });
  });
  return response(200, { success: true, message: 'Item removido com sucesso.' });
};

const inlineUpdate = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return response(401, { success: false, message: 'Sessão inválida.' });
  const rows = req.body?.items;
  let items = rows;
  if (typeof items === 'string') {
    try { items = JSON.parse(items); } catch { items = null; }
  }
  if (!Array.isArray(items) || items.length === 0) {
    return response(422, { success: false, message: 'Nenhum item enviado.' });
  }
  const updated = [];
  const skipped = [];
  for (const item of items) {
    const id = positiveId(item?.id);
    const name = String(item?.name ?? '').trim();
    const price = number(item?.price ?? item?.unit_price);
    const tax = number(item?.tax);
    const quantity = number(item?.quantity, 0);
    const minimum = number(item?.min_quantity, 1);
    const pvp = number(item?.pvp);
    if (!id || !name || [price, tax, quantity, minimum, pvp].some((v) => v == null || v < 0)) {
      skipped.push(id);
      continue;
    }
    const owned = await sequelize.query(
      'SELECT id FROM items WHERE id=:id AND company_id=:companyId LIMIT 1',
      { replacements: { id, companyId }, type: QueryTypes.SELECT },
    );
    if (!owned.length) {
      skipped.push(id);
      continue;
    }
    await sequelize.transaction(async (transaction) => {
      await sequelize.query(
        `UPDATE items SET name=:name, unit_price=:price, tax=:tax, pvp=:pvp, updated_at=NOW()
         WHERE id=:id AND company_id=:companyId`,
        { replacements: { id, name, price, tax, pvp, companyId }, transaction },
      );
      await sequelize.query(
        `UPDATE stock_items si JOIN items i ON i.id=si.item_id
           SET si.quantity=:quantity, si.min_quantity=:minimum
         WHERE si.item_id=:id AND i.company_id=:companyId`,
        { replacements: { id, quantity, minimum, companyId }, transaction },
      );
    });
    updated.push({ id, name });
  }
  return response(200, { success: true, updated: updated.length, skipped, items: updated });
};

const removeBulk = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return response(401, { success: false, message: 'Sessão inválida.' });
  const ids = req.body?.ids;
  if (!Array.isArray(ids) || !ids.length || ids.some((id) => !positiveId(id))) {
    return response(422, { success: false, message: 'Nenhum item válido selecionado.' });
  }
  const deleted = [];
  const blocked = [];
  for (const value of [...new Set(ids.map(Number))]) {
    const result = await deleteItem({ ...req, body: { id: value } });
    if (result.status === 200) deleted.push(value);
    else blocked.push({ id: value, reason: result.body.message });
  }
  return response(200, {
    success: deleted.length > 0,
    message: `${deleted.length} item(s) removidos com sucesso!`,
    deleted,
    blocked,
  });
};

const exportItems = async (req, res, next) => {
  try {
    const companyId = companyIdFor(req);
    if (!companyId) {
      return res.status(401).json({ success: false, message: 'Sessão inválida.' });
    }
    const type = String(req.query.type ?? 'xlsx');
    if (!['xlsx', 'pdf', 'csv'].includes(type)) {
      return res.status(422).json({ success: false, message: 'Formato de exportação inválido.' });
    }
    if (type !== 'csv') {
      return res.status(501).json({
        success: false,
        message: `Exportação ${type.toUpperCase()} ainda não foi migrada: dependências de geração indisponíveis.`,
        todo: true,
      });
    }
    const items = await sequelize.query(
      `SELECT i.id, i.code, i.name, i.item_type, i.category, i.unit_measure,
              i.unit_price, i.pvp, COALESCE(si.quantity, 0) AS quantity,
              i.status, i.currency
         FROM items i LEFT JOIN stock_items si ON si.item_id=i.id
        WHERE i.company_id=:companyId ORDER BY i.id`,
      { replacements: { companyId }, type: QueryTypes.SELECT },
    );
    const headers = ['ID', 'Código', 'Nome', 'Tipo', 'Categoria', 'Unidade', 'Preço', 'PVP', 'Quantidade', 'Estado', 'Moeda'];
    const csvCell = (value) => {
      let text = String(value ?? '');
      if (/^[=+\-@]/.test(text)) text = `'${text}`;
      return `"${text.replaceAll('"', '""')}"`;
    };
    const csv = `\uFEFF${[
      headers.map(csvCell).join(';'),
      ...items.map((item) => [
        item.id, item.code, item.name, item.item_type, item.category, item.unit_measure,
        Number(item.unit_price ?? 0).toFixed(2), Number(item.pvp ?? 0).toFixed(2),
        item.quantity, item.status, item.currency,
      ].map(csvCell).join(';')),
    ].join('\r\n')}`;
    res.set({
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': 'attachment; filename="produtos.csv"',
      'Cache-Control': 'no-store',
    });
    return res.status(200).send(csv);
  } catch (error) {
    return next(error);
  }
};

const handlers = {
  list: respond(list),
  generateCode: respond(generateCode),
  checkCode: respond(checkCode),
  save: respond(save),
  edit: respond(save),
  delete: respond(deleteItem),
  deleteBulk: respond(removeBulk),
  inlineUpdate: respond(inlineUpdate),
  export: exportItems,
};

export class ItemsController {
  static handlers = handlers;
}
