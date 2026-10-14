import { buildArchiveHandlers } from '../services/archiveMigrationService.js';

const entry = (action, method, options = {}) => ({
  action,
  method,
  apiPath: options.apiPath || `/${action.replaceAll('_', '-')}`,
  legacyPath: options.legacyPath || `/ajax/${action}.php`,
  access: options.access || 'authenticated',
  required: options.required || [],
  diagnostic: Boolean(options.diagnostic),
  contentType: options.contentType,
  fileName: options.fileName,
});

const xlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

export const indexSpecs = [
  entry('ai_action_confirm', 'POST'),
  entry('check_contribuitor', 'POST'),
  entry('copilot_ask', 'POST'),
  entry('data_company_notify', 'POST'),
  entry('data_user_notify', 'POST'),
  entry('export_credit_notes', 'GET', { contentType: xlsx, fileName: 'credit-notes.xlsx' }),
  entry('export_debit_notes', 'GET', { contentType: xlsx, fileName: 'debit-notes.xlsx' }),
  entry('export_helpers', 'POST', { contentType: 'application/octet-stream', fileName: 'export' }),
  entry('export_receipts', 'GET', { contentType: xlsx, fileName: 'receipts.xlsx' }),
  entry('export_sales_report', 'GET', { contentType: xlsx, fileName: 'sales-report.xlsx' }),
  entry('faturas_export', 'GET', { contentType: xlsx, fileName: 'invoices.xlsx' }),
  entry('fetch_stock_data', 'POST'),
  entry('fetch_stocks', 'POST'),
  entry('generate_saft', 'GET', { contentType: 'application/xml', fileName: 'saft.xml' }),
  entry('get_ai_insights', 'GET'),
  entry('get_daily_report', 'GET'),
  entry('get_hr_insights', 'GET'),
  entry('get_intelligence_customers', 'GET'),
  entry('get_intelligence_health', 'GET'),
  entry('get_intelligence_my_day', 'GET'),
  entry('get_invoice_data', 'POST'),
  entry('get_monthly_report', 'GET'),
  entry('get_notifications', 'GET'),
  entry('get_sell_insights', 'GET'),
  entry('get_stock_dashboard', 'GET'),
  entry('nif_soap', 'GET', {
    apiPath: '/nif-soap',
    legacyPath: '/nif_soap.php',
    access: 'admin',
    diagnostic: true,
  }),
  entry('notifications_actions', 'POST'),
  entry('test_stock', 'GET', { access: 'admin', diagnostic: true }),
];

export class IndexController {
  static handlers = buildArchiveHandlers('index', indexSpecs);
}
