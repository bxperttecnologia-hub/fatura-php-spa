export const authMiddleware = (req, res, next) => {
  if (!req.session || !req.session.userId) {
    return res.status(401).json({
      success: false,
      message: 'Necessita de autenticação para aceder a este recurso.',
      errors: ['Unauthorized'],
    });
  }

  return next();
};
