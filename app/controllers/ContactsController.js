import { createHash } from 'node:crypto';
import { lookup } from 'node:dns/promises';
import { mkdir, readFile, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { tmpdir } from 'node:os';
import { QueryTypes } from 'sequelize';
import { appConfig } from '../../config/app.js';
import { sequelize } from '../../config/database.js';

const result = (statusCode, payload) => [statusCode, payload];

const respond = (handler) => async (req, res, next) => {
  try {
    const [statusCode, payload] = await handler(req);
    return res.status(statusCode).json(payload);
  } catch (error) {
    return next(error);
  }
};

const validCompanyId = (req) => {
  const companyId = Number(req.session?.user?.company_id);
  return Number.isInteger(companyId) && companyId > 0 ? companyId : null;
};

const validContactId = (value) => {
  const id = Number.parseInt(value, 10);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};

const contactIdFromRequest = (req) => req.body?.id ?? req.query?.id ?? req.params?.id;

const cleanDate = (date) => date.toISOString().slice(0, 19).replace('T', ' ');

const parseJsonPayload = (raw, message) => {
  try {
    return JSON.parse(raw);
  } catch {
    return { status: 'error', message };
  }
};

const geoNamesRequest = async (url) => {
  const response = await fetch(url, { signal: AbortSignal.timeout(15000) });
  const raw = await response.text();
  if (!response.ok) {
    return result(502, { success: false, message: 'Falha ao consultar GeoNames.' });
  }
  return result(200, parseJsonPayload(raw, 'Resposta inválida do GeoNames.'));
};

const nifEnv = (...keys) => keys.map((key) => process.env[key]).find((value) => value);

const csvCell = (value) => {
  const text = value == null ? '' : String(value);
  return `"${text.replaceAll('"', '""')}"`;
};

export class ContactsController {
  static async save(body, companyId) {
    if (!companyId) {
      return result(401, { status: 'error', message: 'Sessão expirada. Faça login novamente.' });
    }

    const values = { ...body, company_id: companyId, created_at: cleanDate(new Date()) };
    delete values.id;
    delete values.updated_at;
    delete values.undefined;

    const fields = Object.keys(values);
    if (fields.some((field) => !/^[A-Za-z0-9_]+$/.test(field))) {
      return result(422, { status: 'error', message: 'Campo inválido no formulário.' });
    }

    for (const field of ['company_id', 'name', 'contributor', 'address']) {
      if (values[field] == null || String(values[field]).trim() === '') {
        return result(422, { status: 'error', message: `O campo '${field}' é obrigatório.` });
      }
    }
    if (fields.length === 0) {
      return result(422, { status: 'error', message: 'Nenhum dado enviado' });
    }

    const sql = `INSERT INTO contact (${fields.map((field) => `\`${field}\``).join(', ')}) VALUES (${fields.map((field) => `:${field}`).join(', ')})`;
    const [insertId] = await sequelize.query(sql, {
      replacements: values,
      type: QueryTypes.INSERT,
    });

    return result(201, {
      status: 'success',
      message: 'Contato cadastrado com sucesso',
      id: Number(insertId),
    });
  }

  static async update(body, companyId) {
    if (!companyId) {
      return result(401, { status: 'error', message: 'Sessão expirada. Faça login novamente.' });
    }

    const id = validContactId(body.id);
    const values = { ...body };
    delete values.id;
    delete values.undefined;
    delete values.company_id;
    delete values.created_at;
    delete values.updated_at;

    if (!id) return result(422, { status: 'error', message: 'ID inválido' });
    if (Object.keys(values).some((field) => !/^[A-Za-z0-9_]+$/.test(field))) {
      return result(422, { status: 'error', message: 'Campo inválido no formulário.' });
    }

    for (const field of ['telephone', 'pref_telephone', 'pref_cellphone']) {
      const value = values[field];
      if (value != null && String(value).trim() !== '' && !/^[29]\d{8}$/.test(String(value).trim())) {
        return result(422, {
          status: 'error',
          message: 'Telefone inválido. Deve ter 9 dígitos e começar por 2 ou 9.',
        });
      }
    }
    if (values.telephone != null && String(values.telephone).trim() === '') {
      return result(422, { status: 'error', message: "O campo 'telephone' é obrigatório." });
    }
    if (values.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(values.email).trim())) {
      return result(422, { status: 'error', message: 'Email inválido.' });
    }

    const fields = Object.keys(values);
    if (fields.length === 0) {
      return result(422, { status: 'error', message: 'Nenhuma alteração detectada' });
    }

    const columns = await sequelize.query("SHOW COLUMNS FROM contact LIKE 'updated_at'");
    const hasUpdatedAt = columns[0].length > 0;
    const assignments = fields.map((field) => `\`${field}\` = :${field}`);
    if (hasUpdatedAt) {
      values.updated_at = cleanDate(new Date());
      assignments.push('`updated_at` = :updated_at');
    }

    const [, affectedRows] = await sequelize.query(
      `UPDATE contact SET ${assignments.join(', ')} WHERE id = :id AND company_id = :company_id`,
      {
        replacements: { ...values, id, company_id: companyId },
        type: QueryTypes.UPDATE,
      },
    );
    if (affectedRows === 0) {
      const found = await sequelize.query(
        'SELECT COUNT(*) AS total FROM contact WHERE id = :id AND company_id = :company_id',
        {
          replacements: { id, company_id: companyId },
          type: QueryTypes.SELECT,
        },
      );
      if (Number(found[0]?.total) === 0) {
        return result(404, { status: 'error', message: 'Contato não encontrado.' });
      }
    }

    return result(200, { status: 'success', message: 'Contato atualizado com sucesso' });
  }

  static async delete(body, companyId) {
    if (!companyId) {
      return result(401, { success: false, message: 'Sessão expirada. Faça login novamente.' });
    }

    const id = validContactId(body.id);
    if (!id) {
      return result(422, { success: false, message: 'ID do contato não fornecido.' });
    }

    const contact = await sequelize.query(
      'SELECT COUNT(*) AS total FROM contact WHERE id = :id AND company_id = :company_id',
      {
        replacements: { id, company_id: companyId },
        type: QueryTypes.SELECT,
      },
    );
    if (Number(contact[0]?.total) === 0) {
      return result(404, { success: false, message: 'Contato não encontrado.' });
    }

    const invoices = await sequelize.query(
      'SELECT COUNT(*) AS total FROM invoices WHERE contact_id = :id',
      { replacements: { id }, type: QueryTypes.SELECT },
    );
    if (Number(invoices[0]?.total) > 0) {
      await sequelize.query(
        'UPDATE contact SET is_active = 0 WHERE id = :id AND company_id = :company_id',
        { replacements: { id, company_id: companyId }, type: QueryTypes.UPDATE },
      );
      return result(200, {
        success: true,
        archived: true,
        message: 'Não foi possível excluir, pois existem facturas vinculadas. O registo foi arquivado.',
      });
    }

    await sequelize.query(
      'DELETE FROM contact WHERE id = :id AND company_id = :company_id',
      { replacements: { id, company_id: companyId }, type: QueryTypes.DELETE },
    );
    return result(200, { success: true, archived: false });
  }

  static async changeStatus(body, companyId) {
    if (!companyId) {
      return result(401, { status: 'error', message: 'Sessão expirada. Faça login novamente.' });
    }

    const id = validContactId(body.id);
    if (!id) return result(422, { status: 'error', message: 'ID do contato inválido.' });
    if (!['0', '1'].includes(String(body.status))) {
      return result(422, { status: 'error', message: 'Estado inválido.' });
    }
    const status = Number(body.status);

    const [, affectedRows] = await sequelize.query(
      'UPDATE contact SET is_active = :status WHERE id = :id AND company_id = :company_id',
      {
        replacements: { status, id, company_id: companyId },
        type: QueryTypes.UPDATE,
      },
    );
    if (affectedRows === 0) {
      const found = await sequelize.query(
        'SELECT COUNT(*) AS total FROM contact WHERE id = :id AND company_id = :company_id',
        {
          replacements: { id, company_id: companyId },
          type: QueryTypes.SELECT,
        },
      );
      if (Number(found[0]?.total) === 0) {
        return result(404, { status: 'error', message: 'Contato não encontrado.' });
      }
    }

    return result(200, {
      status: 'success',
      message: status === 1 ? 'Contato reativado!' : 'Contato arquivado!',
      is_active: status,
    });
  }

  static async fetch(query, companyId) {
    if (!companyId) {
      return result(401, { success: false, message: 'Empresa não encontrada na sessão.' });
    }

    const isActive = Number(query.archived) === 1 ? 0 : 1;
    const contacts = await sequelize.query(
      `SELECT id, name, email, telephone, country, city, is_active
       FROM contact
       WHERE company_id = :company_id AND is_active = :is_active
       ORDER BY id DESC`,
      {
        replacements: { company_id: companyId, is_active: isActive },
        type: QueryTypes.SELECT,
      },
    );
    return result(200, { success: true, data: contacts });
  }

  static async get(body) {
    const id = validContactId(body.id);
    if (!id) return result(422, { error: 'ID do contato inválido.' });
    const contacts = await sequelize.query(
      'SELECT * FROM contact WHERE id = :id',
      { replacements: { id }, type: QueryTypes.SELECT },
    );
    if (!contacts[0]) return result(404, { error: 'Contato não encontrado.' });
    return result(200, contacts[0]);
  }

  static async details(body) {
    const id = validContactId(body.id);
    if (!id) return result(422, { error: 'ID do contato inválido.' });
    const contacts = await sequelize.query(
      `SELECT cp.name AS company_name, c.id, c.company_id, c.type, c.name, c.contributor, c.address,
              c.website, c.country, c.city, c.email, c.telephone, c.cellphone, c.po_box, c.fax,
              c.pref_name, c.pref_email, c.pref_telephone, c.pref_cellphone,
              CASE WHEN c.numberCopys = 1 THEN 'Original'
                   WHEN c.numberCopys = 2 THEN 'Duplicado'
                   WHEN c.numberCopys = 3 THEN 'Triplicado' END AS numberCopys,
              c.observations,
              CASE WHEN c.due_date = 0 THEN 'Pronto pagamento'
                   ELSE CONCAT(c.due_date, ' Dias') END AS due_date,
              ct.name AS language, p.name AS payment_method,
              CONCAT(cr.currency, ' (', cr.iso_code, ')') AS currency,
              c.created_at, c.updated_at
       FROM contact c
       JOIN companies cp ON cp.id = c.company_id
       LEFT JOIN countries ct ON ct.iso = c.language
       LEFT JOIN payment_methods_contacts p ON p.code = c.payment_method
       LEFT JOIN currencies cr ON cr.iso_code = c.currency
       WHERE c.id = :id`,
      { replacements: { id }, type: QueryTypes.SELECT },
    );
    if (!contacts[0]) return result(404, { error: 'Contato não encontrado.' });
    return result(200, contacts[0]);
  }

  static async geonamesCountries() {
    const username = process.env.GEONAMES_USERNAME || 'israelsouza';
    return geoNamesRequest(`https://api.geonames.org/countryInfoJSON?username=${encodeURIComponent(username)}`);
  }

  static async geonamesCities(query) {
    const geonameId = Number.parseInt(query.geonameId, 10);
    if (!Number.isSafeInteger(geonameId) || geonameId <= 0) {
      return result(422, { error: 'geonameId é obrigatório' });
    }
    const username = process.env.GEONAMES_USERNAME || 'israelsouza';
    const url = new URL('https://api.geonames.org/childrenJSON');
    url.search = new URLSearchParams({
      geonameId: String(geonameId),
      username,
    }).toString();
    return geoNamesRequest(url);
  }

  static async consultNif(body, user) {
    if (!user) return result(401, { status: 'error', message: 'Sessão expirada.' });

    const input = body || {};
    const documentType = String(input.tipoDocumento || 'NIF').trim().toUpperCase();
    const documentNumber = String(input.numeroDocumento || '').trim();
    const validTypes = ['NIF', 'AID', 'REF', 'RES', 'BCER', 'PASS', 'FID', 'ONIF', 'OTHR'];
    if (!validTypes.includes(documentType)) {
      return result(422, { status: 'error', message: 'Tipo de documento inválido.' });
    }
    if (!/^[A-Za-z0-9]{5,25}$/.test(documentNumber)) {
      return result(422, { status: 'error', message: 'Número de documento inválido.' });
    }

    const username = nifEnv('AGT_NIF_USERNAME', 'AGT_USERNAME', 'AGT_USER');
    const password = nifEnv('AGT_NIF_PASSWORD', 'AGT_PASSWORD', 'AGT_TOKEN');
    if (!username || !password) {
      return result(500, {
        status: 'error',
        message: 'Credenciais da AGT não encontradas. Verifique AGT_NIF_USERNAME e AGT_NIF_PASSWORD no .env.',
      });
    }

    const cacheTtl = 24 * 60 * 60 * 1000;
    const cacheDir = path.join(tmpdir(), 'agt_nif_cache');
    const cacheFile = path.join(
      cacheDir,
      `${createHash('sha1').update(`${documentType}|${documentNumber.toUpperCase()}`).digest('hex')}.json`,
    );
    if (!input.refresh) {
      try {
        const fileInfo = await stat(cacheFile);
        if (Date.now() - fileInfo.mtimeMs < cacheTtl) {
          const cached = JSON.parse(await readFile(cacheFile, 'utf8'));
          if (cached.status === 'success') {
            return result(200, { ...cached, cached: true });
          }
        }
      } catch (error) {
        if (error.code !== 'ENOENT' && !(error instanceof SyntaxError)) throw error;
      }
    }

    const agtUrl = process.env.AGT_NIF_URL
      || process.env.AGT_URL
      || 'https://sifphml.minfin.gov.ao/sigt/contribuinte/consultarNIF/v5/obter';
    const url = new URL(agtUrl);
    url.search = new URLSearchParams({
      tipoDocumento: documentType,
      numeroDocumento: documentNumber,
    }).toString();
    const headers = {
      Accept: 'application/json',
      Authorization: `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`,
      Username: username,
      Password: password,
    };
    const requestBody = JSON.stringify({
      tipoDocumento: documentType,
      numeroDocumento: documentNumber,
    });
    let method = String(process.env.AGT_NIF_METHOD || 'GET').toUpperCase();
    if (!['GET', 'POST'].includes(method)) method = 'GET';

    const callAgt = async (requestMethod) => {
      const response = await fetch(url, {
        method: requestMethod,
        headers: requestMethod === 'POST' ? { ...headers, 'Content-Type': 'application/json' } : headers,
        body: requestMethod === 'POST' ? requestBody : undefined,
        signal: AbortSignal.timeout(15000),
      });
      return { response, raw: await response.text() };
    };

    let agtResponse;
    try {
      agtResponse = await callAgt(method);
      if ([405, 501].includes(agtResponse.response.status)) {
        method = method === 'POST' ? 'GET' : 'POST';
        agtResponse = await callAgt(method);
      }
    } catch (error) {
      console.error('[AGT consultarNIF] Ligação:', error.message);
      const detail = appConfig.env === 'development' ? ` [${error.message}]` : '';
      return result(502, { status: 'error', message: `Não foi possível contactar a AGT.${detail}` });
    }

    const { response, raw } = agtResponse;
    if ([401, 403].includes(response.status)) {
      return result(502, {
        status: 'error',
        message: `A AGT recusou as credenciais (HTTP ${response.status}). Verifique o Username/Password e se são do mesmo ambiente do URL${appConfig.env === 'development' ? ` [${agtUrl}]` : ''}.`,
      });
    }

    let data;
    try {
      data = JSON.parse(raw);
    } catch {
      const detail = appConfig.env === 'development'
        ? ` [${method} → HTTP ${response.status}: ${raw.replace(/\s+/g, ' ').slice(0, 200)}]`
        : '';
      return result(502, { status: 'error', message: `Resposta inválida da AGT.${detail}` });
    }

    const root = data.ObterContribuinte || data;
    const taxpayer = root.contribuinte;
    if (response.status >= 500) {
      return result(502, {
        status: 'error',
        message: `Serviço da AGT indisponível.${appConfig.env === 'development' ? ` [${method} → HTTP ${response.status}]` : ''}`,
      });
    }
    if (response.status >= 400 || !taxpayer || !taxpayer.numeroNIF) {
      return result(200, {
        status: 'not_found',
        message: root.mensagem || 'Contribuinte não encontrado.',
      });
    }

    const states = { A: 'Ativo', C: 'Cessado', D: 'Falecido', E: 'Herança', F: 'Anulado', G: 'Suspenso' };
    const regimes = {
      GNAD: 'Regime Geral',
      TRAG: 'Regime Transitório',
      SIMP: 'Regime Simplificado',
      NBND: 'Regime de Não Sujeição',
      EXCL: 'Regime de Exclusão',
    };
    const state = taxpayer.estadoContribuinte || '';
    const regime = taxpayer.regimeIva || '';
    const payload = {
      status: 'success',
      message: root.mensagem || '',
      data: {
        nif: taxpayer.numeroNIF,
        nome: taxpayer.nome || '',
        tipo: taxpayer.tipoContribuinte || '',
        estado: state,
        estado_label: states[state] || state,
        regime_iva: regime,
        regime_label: regimes[regime] || regime,
        nao_residente: taxpayer.indicadorNaoResidente === true
          || taxpayer.indicadorNaoResidente === 1
          || taxpayer.indicadorNaoResidente === 'true',
      },
    };

    try {
      await mkdir(cacheDir, { recursive: true, mode: 0o700 });
      await writeFile(cacheFile, JSON.stringify(payload), { mode: 0o600 });
    } catch (error) {
      console.error('[AGT consultarNIF] Cache:', error.message);
    }
    return result(200, payload);
  }

  static async exportContacts(req, res, next) {
    try {
      const companyId = validCompanyId(req);
      if (!companyId) {
        return res.status(401).json({ success: false, message: 'Sessão expirada. Faça login novamente.' });
      }

      const type = String(req.query.type || 'csv').toLowerCase();
      if (type !== 'csv') {
        return res.status(501).json({
          success: false,
          message: `Exportação ${type} ainda não foi migrada; apenas CSV está disponível.`,
        });
      }

      const contacts = await sequelize.query(
        `SELECT cp.name AS 'Nome da empresa', c.type AS 'Tipo', c.name AS 'Nome do Contacto',
                c.contributor AS 'Contribuinte', c.address AS 'Endereço', c.website AS 'Website',
                c.country AS 'País', c.city AS 'Cidade', c.email AS 'E-mail',
                c.telephone AS 'Telefone', c.cellphone AS 'Telemóvel', c.po_box AS 'Caixa Postal',
                c.fax AS 'Fax', c.pref_name AS 'Nome - Contato Preferencial',
                c.pref_email AS 'E-mail - Contato Preferencial',
                c.pref_telephone AS 'Telefone - Contato Preferencial',
                c.pref_cellphone AS 'Telemóvel - Contato Preferencial',
                CASE WHEN c.numberCopys = 1 THEN 'Original'
                     WHEN c.numberCopys = 2 THEN 'Duplicado'
                     WHEN c.numberCopys = 3 THEN 'Triplicado'
                     ELSE 'Desconhecido' END AS 'Nº de Cópias',
                c.observations,
                CASE WHEN c.due_date = 0 THEN 'Pronto pagamento'
                     ELSE CONCAT(c.due_date, ' Dias') END AS 'Vencimento',
                ct.name AS 'Idioma', p.name AS 'Metodo de Pagamento',
                CONCAT(cr.currency, ' (', cr.iso_code, ')') AS 'Moeda',
                c.created_at AS 'Criado em', c.updated_at AS 'Última Atualização'
         FROM contact c
         JOIN companies cp ON cp.id = c.company_id
         LEFT JOIN countries ct ON ct.iso = c.language
         LEFT JOIN payment_methods_contacts p ON p.code = c.payment_method
         LEFT JOIN currencies cr ON cr.iso_code = c.currency
         WHERE cp.id = :company_id`,
        { replacements: { company_id: companyId }, type: QueryTypes.SELECT },
      );
      if (contacts.length === 0) {
        return res.status(404).json({ success: false, message: 'Nenhum contato encontrado para exportação.' });
      }

      const headers = Object.keys(contacts[0]);
      const rows = contacts.map((contact) => headers.map((header) => csvCell(contact[header])).join(','));
      const csv = `\uFEFF${headers.map(csvCell).join(',')}\r\n${rows.join('\r\n')}\r\n`;
      res.status(200);
      res.set({
        'Content-Type': 'text/csv; charset=UTF-8',
        'Content-Disposition': 'attachment; filename="contatos.csv"',
        'Cache-Control': 'no-store',
      });
      return res.send(csv);
    } catch (error) {
      return next(error);
    }
  }

  static async testAgt(req, res) {
    const user = req.session?.user;
    const isAdmin = user && (user.adm === true || Number(user.adm) === 1 || user.role === 'admin');
    if (appConfig.env !== 'development' || !isAdmin) {
      return res.status(401).type('text/plain').send('Acesso restrito a administradores em desenvolvimento.\n');
    }

    const baseUrl = process.env.AGT_NIF_URL
      || process.env.AGT_URL
      || 'https://sifphml.minfin.gov.ao/sigt/contribuinte/consultarNIF/v5/obter';
    const username = nifEnv('AGT_NIF_USERNAME', 'AGT_USERNAME', 'AGT_USER');
    const password = nifEnv('AGT_NIF_PASSWORD', 'AGT_PASSWORD', 'AGT_TOKEN');
    const documentNumber = String(req.query.nif || '5000000000').replace(/[^A-Za-z0-9]/g, '');
    const documentType = String(req.query.tipo || 'NIF').replace(/[^A-Za-z]/g, '').toUpperCase();
    const hostname = new URL(baseUrl).hostname;
    const lines = [
      `Node.js ${process.version}`,
      'fetch: disponível',
      `Username: ${username ? `encontrado (${username.length} caracteres)` : 'NÃO ENCONTRADO'} | Password: ${password ? `encontrada (${password.length} caracteres)` : 'NÃO ENCONTRADA'}`,
    ];

    try {
      lines.push(`DNS ${hostname} => ${(await lookup(hostname)).address}`);
    } catch (error) {
      lines.push(`DNS ${hostname} => FALHA (${error.message})`);
    }
    if (!username || !password) {
      lines.push('Diagnóstico HTTP ignorado: credenciais AGT não configuradas.');
      return res.status(200).type('text/plain').send(`${lines.join('\n')}\n`);
    }

    const url = new URL(baseUrl);
    url.search = new URLSearchParams({
      tipoDocumento: documentType,
      numeroDocumento: documentNumber,
    }).toString();
    const authorization = `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`;
    const variants = [
      ['GET (Basic + Username/Password)', { Accept: 'application/json', Authorization: authorization, Username: username, Password: password }],
      ['GET (so Basic)', { Accept: 'application/json', Authorization: authorization }],
      ['GET (so Username/Password)', { Accept: 'application/json', Username: username, Password: password }],
    ];
    for (const [label, headers] of variants) {
      lines.push(`\n=== ${label} ${url.origin}${url.pathname} ===`);
      try {
        const response = await fetch(url, { headers, signal: AbortSignal.timeout(8000) });
        lines.push(`HTTP ${response.status}`);
        lines.push(`--- corpo (primeiros 1500 caracteres) ---\n${(await response.text()).slice(0, 1500)}`);
      } catch (error) {
        lines.push(`FALHA: ${error.message}`);
      }
    }
    return res.status(200).type('text/plain').send(`${lines.join('\n')}\n`);
  }

  static saveRequest(req) {
    return ContactsController.save(req.body, validCompanyId(req));
  }

  static updateRequest(req) {
    return ContactsController.update(req.body, validCompanyId(req));
  }

  static deleteRequest(req) {
    return ContactsController.delete(req.body, validCompanyId(req));
  }

  static changeStatusRequest(req) {
    return ContactsController.changeStatus(req.body, validCompanyId(req));
  }

  static fetchRequest(req) {
    return ContactsController.fetch(req.query, validCompanyId(req));
  }

  static getRequest(req) {
    return ContactsController.get(req.body);
  }

  static detailsRequest(req) {
    return ContactsController.details(req.body);
  }

  static geonamesCountriesRequest() {
    return ContactsController.geonamesCountries();
  }

  static geonamesCitiesRequest(req) {
    return ContactsController.geonamesCities(req.query);
  }

  static consultNifRequest(req) {
    return ContactsController.consultNif(req.body, req.session?.user);
  }

  static async testAgtRequest(req, res, next) {
    try {
      return await ContactsController.testAgt(req, res);
    } catch (error) {
      return next(error);
    }
  }

  static handlers = {
    save: respond(ContactsController.saveRequest),
    update: respond(ContactsController.updateRequest),
    delete: respond(ContactsController.deleteRequest),
    changeStatus: respond(ContactsController.changeStatusRequest),
    fetch: respond(ContactsController.fetchRequest),
    get: respond(ContactsController.getRequest),
    details: respond(ContactsController.detailsRequest),
    geonamesCountries: respond(ContactsController.geonamesCountriesRequest),
    geonamesCities: respond(ContactsController.geonamesCitiesRequest),
    consultNif: respond(ContactsController.consultNifRequest),
    exportContacts: ContactsController.exportContacts,
    testAgt: ContactsController.testAgtRequest,
  };
}
