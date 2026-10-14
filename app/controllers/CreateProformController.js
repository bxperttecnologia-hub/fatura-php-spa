import {
  companyIdFromRequest,
  jsonHandler,
  notImplemented,
  response,
  select,
  sanitizePlainText,
  userIdFromRequest,
} from './archiveModule.js';

const getItems = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  const userId = userIdFromRequest(req);
  if (!companyId || !userId) {
    return response(401, { success: false, message: 'Sessão expirada. Faça login novamente.' });
  }
  const rows = await select(
    `SELECT i.id, i.code, i.description, i.unit_price, i.tax, i.updated_at
     FROM items i
     JOIN companies c ON c.id = i.id_company
     JOIN company_has_user chu ON chu.company_id = c.id
     WHERE chu.user_id = :user_id AND c.id = :company_id`,
    { user_id: userId, company_id: companyId },
  );
  return response(200, rows.map((item) => ({
    ...item,
    description_plain: sanitizePlainText(item.description),
  })));
});

export class CreateProformController {
  static handlers = {
    getItems,
    saveInvoices: notImplemented('create_proform', 'saveInvoices', { requiresBody: true }),
    saveInvoiceLegacy: notImplemented('create_proform', 'saveInvoiceLegacy', { requiresBody: true }),
  };
}
