import { QueryTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

const result = (status, body) => ({ status, body });
const respond = (handler) => async (req, res, next) => {
  try {
    const output = await handler(req);
    return res.status(output.status).json(output.body);
  } catch (error) {
    return next(error);
  }
};
const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const decimal = (value, fallback) => {
  if (value == null || value === '') return fallback;
  const number = Number(String(value).replace(',', '.'));
  return Number.isFinite(number) ? number : null;
};
const truthyFormValue = (value) => ['1', 'true', 'on', 'yes'].includes(String(value).toLowerCase());
const normalizeIban = (value) => {
  if (value == null || String(value).trim() === '') return null;
  let digits = String(value).toUpperCase().replace(/[^A-Z0-9]/g, '').replace(/^AO/, '').replace(/^\d{2}/, '');
  digits = digits.replace(/\D/g, '').slice(0, 21).padEnd(21, '0');
  const groups = digits.slice(0, 20).match(/.{1,4}/g) ?? [];
  const finalDigit = digits.slice(20);
  if (finalDigit) groups.push(finalDigit);
  return `AO06 ${groups.join(' ')}`.slice(0, 25);
};

const update = async (req) => {
  const userId = positiveId(req.session?.user?.id ?? req.session?.userId);
  if (!userId) return result(401, { success: false, message: 'Sessão expirada. Faça login novamente.' });
  const body = req.body ?? {};
  const companyId = positiveId(body.id);
  const name = String(body.name ?? '').trim();
  const registrationNumber = String(body.registration_number ?? '').trim();
  if (!companyId) return result(422, { success: false, message: 'ID da empresa inválido.' });
  if (!name || !registrationNumber) {
    return result(422, { success: false, message: 'Nome e registo da empresa são obrigatórios.' });
  }
  if (req.file || body.logo) {
    return result(501, {
      success: false,
      message: 'Upload de logótipo ainda não foi migrado: o projeto não tem middleware de upload configurado.',
      todo: true,
    });
  }
  const vatRegime = ['geral', 'simplificado', 'isento'].includes(String(body.vat_regime))
    ? String(body.vat_regime) : 'geral';
  const invoiceImpression = ['A4', 'A5', 'Recibo'].includes(String(body.invoice_impression))
    ? String(body.invoice_impression) : 'A4';
  const withholdingEnabled = Number(body.withholding_enabled ?? 0) ? 1 : 0;
  const defaultVatRate = decimal(body.default_vat_rate, 14);
  const withholdingRate = withholdingEnabled ? decimal(body.withholding_rate, 0) : 0;
  if (defaultVatRate == null || withholdingRate == null || defaultVatRate < 0 || withholdingRate < 0) {
    return result(422, { success: false, message: 'Taxas fiscais inválidas.' });
  }
  const replacements = {
    companyId, name, registrationNumber,
    phone: String(body.phone ?? '').trim(),
    email: String(body.email ?? '').trim(),
    website: String(body.website ?? '').trim(),
    address: String(body.address ?? '').trim(),
    country: String(body.country ?? '').trim(),
    city: String(body.city ?? '').trim(),
    zipCode: String(body.zip_code ?? '').trim(),
    currency: String(body.currency ?? 'AOA').trim() || 'AOA',
    vatRegime, defaultVatRate,
    vatIncludedPrices: truthyFormValue(body.vat_included_prices) ? 1 : 0,
    withholdingEnabled, withholdingRate,
    goodsServices: body.goods_services ?? null,
    bankName: body.bank_name ?? null,
    bankNameOptional: body.bank_name_optional ?? null,
    iban: normalizeIban(body.iban),
    ibanOptional: normalizeIban(body.iban_optional),
    bankDetails: body.bank_details ?? null,
    invoiceImpression,
  };
  if (replacements.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(replacements.email)) {
    return result(422, { success: false, message: 'Email inválido.' });
  }
  const memberships = await sequelize.query(
    'SELECT role FROM company_has_user WHERE user_id=:userId AND company_id=:companyId LIMIT 1',
    { replacements: { userId, companyId }, type: QueryTypes.SELECT },
  );
  if (!['owner', 'admin'].includes(memberships[0]?.role)) {
    return result(403, { success: false, message: 'Acesso negado.' });
  }
  const existing = await sequelize.query(
    'SELECT id FROM companies WHERE id=:companyId LIMIT 1',
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  if (!existing.length) return result(404, { success: false, message: 'Empresa não encontrada.' });
  await sequelize.query(
    `UPDATE companies SET name=:name, registration_number=:registrationNumber, phone=:phone,
      email=:email, website=:website, address=:address, country=:country, city=:city, zip_code=:zipCode,
      currency=:currency, vat_regime=:vatRegime, default_vat_rate=:defaultVatRate,
      vat_included_prices=:vatIncludedPrices, withholding_enabled=:withholdingEnabled,
      withholding_rate=:withholdingRate, goods_services=:goodsServices, bank_name=:bankName,
      bank_name_optional=:bankNameOptional, iban=:iban, iban_optional=:ibanOptional,
      bank_details=:bankDetails, invoice_impression=:invoiceImpression
     WHERE id=:companyId`,
    { replacements, type: QueryTypes.UPDATE },
  );
  return result(200, { success: true, logo_url: null });
};

const show = async (req) => {
  const userId = positiveId(req.session?.user?.id ?? req.session?.userId);
  if (!userId) return result(401, { success: false, message: 'Sessão expirada. Faça login novamente.' });
  const companyId = positiveId(req.query?.id);
  if (!companyId) return result(422, { success: false, message: 'ID da empresa inválido.' });
  const memberships = await sequelize.query(
    'SELECT role FROM company_has_user WHERE user_id=:userId AND company_id=:companyId LIMIT 1',
    { replacements: { userId, companyId }, type: QueryTypes.SELECT },
  );
  if (!['owner', 'admin'].includes(memberships[0]?.role)) {
    return result(404, { success: false, message: 'Empresa não encontrada.' });
  }
  const companies = await sequelize.query(
    'SELECT * FROM companies WHERE id=:companyId LIMIT 1',
    { replacements: { companyId }, type: QueryTypes.SELECT },
  );
  if (!companies.length) return result(404, { success: false, message: 'Empresa não encontrada.' });
  return result(200, { success: true, data: companies[0] });
};

export class EditCompanyController {
  static handlers = { show: respond(show), update: respond(update) };
}
