import {
  companyIdFromRequest,
  idFromRequest,
  jsonHandler,
  notImplemented,
  positiveId,
  response,
  select,
  formatListDate,
} from './archiveModule.js';

const fetch = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const invoices = await select(
    `SELECT i.id, i.company_id, ivs.name AS status_invoice, ivs.color, ivs.text_color,
            CONCAT(YEAR(i.issue_date), '/', i.id) AS codigo,
            COALESCE(c.name, 'Sem cliente') AS cliente, i.issue_date, i.due_date,
            CASE WHEN i.converted_total > 0 THEN ROUND(i.converted_total, 2)
                 ELSE ROUND(i.final_total, 2) END AS final_total,
            cr.symbol, cr.position, CONCAT(cr.currency, ' (', cr.iso_code, ')') AS currency
     FROM invoices i
     JOIN contact c ON c.id = i.contact_id
     JOIN currencies cr ON cr.iso_code = i.currency
     JOIN invoice_status ivs ON ivs.id = i.status
     WHERE i.company_id = :company_id
     ORDER BY i.id DESC`,
    { company_id: companyId },
  );
  const data = invoices.map((invoice) => {
    const issueDate = formatListDate(invoice.issue_date);
    const due = issueDate && Number.isFinite(Number(invoice.due_date))
      ? new Date(`${issueDate}T00:00:00Z`)
      : null;
    if (due) due.setUTCDate(due.getUTCDate() + Number(invoice.due_date));
    return {
      ...invoice,
      issue_date: issueDate,
      due_date: due && !Number.isNaN(due.getTime()) ? due.toISOString().slice(0, 10) : null,
    };
  });
  return response(200, data);
});

const getLastInvoice = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const rows = await select(
    'SELECT * FROM invoices WHERE company_id = :company_id ORDER BY id DESC LIMIT 1',
    { company_id: companyId },
  );
  return rows[0]
    ? response(200, { success: true, data: rows[0] })
    : response(404, { success: false, message: 'Fatura não encontrada.' });
});

const getInvoiceById = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const id = positiveId(idFromRequest(req));
  if (!id) return response(422, { success: false, error: 'ID da fatura inválido.' });
  const rows = await select(
    'SELECT * FROM invoices WHERE id = :id AND company_id = :company_id LIMIT 1',
    { id, company_id: companyId },
  );
  return rows[0]
    ? response(200, { success: true, data: rows[0] })
    : response(404, { success: false, message: 'Fatura não encontrada.' });
});

const fetchCreditNotes = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const rows = await select(
    `SELECT c.id, c.invoice_id, c.company_id, c.contact_id, c.currency, c.created_at,
            c.final_total, c.reason, c.retention, c.retention_value, c.subtotal_without_tax,
            c.total_discount, c.total_tax, c.total_sum, c.user_id, c.issue_date
     FROM credit_notes c
     INNER JOIN invoices i ON i.id = c.invoice_id
     WHERE i.reference IS NOT NULL AND c.company_id = :company_id
     ORDER BY c.id DESC`,
    { company_id: companyId },
  );
  return response(200, { success: true, data: rows.map((row) => ({
    ...row,
    issue_date: formatListDate(row.issue_date),
  })) });
});

const fetchDebitNotes = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const rows = await select(
    `SELECT dn.id, dn.invoice_id, dn.serie, dn.number, dn.issue_date, dn.reason, dn.currency,
            dn.subtotal_without_tax, dn.total_tax, dn.final_total, dn.created_at, c.name AS client_name
     FROM debit_notes dn
     JOIN contact c ON c.id = dn.contact_id
     WHERE dn.company_id = :company_id
     ORDER BY dn.id DESC`,
    { company_id: companyId },
  );
  return response(200, { success: true, data: rows });
});

const fetchDeliveryNotes = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const rows = await select(
    `SELECT dn.id, dn.invoice_id, dn.serie, dn.number, dn.issue_date, dn.delivery_address,
            dn.notes, dn.created_at, c.name AS client_name,
            (SELECT COALESCE(SUM(dni.quantity), 0)
             FROM delivery_note_items dni WHERE dni.delivery_note_id = dn.id) AS total_quantity
     FROM delivery_notes dn
     JOIN contact c ON c.id = dn.contact_id
     WHERE dn.company_id = :company_id
     ORDER BY dn.id DESC`,
    { company_id: companyId },
  );
  return response(200, { success: true, data: rows });
});

const fetchReceipts = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const rows = await select(
    `SELECT r.id, r.invoice_id, r.serie, r.reference, r.number, r.notes, r.pay_date,
            r.payment_method, r.amount_paid, r.pending_amount, r.created_at AS issue_date, i.due_date
     FROM receipts r
     INNER JOIN invoices i ON i.id = r.invoice_id
     WHERE r.reference IS NOT NULL AND i.company_id = :company_id
     ORDER BY r.id DESC`,
    { company_id: companyId },
  );
  return response(200, { success: true, data: rows.map((row) => {
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
  }) });
});

const getLastReceipt = jsonHandler(async (req) => {
  const companyId = companyIdFromRequest(req);
  if (!companyId) return response(401, { success: false, error: 'Empresa não encontrada na sessão.' });
  const invoiceId = positiveId(req.query?.invoice_id);
  if (!invoiceId) return response(422, { success: false, message: 'invoice_id é obrigatório.' });
  const rows = await select(
    `SELECT r.id, r.invoice_id, r.reference, r.serie, r.number, r.pay_date, r.amount_paid,
            r.payment_method, r.notes, r.created_at
     FROM receipts r
     JOIN invoices i ON i.id = r.invoice_id
     WHERE r.invoice_id = :invoice_id AND i.company_id = :company_id
     ORDER BY r.id DESC LIMIT 1`,
    { invoice_id: invoiceId, company_id: companyId },
  );
  return response(200, { success: true, data: rows[0] ?? null });
});

const actions = [
  'cancelInvoice', 'cloneInvoice', 'createCreditNote', 'createDebitNote', 'createDeliveryNote',
  'creditNotes', 'debitNotes', 'deleteInvoice', 'deliveryNotes', 'exportInvoices',
  'finalInvoice', 'getInvoiceData', 'getInvoicesFeed', 'getProform', 'registerPayment',
  'sendInvoice', 'updateStatus',
];
const handlers = Object.fromEntries(actions.map((action) => [
  action,
  notImplemented('invoices', action, { requiresId: !['getInvoicesFeed', 'getLastReceipt'].includes(action) }),
]));

export class InvoicesController {
  static handlers = {
    ...handlers,
    fetch,
    fetchCreditNotes,
    fetchDebitNotes,
    fetchDeliveryNotes,
    fetchReceipts,
    getInvoice: getInvoiceById,
    getLastInvoice,
    getLastReceipt,
    invoicePublic: notImplemented('invoices', 'invoicePublic', { publicAccess: true, requiresId: true }),
    debitNotePdf: notImplemented('invoices', 'debitNotePdf', { requiresId: true }),
    deliveryNotePdf: notImplemented('invoices', 'deliveryNotePdf', { requiresId: true }),
    receiptPdf: notImplemented('invoices', 'receiptPdf', { requiresId: true }),
  };
}
