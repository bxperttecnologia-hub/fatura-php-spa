import {
  companyIdFromRequest,
  idFromRequest,
  jsonHandler,
  notImplemented,
  positiveId,
  response,
  select,
  formatListDate,
  sanitizePlainText,
  userIdFromRequest,
} from './archiveModule.js';

const listGuides = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, message: 'Empresa não encontrada na sessão.' });
  const rows = await select(
    `SELECT g.*, c.name AS client_name
     FROM guides g LEFT JOIN contact c ON c.id = g.contact_id
     WHERE c.company_id = :company_id
     ORDER BY g.id DESC`,
    { company_id: companyId },
  );
  return response(200, rows.map((guide) => ({
    ...guide,
    cargo_date: formatListDate(guide.cargo_date),
    client_name: guide.client_name ?? '-',
  })));
});

const getGuide = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, message: 'Empresa não encontrada na sessão.' });
  const id = positiveId(idFromRequest(req));
  if (!id) return response(422, { success: false, error: 'Guia inválida.' });
  const rows = await select(
    `SELECT g.id, g.series, g.document_date, g.cargo_date, g.final_total, g.currency,
            c.name AS client_name, c.email AS client_email, comp.name AS company_name,
            comp.address AS company_address, comp.city AS company_city, comp.country AS company_country,
            comp.phone AS company_phone, cr.symbol AS money_symbol, cr.position AS money_position
     FROM guides g
     JOIN contact c ON c.id = g.contact_id
     JOIN companies comp ON comp.id = c.company_id
     LEFT JOIN currencies cr ON cr.iso_code = g.currency
     WHERE g.id = :id AND comp.id = :company_id
     LIMIT 1`,
    { id, company_id: companyId },
  );
  return rows[0]
    ? response(200, { success: true, data: rows[0] })
    : response(404, { success: false, error: 'Guia não encontrada.' });
});

const getItems = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  const userId = userIdFromRequest(req);
  if (!companyId || !userId) {
    return response(401, { success: false, message: 'Sessão expirada. Faça login novamente.' });
  }
  const rows = await select(
    `SELECT i.id, i.code, i.description, i.unit_price, i.tax
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

const actions = {
  generatePdf: notImplemented('guides', 'generatePdf', { requiresId: true }),
  saveGuide: notImplemented('guides', 'saveGuide'),
  sendGuide: notImplemented('guides', 'sendGuide', { requiresId: true }),
  guidePublic: notImplemented('guides', 'guidePublic', { publicAccess: true, requiresId: true }),
};

export class GuidesController {
  static handlers = {
    ...actions,
    listGuides,
    getGuide,
    getItems,
  };
}
