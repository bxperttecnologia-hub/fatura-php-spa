import { buildArchiveHandlers, positiveIntegerField } from '../services/archiveMigrationService.js';

const required = (name) => ({ name });
const entry = (action, method, options = {}) => {
  const legacyPath = options.legacyPath || `/ajax/${action}.php`;
  return {
    action,
    method,
    apiPath: options.apiPath || `/${action.replaceAll('_', '-')}`,
    legacyPath,
    access: 'authenticated',
    required: options.required || [],
    contentType: options.contentType,
    fileName: options.fileName,
  };
};

const xlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
const pdf = 'application/pdf';

export const rhSpecs = [
  entry('calculate_payroll_batch', 'POST'),
  entry('delete_attendance', 'POST', { required: [positiveIntegerField()] }),
  entry('delete_department', 'POST', { required: [positiveIntegerField()] }),
  entry('delete_employee_file', 'POST', { required: [positiveIntegerField()] }),
  entry('delete_employee', 'POST', { required: [positiveIntegerField()] }),
  entry('delete_evaluation_template', 'POST', { required: [positiveIntegerField()] }),
  entry('delete_position', 'POST', { required: [positiveIntegerField()] }),
  entry('delete_vacation', 'POST', { required: [positiveIntegerField()] }),
  entry('desligar_funcionario', 'POST', { required: [positiveIntegerField('employee_id')] }),
  entry('export_all_payroll_pdf', 'GET', { contentType: pdf, fileName: 'payroll.pdf' }),
  entry('export_employees_pdf', 'POST', { contentType: pdf, fileName: 'employees.pdf' }),
  entry('export_vacations_pdf', 'GET', { contentType: pdf, fileName: 'vacations.pdf' }),
  entry('generate_evaluations', 'POST'),
  entry('generate_monthly_payroll', 'POST'),
  entry('get_alerts', 'GET'),
  entry('get_dashboard_stats', 'GET'),
  entry('get_employee_evaluation_history', 'GET', { required: [positiveIntegerField('employee_id')] }),
  entry('get_evaluation', 'GET', { required: [positiveIntegerField()] }),
  entry('get_org_chart', 'POST'),
  entry('get_payroll', 'GET'),
  entry('get_suggested_subsidies', 'GET'),
  entry('get_vacation', 'GET', { required: [positiveIntegerField()] }),
  entry('issue_payroll_batch', 'POST'),
  entry('list_attendance', 'POST'),
  entry('list_departments', 'POST'),
  entry('list_employees_for_payroll', 'GET'),
  entry('list_employees', 'POST'),
  entry('list_evaluation_cycles', 'POST'),
  entry('list_evaluation_templates', 'POST'),
  entry('list_evaluations', 'POST'),
  entry('list_managers', 'GET'),
  entry('list_payroll', 'POST'),
  entry('list_positions', 'POST'),
  entry('list_vacations', 'POST'),
  entry('save_attendance', 'POST'),
  entry('save_department', 'POST'),
  entry('save_employee', 'POST', { required: [required('employee_name')] }),
  entry('save_evaluation_answers', 'POST'),
  entry('save_evaluation_cycle', 'POST'),
  entry('save_evaluation_template', 'POST'),
  entry('save_payroll', 'POST'),
  entry('save_position', 'POST'),
  entry('save_vacation', 'POST'),
  entry('search_employees', 'GET'),
  entry('simular_rescisao', 'GET', {
    required: [
      positiveIntegerField('employee_id'),
      required('tipo_cessacao'),
    ],
  }),
  entry('update_vacation_status', 'POST'),
  entry('vacation_ajax', 'GET'),
  entry('folha_export', 'POST', {
    apiPath: '/export/folha-export',
    legacyPath: '/export/folha_export.php',
    contentType: xlsx,
    fileName: 'payroll.xlsx',
  }),
  entry('mapa_inss', 'GET', {
    apiPath: '/export/mapa-inss',
    legacyPath: '/export/mapa_inss.php',
    contentType: xlsx,
    fileName: 'inss-map.xlsx',
  }),
  entry('provisoes', 'GET', {
    apiPath: '/export/provisoes',
    legacyPath: '/export/provisoes.php',
    contentType: xlsx,
    fileName: 'provisions.xlsx',
  }),
  entry('recibo', 'GET', {
    apiPath: '/pdf/recibo',
    legacyPath: '/pdf/recibo.php',
    contentType: pdf,
    fileName: 'pay-slip.pdf',
  }),
];

export class RhController {
  static handlers = buildArchiveHandlers('rh', rhSpecs);
}
