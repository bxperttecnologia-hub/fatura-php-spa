import {
  companyIdFromRequest,
  positiveId,
  jsonHandler,
  notImplemented,
  response,
  select,
  formatListDate,
} from './archiveModule.js';

const fetch = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const rows = await select(
    `SELECT p.id, p.company_id, ps.name AS status_invoice, ps.color, ps.text_color,
            CONCAT(YEAR(p.issue_date), '/', p.id) AS codigo, c.name AS cliente, p.issue_date, p.due_date,
            CASE WHEN p.converted_total > 0 THEN ROUND(p.converted_total, 2)
                 ELSE p.final_total END AS final_total,
            cr.symbol, cr.position, CONCAT(cr.currency, ' (', cr.iso_code, ')') AS currency
     FROM proformas p
     JOIN contact c ON c.id = p.contact_id
     JOIN currencies cr ON cr.iso_code = 'AOA'
     JOIN proforma_status ps ON ps.id = p.status
     WHERE p.company_id = :company_id
     ORDER BY p.id DESC`,
    { company_id: companyId },
  );
  return response(200, rows.map((row) => {
    const issueDate = formatListDate(row.issue_date);
    const due = issueDate && Number.isFinite(Number(row.due_date))
      ? new Date(`${issueDate}T00:00:00Z`)
      : null;
    if (due) due.setUTCDate(due.getUTCDate() + Number(row.due_date));
    return {
      ...row,
      issue_date: issueDate,
      due_date: due && !Number.isNaN(due.getTime()) ? due.toISOString().slice(0, 10) : null,
    };
  }));
});

const getLastReceipt = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, message: 'Empresa não encontrada na sessão.' });
  const invoiceId = positiveId(req.query?.invoice_id);
  if (!invoiceId) return response(422, { success: false, message: 'invoice_id é obrigatório.' });
  const rows = await select(
    `SELECT r.id, r.serie, r.number, r.pay_date, r.amount_paid
     FROM receipts r
     JOIN invoices i ON i.id = r.invoice_id
     WHERE r.invoice_id = :invoice_id AND i.company_id = :company_id
     ORDER BY r.id DESC
     LIMIT 1`,
    { invoice_id: invoiceId, company_id: companyId },
  );
  return response(200, { success: true, data: rows[0] ?? null });
});

const actions = [
  'convertProforma', 'createCreditNote', 'deleteInvoice', 'getInvoiceData', 'getInvoicesFeed',
  'getProform', 'registerPayment', 'sendInvoice', 'updateStatus', 'exportProforms',
];

export class ProformController {
  static handlers = {
    fetchProforms: fetch,
    getLastReceipt,
    ...Object.fromEntries(actions.map((action) => [
      action,
      notImplemented('proform', action, {
        requiresId: !['getInvoicesFeed', 'getLastReceipt'].includes(action),
      }),
    ])),
    proformPublic: notImplemented('proform', 'proformPublic', { publicAccess: true, requiresId: true }),
    receiptPdf: notImplemented('proform', 'receiptPdf', { requiresId: true }),
  };
}
