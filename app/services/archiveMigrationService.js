const response = (statusCode, payload) => [statusCode, payload];

const migrationPending = (moduleName, spec) => response(501, {
  success: false,
  message: 'Migração ainda não concluída; a operação requer revisão da lógica PHP original.',
  migration_pending: true,
  module: moduleName,
  action: spec.action,
  ...(spec.contentType ? { expected_content_type: spec.contentType } : {}),
  ...(spec.fileName ? { expected_file_name: spec.fileName } : {}),
});

const isAuthenticated = (req) => Boolean(req.session?.userId || req.session?.user?.id);

const isAdministrator = (req) => {
  const user = req.session?.user;
  return Boolean(user?.adm === true || Number(user?.adm) === 1
    || user?.is_admin || user?.isAdministrator
    || ['admin', 'administrator', 'administrador'].includes(String(user?.role || '').toLowerCase()));
};

const fieldValue = (req, field) => {
  const name = typeof field === 'string' ? field : field.name;
  return req.body?.[name] ?? req.query?.[name] ?? req.params?.[name];
};

export const createArchiveHandler = ({
  moduleName,
  action,
  access = 'authenticated',
  required = [],
  diagnostic = false,
  spec = {},
}) => async (req, res, next) => {
  try {
    const send = (status, payload) => {
      if (diagnostic) {
        return res.status(status).type('text/plain').send(
          typeof payload === 'string' ? payload : payload.message,
        );
      }
      return res.status(status).json(payload);
    };

    if (access !== 'public' && !isAuthenticated(req)) {
      return send(401, { success: false, message: 'Sessão necessária.' });
    }
    if (access === 'admin' && !isAdministrator(req)) {
      return send(401, { success: false, message: 'Acesso administrativo necessário.' });
    }

    for (const field of required) {
      const value = fieldValue(req, field.name);
      if (value == null || String(value).trim() === '') {
        return send(422, {
          success: false,
          message: `O campo '${field.name}' é obrigatório.`,
        });
      }
      if (field.pattern && !field.pattern.test(String(value).trim())) {
        return send(field.invalidStatus || 422, {
          success: false,
          message: field.invalidMessage || `O campo '${field.name}' é inválido.`,
        });
      }
    }

    for (const name of ['id', 'employee_id', 'company_id']) {
      const value = req.body?.[name] ?? req.query?.[name];
      if (value != null && !/^[1-9]\d*$/.test(String(value))) {
        return send(422, { success: false, message: `${name} inválido.` });
      }
    }

    const [, pending] = migrationPending(moduleName, { ...spec, action });
    return send(501, diagnostic
      ? `TODO: ${pending.message} (${action})`
      : pending);
  } catch (error) {
    return next(error);
  }
};

export const buildArchiveHandlers = (moduleName, specs) => Object.fromEntries(
  specs.map((spec) => [spec.action, createArchiveHandler({
    moduleName,
    action: spec.action,
    access: spec.access || 'authenticated',
    required: spec.required || [],
    diagnostic: Boolean(spec.diagnostic),
    spec,
  })]),
);

export const registerArchiveRoutes = (router, specs, handlers, routeType = 'api') => {
  for (const spec of specs) {
    const routePath = routeType === 'legacy' ? spec.legacyPath : spec.apiPath;
    const method = spec.method.toLowerCase();
    router[method](routePath, handlers[spec.action]);
  }
  return router;
};

export const positiveIntegerField = (name = 'id') => ({
  name,
  pattern: /^[1-9]\d*$/,
  invalidMessage: `${name} inválido.`,
});

export const sixDigitCodeField = {
  name: 'code',
  pattern: /^\d{6}$/,
  invalidStatus: 401,
  invalidMessage: 'Código público inválido.',
};

export const oauthCodeField = {
  name: 'code',
  pattern: /^[A-Za-z0-9._~/-]{8,2048}$/,
  invalidStatus: 401,
  invalidMessage: 'Código OAuth inválido.',
};
