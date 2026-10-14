export const errorMiddleware = (logger) => (error, _req, res, _next) => {
  logger.error({ err: error }, 'Unhandled error');

  const statusCode = error.statusCode || 500;
  const message = statusCode === 500 ? 'Não foi possível processar o pedido.' : error.message;

  res.status(statusCode).json({
    success: false,
    message,
    errors: error.errors || [],
  });
};
