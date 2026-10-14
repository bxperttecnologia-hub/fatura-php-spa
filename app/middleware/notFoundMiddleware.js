import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const notFoundPage = path.join(__dirname, '../../public/404.html');

export const notFoundMiddleware = (req, res) => {
  if (req.path.startsWith('/api/')) {
    return res.status(404).json({
      success: false,
      message: 'Recurso não encontrado.',
      errors: ['Not found'],
    });
  }

  return res.status(404).sendFile(notFoundPage);
};
