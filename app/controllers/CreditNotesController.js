import { notImplemented } from './archiveModule.js';

export class CreditNotesController {
  static handlers = {
    generatePdf: notImplemented('credit_notes', 'generatePdf', { requiresId: true }),
    creditNotePublic: notImplemented('credit_notes', 'creditNotePublic', {
      publicAccess: true,
      requiresId: true,
    }),
  };
}
