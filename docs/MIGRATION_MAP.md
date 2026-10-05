# MIGRATION_MAP — Passo 1 de Reorganização

**Gerado:** 2026-10-05  
**Estado:** Inventário e Plano (SEM movimentos aplicados ainda)

## Princípios
- Mover endpoints AJAX de `_import/<modulo>/ajax/*.php` para `app/modules/<modulo>/ajax/*.php`
- Centralizar estáticos (CSS, JS, fonts) em `public/assets/{css,js,img,fonts}`
- Criar despachante único `public/ajax.php` para resolver `^/(\w+)/ajax/(\w+)\.php$`
- Criar `config/modules.php` com metadados (públicos, downloads, webhooks)
- Manter URLs funcionais para código legado (bridge via `legacy.js`)
- **Nenhum ficheiro será apagado até estar arquivado**

---

## 1. ENDPOINTS AJAX POR MÓDULO

### assets (shell + nav)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `get_companies.php` | `_import/index/ajax/` | `app/modules/assets/ajax/` | Mover | **Público?** Validar se precisa sessão |
| `get_company_limits.php` | `_import/index/ajax/` (não encontrado) | `app/modules/assets/ajax/` | Criar | Chamado por `nav.php` linha 808 |
| `company_data.php` | `_import/index/ajax/` (não encontrado) | `app/modules/assets/ajax/` | Criar | Chamado por `nav.php` linha 827 |
| `change_company.php` | `_import/index/ajax/` (não encontrado) | `app/modules/assets/ajax/` | Criar | Chamado por `nav.php` linha 844 |
| `proxy_company_logo.php` | `_import/index/ajax/` (não encontrado) | `app/modules/assets/ajax/` | Criar | Mencionado no regras (serve uploads) |

### index (dashboard)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `data_user_notify.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | Idêntico a `send_invoice.php`? (ver **Passo 6**) |
| `data_company_notify.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_notifications.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `notifications_actions.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_ai_insights.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_daily_report.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_monthly_report.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_sell_insights.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_hr_insights.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_intelligence_customers.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_intelligence_health.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_intelligence_my_day.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_stock_dashboard.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `get_invoice_data.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `check_contribuitor.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `copilot_ask.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `ai_action_confirm.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `fetch_stock_data.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `fetch_stocks.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | |
| `test_stock.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | Debug: arquivar em `_archive/` |
| `export_credit_notes.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | **Download** (sem JSON header) |
| `export_debit_notes.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | **Download** |
| `export_receipts.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | **Download** |
| `export_sales_report.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | **Download** |
| `faturas_export.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | **Download** (duplicado em `/invoices/ajax/`) |
| `generate_saft.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | **Download** (SAF-T fiscal) |
| `export_helpers.php` | `_import/index/ajax/` | `app/modules/index/ajax/` | Mover | Ficheiro helper |
| `nif_soap.php` | `_import/index/` | `app/modules/index/lib/` | Mover | Não é AJAX: é helper |

### contacts (clientes/empresas)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `fetch_contacts.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | |
| `get_contact.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | |
| `save_contact.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | Validar `company_id` da sessão |
| `update_contact.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | Validar `company_id` da sessão |
| `delete_contact.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | Validar autorização |
| `change_status.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | |
| `details_contact.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | |
| `export_contacts.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | **Download** |
| `geonames_countries.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | **Público** (geonames API) |
| `geonames_cities.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | **Público** (geonames API) |
| `consult_nif.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | (consultoria de NIF: AGT?) |
| `test_agt.php` | `_import/contacts/ajax/` | `app/modules/contacts/ajax/` | Mover | Debug: arquivar em `_archive/` |

### items (artigos/produtos)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `get_items.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | |
| `save_item.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | Validar `company_id` da sessão |
| `edit_item.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | Validar `company_id` da sessão |
| `delete_item.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | Validar autorização |
| `delete_items_bulk.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | Validar autorização |
| `update_item_inline.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | |
| `generate_code.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | |
| `check_code.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | |
| `export_items.php` | `_import/items/ajax/` | `app/modules/items/ajax/` | Mover | **Download** |
| `debug_post.txt` | `_import/items/ajax/` | `_archive/debug/` | Arquivar | Debug: não é código |

### invoices (faturas)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `fetch_invoices.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `get_invoice.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `get_invoice_data.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | Duplicado em `/proform/ajax/` (ver **Passo 6**) |
| `get_last_invoice.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `get_last_receipt.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | Duplicado em `/proform/ajax/` |
| `get_invoices_feed.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | Duplicado em `/proform/ajax/` |
| `update_status.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | Duplicado em `/proform/ajax/` |
| `send_invoice.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | **SEGURANÇA**: remover senha SMTP hardcoded |
| `registrar_pagamento.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `delete_invoice.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | Validar autorização |
| `cancel_invoice.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `clone_invoice.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `final_invoice.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `invoice_public.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | **Público**: visualizar fatura sem sessão |
| `faturas_export.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | **Download** (duplicado em `/index/ajax/`) |
| `fetch_receipts.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `fetch_credit_notes.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `create_credit_note.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | Validar autorização |
| `credit_notes.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `fetch_debit_notes.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `create_debit_note.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | Validar autorização |
| `debit_notes.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `fetch_delivery_notes.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `create_delivery_note.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `delivery_notes.php` | `_import/invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `invoice.css` | `_import/invoices/` | `public/assets/css/modules/invoices.css` | Mover | |
| `invoice_footer.css` | `_import/invoices/` | `public/assets/css/modules/invoice_footer.css` | Mover | Rever se é usado com `invoice.css` |
| `list_invoices.css` | `_import/invoices/` | `public/assets/css/modules/list_invoices.css` | Mover | |
| `invoice.js` | `_import/invoices/` | `public/assets/js/modules/invoices/invoice.js` | Mover | |
| `invoice.js.bak.*` | `_import/invoices/` | `_archive/backups/` | Arquivar | Backup antigo |
| `list_invoices.js` | `_import/invoices/` | `public/assets/js/modules/invoices/list.js` | Mover | |

### create_invoices
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `get_items.php` | `_import/create_invoices/ajax/` | `app/modules/invoices/ajax/create_get_items.php` | Mover | Fundido com `/items/ajax/get_items.php`? |
| `save_invoices.php` | `_import/create_invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `update_invoice.php` | `_import/create_invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | Duplicado com `/invoices/ajax/update_invoice.php`? |
| `anonymous_contact.php` | `_import/create_invoices/ajax/` | `app/modules/invoices/ajax/` | Mover | |
| `save_invoice-1.php` | `_import/create_invoices/` | `_archive/deprecated/` | Arquivar | Backup/deprecated |
| `create_invoices.css` | `_import/create_invoices/` | `public/assets/css/modules/create_invoices.css` | Mover | |
| `create_invoices.js` | `_import/create_invoices/` | `public/assets/js/modules/invoices/create.js` | Mover | |

### proform (proformas)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `fetch_proforms.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | |
| `get_proform.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | Versão diferente de `/invoices/ajax/get_proform.php`? |
| `get_invoice_data.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | Duplicado com `/invoices/ajax/` |
| `get_invoices_feed.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | Duplicado com `/invoices/ajax/` |
| `get_last_receipt.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | Duplicado com `/invoices/ajax/` |
| `update_status.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | Duplicado com `/invoices/ajax/` |
| `convert_proforma.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | |
| `create_credit_note.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | Duplicado com `/invoices/ajax/` |
| `delete_invoice.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | **ATENÇÃO**: validar `company_id` (regra 4 da Fase B) |
| `proform_public.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | **Público** |
| `proformas_export.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | **Download** |
| `registrar_pagamento.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | Duplicado com `/invoices/ajax/` |
| `send_invoice.php` | `_import/proform/ajax/` | `app/modules/proform/ajax/` | Mover | Duplicado com `/invoices/ajax/` |
| `invoice.css` | `_import/proform/` | `public/assets/css/modules/proform.css` | Mover | Versão diferente de `/invoices/invoice.css`? |
| `invoice_footer.css` | `_import/proform/` | `public/assets/css/modules/proform_footer.css` | Mover | |
| `invoice.js` | `_import/proform/` | `public/assets/js/modules/proform/invoice.js` | Mover | Versão diferente de `/invoices/invoice.js`? |
| `invoice.js.bak.*` | `_import/proform/` | `_archive/backups/` | Arquivar | |
| `list_proforms.js` | `_import/proform/` | `public/assets/js/modules/proform/list.js` | Mover | |
| `recibo_pdf.php` | `_import/proform/` | `app/modules/proform/pdf/` | Mover | Gerador de PDF |

### guides (guias de remessa)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `list_guides.php` | `_import/guides/ajax/` | `app/modules/guides/ajax/` | Mover | |
| `get_guide.php` | `_import/guides/ajax/` | `app/modules/guides/ajax/` | Mover | |
| `get_items.php` | `_import/guides/ajax/` | `app/modules/guides/ajax/` | Mover | |
| `save_guide.php` | `_import/guides/ajax/` | `app/modules/guides/ajax/` | Mover | |
| `generate_pdf.php` | `_import/guides/ajax/` | `app/modules/guides/ajax/` | Mover | **Download** (PDF) |
| `send_guide.php` | `_import/guides/ajax/` | `app/modules/guides/ajax/` | Mover | **SEGURANÇA**: remover senha SMTP |
| `guide_public.php` | `_import/guides/ajax/` | `app/modules/guides/ajax/` | Mover | **Público** |
| `guides.css` | `_import/guides/` | `public/assets/css/modules/guides.css` | Mover | |
| `guides_public.css` | `_import/guides/` | `public/assets/css/modules/guides_public.css` | Mover | |
| `guides.js` | `_import/guides/` | `public/assets/js/modules/guides/list.js` | Mover | |
| `view_guides.js` | `_import/guides/` | `public/assets/js/modules/guides/view.js` | Mover | |

### credit_notes
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `generate_pdf.php` | `_import/credit_notes/ajax/` | `app/modules/credit_notes/ajax/` | Mover | **Download** |
| `credit_note_public.php` | `_import/credit_notes/ajax/` | `app/modules/credit_notes/ajax/` | Mover | **Público** |

### stock
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `stock_items.php` | `_import/stock/ajax/` | `app/modules/stock/ajax/` | Mover | |
| `move_item.php` | `_import/stock/ajax/` | `app/modules/stock/ajax/` | Mover | |
| `delete_item.php` | `_import/stock/ajax/` | `app/modules/stock/ajax/` | Mover | |
| `stock_controller.php` | `_import/stock/ajax/` | `app/modules/stock/ajax/` | Mover | |
| `company_logo.php` | `_import/stock/ajax/` | `app/modules/stock/ajax/` | Mover | **SEGURANÇA**: mover upload para `/files/` endpoint |
| `stock.js` | `_import/stock/` | `public/assets/js/modules/stock/list.js` | Mover | |
| `view.js` | `_import/stock/` | `public/assets/js/modules/stock/view.js` | Mover | |
| `style.css` | `_import/stock/` | `public/assets/css/modules/stock.css` | Mover | |

### rh (recursos humanos) — **Maior módulo**
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `list_employees.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `get_dashboard_stats.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_employee.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | **SEGURANÇA**: upload de ficheiros → `/storage/uploads/employees/` |
| `search_employees.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `delete_employee.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `delete_employee_file.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | **SEGURANÇA**: ler de `/storage/uploads/` |
| `list_departments.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_department.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `delete_department.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `list_positions.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_position.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `delete_position.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `list_managers.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `get_org_chart.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `list_payroll.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `get_payroll.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_payroll.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `generate_monthly_payroll.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `calculate_payroll_batch.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `issue_payroll_batch.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `get_suggested_subsidies.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `simular_rescisao.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `desligar_funcionario.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `list_attendance.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_attendance.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `delete_attendance.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `list_vacations.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `get_vacation.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_vacation.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `delete_vacation.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `vacation_ajax.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `update_vacation_status.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `list_evaluation_templates.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_evaluation_template.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `delete_evaluation_template.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `list_evaluation_cycles.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_evaluation_cycle.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `generate_evaluations.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `list_evaluations.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `get_evaluation.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `get_employee_evaluation_history.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `save_evaluation_answers.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `get_alerts.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | |
| `export_employees_pdf.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | **Download** |
| `export_all_payroll_pdf.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | **Download** |
| `export_vacations_pdf.php` | `_import/rh/ajax/` | `app/modules/rh/ajax/` | Mover | **Download** |
| `payroll_calc.php` | `_import/rh/helpers/` | `app/modules/rh/lib/` | Mover | Helper |
| `rh_helpers.php` | `_import/rh/lib/` | `app/modules/rh/lib/` | Mover | Helper |
| `rescisao.php` | `_import/rh/lib/` | `app/modules/rh/lib/` | Mover | Helper |
| `recibo.php` | `_import/rh/pdf/` | `app/modules/rh/pdf/` | Mover | Gerador de PDF |
| `folha_export.php` | `_import/rh/export/` | `app/modules/rh/export/` | Mover | Gerador de export |
| `mapa_inss.php` | `_import/rh/export/` | `app/modules/rh/export/` | Mover | Gerador de export fiscal |
| `provisoes.php` | `_import/rh/export/` | `app/modules/rh/export/` | Mover | Gerador de export |
| `migrate_fase1_position_id.php` | `_import/rh/migrations/` | `database/migrations/rh/` | Mover | Migração de DB |

### manage_users
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `get_manage_users.php` | `_import/manage_users/ajax/` | `app/modules/manage_users/ajax/` | Mover | Validar `company_id` da sessão |
| `check_user.php` | `_import/manage_users/ajax/` | `app/modules/manage_users/ajax/` | Mover | |
| `create_collaborator.php` | `_import/manage_users/ajax/` | `app/modules/manage_users/ajax/` | Mover | **SEGURANÇA**: remover senha SMTP |
| `update_role.php` | `_import/manage_users/ajax/` | `app/modules/manage_users/ajax/` | Mover | Validar `company_id` e autorização |
| `unlink_user.php` | `_import/manage_users/ajax/` | `app/modules/manage_users/ajax/` | Mover | Validar autorização |
| `manage_users.js` | `_import/manage_users/` | `public/assets/js/modules/manage_users/list.js` | Mover | |

### perfil (profile do utilizador)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `update_profile.php` | `_import/perfil/ajax/` | `app/modules/perfil/ajax/` | Mover | |
| `upload.php` | `_import/perfil/ajax/` | `app/modules/perfil/ajax/` | Mover | **SEGURANÇA**: upload para `/storage/uploads/profiles/` |
| `perfil.js` | `_import/perfil/` | `public/assets/js/modules/perfil/index.js` | Mover | |

### edit_company
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `update_company.php` | `_import/edit_company/ajax/` | `app/modules/edit_company/ajax/` | Mover | Validar `company_id` da sessão |
| `edit_company.js` | `_import/edit_company/` | `public/assets/js/modules/edit_company/index.js` | Mover | |

### subscription (gestão de planos)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `list_orders.php` | `_import/subscription/ajax/` | `app/modules/subscription/ajax/` | Mover | |
| `create_order.php` | `_import/subscription/ajax/` | `app/modules/subscription/ajax/` | Mover | Validar `company_id` da sessão |
| `set_plan.php` | `_import/subscription/ajax/` | `app/modules/subscription/ajax/` | Mover | Validar `company_id` da sessão |
| `create_charge.php` | `_import/subscription/ajax/` | `app/modules/subscription/ajax/` | Mover | **SEGURANÇA**: credenciais AppyPay via `.env` |
| `mark_paid.php` | `_import/subscription/ajax/` | `app/modules/subscription/ajax/` | Mover | |
| `sync_orders.php` | `_import/subscription/ajax/` | `app/modules/subscription/ajax/` | Mover | **SEGURANÇA**: credenciais AppyPay via `.env` |
| `test_appypay_token.php` | `_import/subscription/ajax/` | `app/modules/subscription/ajax/` | Mover | Debug: arquivar em `_archive/` |
| `appypay.php` (webhook) | `_import/subscription/webhook/` | `app/modules/subscription/webhook/` | Mover | **Público** + validar assinatura do provedor |

### login (autenticação)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `process_login.php` | `_import/login/ajax/` | `app/modules/login/ajax/` | Mover | Já tem endpoint em `api.php` (`auth/login`)? |
| `forgot_send.php` | `_import/login/ajax/` | `app/modules/login/ajax/` | Mover | **SEGURANÇA**: remover senha SMTP |
| `forgot_verify.php` | `_import/login/ajax/` | `app/modules/login/ajax/` | Mover | |
| `forgot_reset.php` | `_import/login/ajax/` | `app/modules/login/ajax/` | Mover | |
| `notify.php` | `_import/login/ajax/` | `app/modules/login/ajax/` | Mover | |
| `login.js` | `_import/login/` | `public/assets/js/modules/login/index.js` | Mover | Já tem página nativa? |
| `forgot.js` | `_import/login/` | `public/assets/js/modules/login/forgot.js` | Mover | |

### register (registo de contas)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|---------|------|-------|
| `process_register.php` | `_import/register/ajax/` | `app/modules/register/ajax/` | Mover | Página pública em `public/register.php` |
| `send_otp.php` | `_import/register/ajax/` | `app/modules/register/ajax/` | Mover | **SEGURANÇA**: remover senha SMTP |
| `verify_otp.php` | `_import/register/ajax/` | `app/modules/register/ajax/` | Mover | |
| `set_password.php` | `_import/register/ajax/` | `app/modules/register/ajax/` | Mover | |
| `verifica_username.php` | `_import/register/ajax/` | `app/modules/register/ajax/` | Mover | |
| `debug_conexao.php` | `_import/register/ajax/` | `_archive/debug/` | Arquivar | Debug |
| `register.js` | `_import/register/` | `public/assets/js/modules/register/index.js` | Mover | |

### loginGoogle (Google OAuth)
| Ficheiro | Origem | Destino | Tipo | Notas |
|----------|--------|------__|------|-------|
| `processLoginGoogle.php` | `_import/loginGoogle/` | `app/modules/loginGoogle/` | Mover | Callback OAuth |
| `verificarUsuario.php` | `_import/loginGoogle/` | `app/modules/loginGoogle/` | Mover | Validação de utilizador |
| `callback.php` | `_import/loginGoogle/` | `app/modules/loginGoogle/callback.php` | Mover | **Público** + validar assinatura |
| `loginGoogle.php` | `_import/loginGoogle/` | `public/assets/js/modules/loginGoogle/` | Mover | Pode ser JS ou PHP? |

---

## 2. REFERÊNCIAS EM FICHEIROS DE CÓDIGO

### Ficheiros `app/pages/*.php` que referenciam CSS/JS/imagens
```
app/pages/contacts.php:
  - require '../contacts/partials/contact_form_modal.php'
  - <link rel="stylesheet" href="contacts/contacts.css?v=1.0">
  - <script src="contacts/contacts.js?v=1.0"></script>
  - <script src="contacts/register_contact.js?v=1.0"></script>
```

**AÇÃO**: Todos estes paths relativos (`contacts/`, `invoices/`, `rh/`, etc.) devem ser convertidos para `/assets/js/modules/<modulo>/` e `/assets/css/modules/<modulo>/` ou `/assets/css/modules/<modulo>.css`.

### Ficheiros `app/partials/*` que referenciam estáticos
```
app/partials/head.php:
  - <link rel="stylesheet" href="/assets/css/style.css?v=0.3">  ✓ Absoluto
  - asset_if_exists('assets/css/side.css')  ✓ Relativo → /assets/css/side.css
  - asset_if_exists('assets/css/support-chat.css')  ✓ Relativo
  - asset_if_exists('assets/js/script.js')  ✓ Relativo
  - asset_if_exists('assets/js/support-chat.js')  ✓ Relativo
```

**AÇÃO**: Manter como estão (paths já são robustos com `asset_if_exists()`).

### Ficheiros `app/partials/nav.php` que carregam endpoints AJAX
```
line 808:  /assets/ajax/get_company_limits.php
line 827:  /assets/ajax/company_data.php
line 844:  /assets/ajax/change_company.php
line 946:  /index/ajax/data_user_notify.php
line 947:  /index/ajax/data_company_notify.php
line 959:  /index/ajax/get_notifications.php
line 1070: /index/ajax/copilot_ask.php
```

**AÇÃO**: Todos estes continuarão a funcionar após o despachante `public/ajax.php` ser instalado (transparente).

---

## 3. FICHEIROS A ARQUIVAR (NÃO APAGAR)

| Ficheiro | Origem | Destino | Motivo |
|----------|--------|---------|--------|
| `.bak.20260313_*` | Vários | `_archive/backups/` | Backups antigos |
| `debug_post.txt` | `_import/items/ajax/` | `_archive/debug/` | Debug |
| `debug_conexao.php` | `_import/register/ajax/` | `_archive/debug/` | Debug |
| `test_stock.php` | `_import/index/ajax/` | `_archive/debug/` | Debug |
| `test_agt.php` | `_import/contacts/ajax/` | `_archive/debug/` | Debug |
| `test_appypay_token.php` | `_import/subscription/ajax/` | `_archive/debug/` | Debug |
| `save_invoice-1.php` | `_import/create_invoices/` | `_archive/deprecated/` | Deprecated |
| `*router*.js` | `public/assets/js/` (se existir) | `_archive/old-routers/` | Routers antigos |

---

## 4. ESTRUTURA ALVO

```
project/
├── app/
│   ├── modules/
│   │   ├── assets/ajax/*.php
│   │   ├── index/ajax/*.php
│   │   ├── contacts/ajax/*.php
│   │   ├── items/ajax/*.php
│   │   ├── invoices/
│   │   │   ├── ajax/*.php
│   │   │   └── pdf/*.php
│   │   ├── proform/ajax/*.php
│   │   ├── guides/
│   │   │   ├── ajax/*.php
│   │   │   └── pdf/guide_pdf.php
│   │   ├── credit_notes/ajax/*.php
│   │   ├── stock/ajax/*.php
│   │   ├── rh/
│   │   │   ├── ajax/*.php
│   │   │   ├── lib/ (helpers, payroll_calc, rescisao, etc.)
│   │   │   ├── export/ (folha, mapa_inss, provisoes)
│   │   │   └── pdf/recibo.php
│   │   ├── manage_users/ajax/*.php
│   │   ├── perfil/ajax/*.php
│   │   ├── edit_company/ajax/*.php
│   │   ├── subscription/
│   │   │   ├── ajax/*.php
│   │   │   └── webhook/appypay.php  (público)
│   │   ├── login/ajax/*.php
│   │   ├── register/ajax/*.php
│   │   └── loginGoogle/*.php
│   └── ... (resto inalterado)
│
├── config/
│   ├── modules.php  (novo: metadados de módulos)
│   └── ... (resto inalterado)
│
├── database/
│   └── migrations/
│       └── rh/migrate_fase1_position_id.php
│
├── public/
│   ├── ajax.php  (novo: despachante)
│   ├── assets/
│   │   ├── css/
│   │   │   ├── base.css
│   │   │   ├── layout.css
│   │   │   ├── vendor/
│   │   │   └── modules/
│   │   │       ├── invoices.css
│   │   │       ├── create_invoices.css
│   │   │       ├── proform.css
│   │   │       ├── guides.css
│   │   │       ├── stock.css
│   │   │       ├── rh.css
│   │   │       └── ...
│   │   ├── js/
│   │   │   ├── core/
│   │   │   ├── vendor/
│   │   │   ├── legacy/
│   │   │   ├── modules/
│   │   │   │   ├── invoices/
│   │   │   │   ├── guides/
│   │   │   │   ├── rh/
│   │   │   │   └── ...
│   │   │   └── pages/
│   │   ├── img/
│   │   │   ├── brand/
│   │   │   ├── icons/
│   │   │   ├── defaults/
│   │   │   └── profiles/
│   │   └── fonts/
│   │       └── quarion/
│   ├── .htaccess  (atualizar: rota `/(\w+)/ajax/(\w+)\.php$`)
│   └── ... (resto inalterado)
│
├── storage/
│   └── uploads/  (novo: fora da webroot)
│       ├── companies/  (logos)
│       ├── employees/  (ficheiros de RH)
│       └── profiles/   (fotos de perfil)
│
├── _archive/  (novo: não deploy)
│   ├── backups/
│   ├── debug/
│   └── deprecated/
│
└── ... (resto inalterado)
```

---

## 5. FICHEIROS/PASTAS A CRIAR

| Ficheiro/Pasta | Propósito |
|-----------------|-----------|
| `public/ajax.php` | Despachante único para `/(\w+)/ajax/(\w+)\.php` |
| `config/modules.php` | Metadados: módulo → {públicos[], downloads[], webhooks[]} |
| `app/lib/Mailer.php` | Centralizado: SMTP com env vars (sem hardcode) |
| `app/modules/*/` | Todas as pastas de módulos |
| `public/assets/css/base.css` | Estilos base (separado de `style.css` de 25 KB) |
| `public/assets/css/layout.css` | Estilos de layout |
| `public/assets/css/modules/` | CSS de módulos |
| `public/assets/js/modules/` | JS de módulos |
| `public/assets/img/brand/`, `icons/`, `defaults/` | Imagens organizadas |
| `public/assets/fonts/quarion/` | Fontes Quarion |
| `public/assets/fonts/fonts.css` | @font-face para Quarion |
| `storage/uploads/` | Uploads de utilizadores (fora da webroot) |
| `_archive/` | Ficheiros antigos (não no git) |

---

## 6. QUESTÕES PARA VALIDAÇÃO

Antes de prosseguir ao **Passo 2** (limpeza e movimento), confirma:

1. **Endpoints públicos**: Quais destes são realmente públicos (sem sessão)?
   - `geonames_countries.php`, `geonames_cities.php` ✓ Sim
   - `invoice_public.php`, `proform_public.php`, `guide_public.php`, `credit_note_public.php`, `callback.php` ✓ Sim
   - Outros?

2. **Endpoints de download** (sem JSON header, com file attachment):
   - Todos os `*_export.php` ✓
   - `generate_pdf.php` ✓
   - Outros?

3. **Webhooks públicos** (sem CSRF, validam assinatura):
   - `subscription/webhook/appypay.php` ✓
   - Outros?

4. **Duplicados a fundir** (ver **Passo 6**):
   - `/invoices/ajax/get_invoice_data.php` vs `/proform/ajax/get_invoice_data.php` — versões diferentes?
   - `/invoices/ajax/send_invoice.php` vs `/proform/ajax/send_invoice.php` — versões diferentes?
   - `/invoices/invoice.js` vs `/proform/invoice.js` — versões diferentes?
   - `index/ajax/faturas_export.php` vs `invoices/ajax/faturas_export.php` — duplicado?

5. **Helpers e libs legadas**:
   - Todos em `_import/rh/helpers/`, `_import/rh/lib/`, etc. — mover para `app/modules/*/lib/`?
   - Também em `/helpers/` na raiz — consolidar ou manter separados?

6. **Contactos/empresas**:
   - As referências em `app/pages/contacts.php` a `contacts/` subpasta (CSS, JS, modal) — todos em `_import/contacts/`?
   - Existe ficheiro `_import/contacts/partials/contact_form_modal.php`?

Responde e confirma que o plano acima está correto para prosseguirmos ao **Passo 2**.

