import { QueryTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

const output = (status, body) => ({ status, body });
const sessionUser = (req) => req.session?.user;
const plans = Object.freeze({
  BXPERT_BAZA: {
    name: 'BXPERT BAZA',
    price: 15900,
    invoice_limit_month: 15,
    user_limit: 2,
    rh_employee_limit: 0,
    stock_item_limit: 0,
    features: { rh: false, stock: false, reports: 'daily' },
  },
  BXPERT_BASE: {
    name: 'BXPERT BASE',
    price: 17900,
    invoice_limit_month: 30,
    user_limit: 3,
    rh_employee_limit: 5,
    stock_item_limit: 0,
    features: { rh: true, stock: false, reports: 'daily' },
  },
  XPERT: {
    name: 'XPERT',
    price: 23600,
    invoice_limit_month: 70,
    user_limit: 4,
    rh_employee_limit: 15,
    stock_item_limit: 50,
    features: { rh: true, stock: true, reports: 'full' },
  },
  ENTERPRISE: {
    name: 'ENTERPRISE',
    price: 28000,
    invoice_limit_month: null,
    user_limit: 10,
    rh_employee_limit: 70,
    stock_item_limit: 300,
    features: { rh: true, stock: true, reports: 'full' },
  },
});
const companyIdFor = (req) => {
  const id = Number(sessionUser(req)?.company_id ?? sessionUser(req)?.companyId);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const overview = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return output(401, { success: false, message: 'Empresa inválida (sessão).' });

  const companies = await sequelize.query(
    `SELECT plan_code, plan_started_at, plan_expires_at
       FROM companies WHERE id=:companyId LIMIT 1`,
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  if (!companies.length) return output(404, { success: false, message: 'Empresa não encontrada.' });

  const company = companies[0];
  const planCode = Object.hasOwn(plans, company.plan_code) ? company.plan_code : 'BXPERT_BAZA';
  const plan = { ...plans[planCode], code: planCode };
  const now = new Date();
  const ym = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const firstDay = `${ym}-01`;
  const lastDay = `${ym}-${String(new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()).padStart(2, '0')}`;

  const [invoiceRows, userRows, employeeRows, stockRows] = await Promise.all([
    sequelize.query(
      `SELECT COUNT(*) AS total FROM invoices
        WHERE company_id=:companyId AND issue_date BETWEEN :firstDay AND :lastDay`,
      { replacements: { companyId, firstDay, lastDay }, type: QueryTypes.SELECT },
    ),
    sequelize.query(
      'SELECT COUNT(*) AS total FROM company_has_user WHERE company_id=:companyId',
      { replacements: { companyId }, type: QueryTypes.SELECT },
    ),
    sequelize.query(
      `SELECT COUNT(*) AS total FROM employees
        WHERE company_id=:companyId AND status='ativo'`,
      { replacements: { companyId }, type: QueryTypes.SELECT },
    ),
    sequelize.query(
      `SELECT COUNT(*) AS total FROM stock_items si
         JOIN stocks s ON s.id=si.stock_id WHERE s.company_id=:companyId`,
      { replacements: { companyId }, type: QueryTypes.SELECT },
    ),
  ]);

  const expiresAt = company.plan_expires_at ? new Date(company.plan_expires_at) : null;
  const daysLeft = expiresAt && Number.isFinite(expiresAt.getTime())
    ? Math.floor((expiresAt.getTime() - now.getTime()) / 86400000)
    : null;
  const usage = {
    ym,
    invoice_count: Number(invoiceRows[0]?.total ?? 0),
    user_count: Number(userRows[0]?.total ?? 0),
    employee_count: Number(employeeRows[0]?.total ?? 0),
    stock_item_count: Number(stockRows[0]?.total ?? 0),
  };

  return output(200, {
    success: true,
    data: {
      company_id: companyId,
      plan_code: planCode,
      plan,
      plans: Object.entries(plans).map(([code, definition]) => ({ code, ...definition })),
      plan_started_at: company.plan_started_at,
      plan_expires_at: company.plan_expires_at,
      days_left: daysLeft,
      usage,
    },
  });
};
const respond = (handler) => async (req, res, next) => {
  try {
    const value = await handler(req);
    return res.status(value.status).json(value.body);
  } catch (error) {
    return next(error);
  }
};
const todo = (name) => output(501, {
  success: false, message: `${name} ainda não foi migrado com fidelidade.`, todo: true,
});

const appyPayWebhook = (req) => {
  const expectedToken = process.env.APPYPAY_WEBHOOK_TOKEN;
  if (expectedToken && req.get('x-appypay-webhook-token') !== expectedToken) {
    return output(401, { ok: false, error: 'unauthorized' });
  }
  const payload = req.body;
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    return output(422, { ok: false, error: 'invalid_json' });
  }
  const merchant = payload.merchantTransactionId
    ?? payload.data?.merchantTransactionId
    ?? payload.merchant_transaction_id;
  if (!merchant) return output(422, { ok: false, error: 'merchantTransactionId_missing' });
  return todo('Webhook AppyPay');
};

const checkMembership = async (companyId, userId) => {
  if (!companyId || !userId) return null;
  const rows = await sequelize.query(
    'SELECT role FROM company_has_user WHERE user_id=:userId AND company_id=:companyId LIMIT 1',
    { replacements: { userId, companyId }, type: QueryTypes.SELECT },
  );
  return rows[0]?.role ?? null;
};

const listOrders = async (req) => {
  const companyId = companyIdFor(req);
  if (!companyId) return output(401, { success: false, message: 'Empresa inválida (sessão).' });
  const userId = positiveId(sessionUser(req)?.id);
  const role = await checkMembership(companyId, userId);
  if (!role) return output(403, { success: false, message: 'Acesso negado.' });
  const page = Math.max(1, Number.parseInt(req.query.page, 10) || 1);
  const limit = Math.max(1, Math.min(50, Number.parseInt(req.query.limit, 10) || 10));
  const offset = (page - 1) * limit;
  const countRows = await sequelize.query(
    'SELECT COUNT(*) AS total FROM subscription_orders WHERE company_id=:companyId',
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  const orders = await sequelize.query(
    `SELECT id, plan_code, period_months, amount, status, gateway, payment_method,
            merchant_transaction_id, gateway_charge_id, gateway_status, payment_ref,
            created_at, paid_at
       FROM subscription_orders WHERE company_id=:companyId
      ORDER BY created_at DESC LIMIT :limit OFFSET :offset`,
    { replacements: { companyId, limit, offset }, type: QueryTypes.SELECT },
  );
  const total = Number(countRows[0]?.total ?? 0);
  return output(200, {
    success: true, orders, page, limit, total, total_pages: Math.ceil(total / limit),
  });
};

const markPaid = async (req) => {
  const companyId = positiveId(req.body?.company_id) ?? companyIdFor(req);
  if (!companyId) return output(401, { success: false, message: 'Empresa inválida (sessão).' });
  const userId = positiveId(sessionUser(req)?.id);
  const role = await checkMembership(companyId, userId);
  if (!role) return output(403, { success: false, message: 'Acesso negado.' });
  const pending = await sequelize.query(
    `SELECT id, plan_code, period_months FROM subscription_orders
      WHERE company_id=:companyId AND status='pending' ORDER BY id DESC LIMIT 1`,
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  if (!pending.length) return output(404, { success: false, message: 'Nenhum pedido pendente encontrado.' });
  const order = pending[0];
  const companyRows = await sequelize.query(
    'SELECT plan_code, plan_expires_at FROM companies WHERE id=:companyId LIMIT 1',
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  if (!companyRows.length) return output(404, { success: false, message: 'Empresa não encontrada.' });
  const company = companyRows[0];
  const planCode = order.plan_code || company.plan_code || 'BXPERT_BAZA';
  const now = new Date();
  const today = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
  let start = today;
  if (company.plan_code === planCode && company.plan_expires_at) {
    const expiry = new Date(company.plan_expires_at);
    if (Number.isFinite(expiry.getTime()) && expiry > today) start = expiry;
  }
  const expiry = new Date(start);
  expiry.setUTCMonth(expiry.getUTCMonth() + Math.max(1, Number(order.period_months) || 3));
  const toDate = (date) => date.toISOString().slice(0, 10);
  const newExpiresAt = toDate(expiry);
  await sequelize.transaction(async (transaction) => {
    await sequelize.query(
      `UPDATE subscription_orders SET status='paid', paid_at=NOW()
        WHERE id=:id AND company_id=:companyId AND status='pending'`,
      { replacements: { id: order.id, companyId }, transaction },
    );
    await sequelize.query(
      `UPDATE companies
          SET plan_code=:planCode, plan_started_at=:today,
              plan_expires_at=:expiresAt,
              plan_status='active', is_active=1
        WHERE id=:companyId`,
      {
        replacements: {
          planCode,
          today: toDate(today),
          expiresAt: newExpiresAt,
          companyId,
        },
        transaction,
      },
    );
  });
  return output(200, { success: true, new_expires_at: newExpiresAt });
};

const handlers = {
  overview: respond(overview),
  listOrders: respond(listOrders),
  markPaid: respond(markPaid),
  createCharge: respond(async (req) => {
    if (!companyIdFor(req)) return output(401, { success: false, message: 'Empresa inválida (sessão).' });
    const method = String(req.body?.method ?? '').trim().toUpperCase();
    const phone = String(req.body?.phone ?? '').trim();
    if (!['REF', 'GPO'].includes(method) || !/^\d{9}$/.test(phone)) {
      return output(422, { success: false, message: 'Método ou telefone inválido.' });
    }
    return todo('Integração de cobrança AppyPay');
  }),
  createOrder: respond(async (req) => companyIdFor(req)
    ? todo('Criação de pedido: catálogo/preços do helper PHP não disponível')
    : output(401, { success: false, message: 'Empresa inválida (sessão).' })),
  setPlan: respond(async (req) => {
    if (!positiveId(sessionUser(req)?.id)) {
      return output(401, { success: false, message: 'Utilizador não autenticado.' });
    }
    if (!positiveId(req.body?.company_id) || !String(req.body?.plan_code ?? '').trim()) {
      return output(422, { success: false, message: 'Dados inválidos.' });
    }
    return todo('Validação do plano conforme catálogo do helper PHP');
  }),
  syncOrders: respond(async (req) => companyIdFor(req)
    ? todo('Reconciliação de cobranças AppyPay')
    : output(401, { success: false, message: 'Empresa inválida (sessão).' })),
  testToken: respond(async (req) => positiveId(sessionUser(req)?.id)
    ? todo('Endpoint de teste de credenciais de pagamento')
    : output(401, { success: false, message: 'Utilizador não autenticado.' })),
  appyPayWebhook: respond(appyPayWebhook),
};

export class SubscriptionController {
  static handlers = handlers;
}
