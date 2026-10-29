const IRT_BRACKETS_2026 = [
  [150001, 200000, 12500, 0.16],
  [200001, 300000, 31250, 0.18],
  [300001, 500000, 49250, 0.19],
  [500001, 1000000, 87250, 0.20],
  [1000001, 1500000, 187250, 0.21],
  [1500001, 2000000, 292250, 0.22],
  [2000001, 2500000, 402250, 0.23],
  [2500001, 5000000, 517250, 0.24],
  [5000001, 10000000, 1117250, 0.245],
  [10000001, Number.POSITIVE_INFINITY, 2342250, 0.25],
];

const parseDate = (value) => {
  const date = value instanceof Date ? value : new Date(`${value}T00:00:00Z`);
  if (Number.isNaN(date.getTime())) throw new TypeError('Invalid date');
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
};

export const calculateIrtGroupA2026 = (base) => {
  const amount = Number(base);
  if (!Number.isFinite(amount)) throw new TypeError('IRT base must be a finite number');
  if (amount <= 150000) return 0;

  const bracket = IRT_BRACKETS_2026.find(([min, max]) => amount >= min && amount <= max);
  if (!bracket) return 0;
  const [min, , fixed, rate] = bracket;
  const lowerBound = min === 150001 ? 150000 : min - 1;
  return fixed + rate * Math.max(0, amount - lowerBound);
};

export const calculateCompletedServiceYears = (admissionDate, referenceDate = new Date()) => {
  const admission = parseDate(admissionDate);
  const reference = parseDate(referenceDate);
  if (reference < admission) return 0;

  let years = reference.getUTCFullYear() - admission.getUTCFullYear();
  let months = reference.getUTCMonth() - admission.getUTCMonth();
  if (reference.getUTCDate() < admission.getUTCDate()) months -= 1;
  if (months < 0) {
    years -= 1;
    months += 12;
  }
  return years + (months >= 3 ? 1 : 0);
};

export const countWorkingDays = (start, end, holidays = []) => {
  const first = parseDate(start);
  const last = parseDate(end);
  if (last < first) return 0;
  const holidaySet = new Set(holidays.map((date) => parseDate(date).toISOString().slice(0, 10)));
  let days = 0;
  for (let cursor = first; cursor <= last; cursor.setUTCDate(cursor.getUTCDate() + 1)) {
    const day = cursor.getUTCDay();
    const date = cursor.toISOString().slice(0, 10);
    if (day !== 0 && day !== 6 && !holidaySet.has(date)) days += 1;
  }
  return days;
};

export const calculateSeverance = (employee, terminationType, endDate, options = {}) => {
  const salary = Number(employee?.salary_base || 0);
  if (!Number.isFinite(salary) || salary < 0) throw new TypeError('Invalid base salary');
  const serviceYears = employee?.admission_date
    ? calculateCompletedServiceYears(employee.admission_date, endDate)
    : 0;
  const warnings = [
    'Cálculo de apoio — confirma sempre com um advogado laboral ou contabilista antes de pagar. Não cobre todas as exceções da Lei n.º 12/23.',
  ];
  const result = {
    tipo_cessacao: terminationType,
    anos_antiguidade: serviceYears,
    aviso_previo_dias: 0,
    indemnizacao: 0,
    detalhe: '',
    avisos: warnings,
  };

  switch (terminationType) {
    case 'periodo_experimental':
      result.detalhe = 'Período experimental: sem aviso prévio nem indemnização. Paga-se apenas o trabalho já prestado até à data de cessação.';
      break;
    case 'termo_certo_nao_renovado':
      result.aviso_previo_dias = 30;
      result.indemnizacao = options.aviso_previo_dado ? 0 : salary;
      result.detalhe = options.aviso_previo_dado
        ? 'Contrato a termo certo não renovado, com aviso prévio de 30 dias dado a tempo: sem compensação adicional.'
        : 'Contrato a termo certo não renovado, sem aviso prévio de 30 dias: compensação de 30 dias de salário base.';
      break;
    case 'causas_objetivas': {
      const yearsToCalculate = Math.min(serviceYears, 5);
      result.indemnizacao = Math.round(salary * yearsToCalculate * 100) / 100;
      result.detalhe = `Despedimento por causas objetivas/coletivo: ${serviceYears} ano(s) de antiguidade, limitado ao teto legal de 5 anos.`;
      if (serviceYears > 5) warnings.push(`Antiguidade real (${serviceYears} anos) excede o teto de 5 anos — teto aplicado.`);
      break;
    }
    case 'sem_justa_causa': {
      const byService = Math.round(salary * serviceYears * 100) / 100;
      const minimum = Math.round(salary * 3 * 100) / 100;
      result.indemnizacao = Math.max(byService, minimum);
      result.detalhe = `Indemnização calculada como o maior entre ${serviceYears} ano(s) × salário base e três meses de salário base.`;
      break;
    }
    case 'demissao_trabalhador':
      result.aviso_previo_dias = Number.parseInt(options.aviso_previo_dias_config ?? 30, 10);
      result.detalhe = `Demissão pelo trabalhador, sem justa causa: cumprir ${result.aviso_previo_dias} dias de aviso prévio.`;
      warnings.push('Confirma com um contabilista a configuração legal de aviso prévio aplicável.');
      break;
    default:
      result.detalhe = 'Tipo de cessação desconhecido — nenhum cálculo aplicado.';
      warnings.push(`Tipo de cessação '${terminationType}' não reconhecido.`);
  }
  return result;
};

export const rhHolidaysForCompany = async (sequelize, companyId, start, end) => {
  const [rows] = await sequelize.query(
    'SELECT date, name FROM holidays WHERE company_id = :companyId AND date BETWEEN :start AND :end',
    { replacements: { companyId, start, end } },
  );
  return Object.fromEntries(rows.map((row) => [
    new Date(row.date).toISOString().slice(0, 10),
    row.name,
  ]));
};

export const isRhHoliday = async (sequelize, companyId, date) => {
  const holidays = await rhHolidaysForCompany(sequelize, companyId, date, date);
  return Object.hasOwn(holidays, parseDate(date).toISOString().slice(0, 10));
};

export const rhWorkingDaysForCompany = async (sequelize, companyId, start, end) => {
  const holidays = await rhHolidaysForCompany(sequelize, companyId, start, end);
  return countWorkingDays(start, end, Object.keys(holidays));
};

export const calculateEmployeePayroll = async () => ({
  statusCode: 501,
  success: false,
  migration_pending: true,
  message: 'Payroll completo ainda depende da migração e validação das consultas PHP.',
});
