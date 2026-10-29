export const guestMiddleware = (req, res, next) => {
  if (req.session && req.session.userId) {
    return res.status(403).json({
      success: false,
      message: 'Já está autenticado.',
      errors: ['Already authenticated'],
    });
  }

  return next();
};
