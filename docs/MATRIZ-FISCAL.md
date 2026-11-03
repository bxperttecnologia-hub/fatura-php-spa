# Matriz técnica do módulo fiscal

**Revisão:** 30-10-2026  
**Âmbito:** aplicação PHP SPA em `fatura-php-spa`.  
**Limite:** esta matriz descreve o código observado; não é certificação AGT nem parecer fiscal.

## Fontes legais consultadas

| Diploma | Disposições verificadas | Fonte e observação |
|---|---|---|
| Decreto Presidencial n.º 71/25, de 20 de Março de 2025, Diário da República, I Série n.º 52, p. 11902 | Art. 4.º, n.º 9 (documentos que não são facturas); art. 8.º, n.os 4–9 (rectificação/anulação); art. 10.º (elementos obrigatórios); arts. 16.º–21.º (facturação electrónica, integridade e ciência do adquirente); arts. 25.º, 36.º–37.º (SAF-T, especificações e transição) | [Texto do diploma na Lex.ao](https://lex.ao/docs/presidente-da-republica/2025/decreto-presidencial-n-o-71-25-de-20-de-marco/). A página identifica a publicação no Diário da República. |
| Lei n.º 7/19, de 24 de Abril de 2019 | Aprovação do Código do IVA | [Texto na Lex.ao](https://lex.ao/docs/assembleia-nacional/2019/lei-n-o-7-19-de-24-de-abril/). É a lei de aprovação; não foi validada aqui uma versão consolidada com todas as alterações posteriores. |
| Lei n.º 19/14, de 22 de Outubro de 2014 | Aprovação do Código do Imposto Industrial | [Texto na Lex.ao](https://lex.ao/docs/assembleia-nacional/2014/lei-n-o-19-14-de-22-de-outubro/). É a lei de aprovação; não foi validada aqui uma versão consolidada com todas as alterações posteriores. |

O art. 36.º do Decreto Presidencial n.º 71/25 remete as especificações do sistema para Decreto Executivo. A versão aplicável dessa especificação e a data que determina a fase de obrigatoriedade do art. 37.º não foram confirmadas nesta revisão. Não foram, por isso, codificadas taxas legais, isenções, incidências ou elegibilidade de contribuintes por inferência.

## Fluxos activos observados

| Fluxo | Página SPA | JavaScript activo | Endpoint / geração | Cálculo ou integração |
|---|---|---|---|---|
| Criar/editar factura | `/invoices/create` → `app/pages/create_invoices.php` | `public/create_invoices/create_invoices.js` | `public/create_invoices/ajax/save_invoices.php`, `update_invoice.php`; finalização em `public/invoices/ajax/update_status.php` | `app/helpers/document_tax.php`, `invoice_helper.php`, `agt.php` |
| Nota de crédito | `/invoices/view` → `app/pages/invoice.php` | `public/invoices/invoice.js` | `public/invoices/ajax/create_credit_note.php`; PDF por `public/document_pdf.php` e `public/credit_notes/ajax/credit_note_public.php` | `document_tax.php`; sem comunicação AGT activa |
| Criar/editar proforma | `/proformas/create` → `app/pages/create_proform.php` | `public/create_proform/create_proform.js` | `public/create_proform/ajax/save_proform.php`, `update_proform.php` | `document_tax.php` |
| Ver/imprimir proforma | `/proformas/view` → `app/pages/proform.php` | `public/proform/invoice.js` | PDF por `public/document_pdf.php` e `public/proform/ajax/proform_public.php` | Totais são recalculados no backend |
| Configuração AGT | `/agt` → `app/pages/agt.php` | scripts da página AGT | `public/agt/ajax/agt.php` | `app/helpers/agt.php` e adaptador `agt-facturacao-api` |

## Matriz de documentos

| Documento | Suporte interno observado | Emissão/comunicação no código | Estado e controlo necessário |
|---|---|---|---|
| Factura (FT) | Sim; rascunho, finalização, hash local e PDF | A integração AGT só permite FT em ambiente de teste | Produção permanece desactivada. Emissão fiscal depende de software validado, elegibilidade e especificações aplicáveis. |
| Factura-recibo (FR) | Sim; tipo aceite no fluxo de factura | A integração AGT só permite FR em ambiente de teste | Mesmos bloqueios de certificação e elegibilidade da FT. |
| Factura simplificada | Não identificada nos tipos activos de emissão | Não enviada à AGT pelo fluxo activo | Pendente de análise legal e de implementação, se aplicável ao contribuinte. |
| Nota de crédito (NC) | Sim; cópia integral das linhas da factura, motivo obrigatório e PDF identificado como anulação integral | Não é atribuída série AGT nem comunicada pela integração activa | O fluxo actual é de anulação integral, não de rectificação parcial. Continua a faltar prova auditável de ciência do adquirente. Não declarar conformidade nem emitir como documento fiscal validado até completar esse controlo. |
| Nota de débito (ND) | Fluxo interno e PDF presentes | Não consta dos tipos de emissão activos AGT | O catálogo AGT não equivale a autorização. A nota de débito não deve liquidar imposto quando usada nos termos do art. 3.º, alínea m), do DP 71/25; os casos de uso precisam de validação fiscal. |
| Recibo (RG/RC) | Existem recibos internos e PDF | Não consta de `agt_invoice_document_types()` | Suporte interno não comprova comunicação ou autorização AGT. |
| Proforma (PF) | Sim; criação, edição, listagem e impressão | Sem envio AGT; usa referência comercial `PF` | O art. 4.º, n.º 9, alínea b), do DP 71/25 exclui a proforma da categoria de factura. A referência PF é comercial, não uma série fiscal. |
| Guia de entrega/transporte | Suporte interno e PDF | Sem comunicação AGT activa | Documento de suporte logístico, não tratado pelo fluxo como factura. |
| FA, FG, GF, AC, AR, TV, RE, AF, RP, RA, CS, LD | Aparecem no catálogo de tipos da integração | Não são tipos autorizados para criação de séries pelo fluxo activo | Catálogo técnico apenas; sem emissão ou activação automática. |

## Controlos e limitações verificadas

- `agt_default_document_types()` e `agt_invoice_document_types()` restringem a operação a FT/FR; `agt_enabled()` desactiva a emissão AGT fora de HML.
- A finalização da factura bloqueia a empresa e o documento em transacção, recusa documento já com hash e só aceita rascunhos. O hash local não prova que o código digital exigido pelo art. 19.º seja o definido pela AGT.
- Os handlers de gravação recalculam os totais usando taxas do artigo pertencente à empresa. O tratamento do IVA por regime no helper permanece dependente de validação fiscal; não é uma implementação legal certificada.
- A emissão de nota de crédito é transaccional, exige motivo e identifica no PDF a anulação integral; copia a factura integral e cancela o estado original. Não existe fluxo parcial nem mecanismo de prova auditável de ciência do cliente (DP 71/25, arts. 8.º e 21.º).
- A proforma não entra no fluxo de facturas/AGT. A referência é serializada por empresa e validada contra referências já existentes.
- Os PDFs privados verificam a empresa da sessão antes de obter os dados; a geração de nota de crédito restringe também factura, cliente e artigo à empresa.
- A migration `20261030000072-use-decimal-invoice-item-quantity.js` alarga a quantidade da linha de factura de inteiro para `DOUBLE`, preservando valores existentes. O rollback recusa a conversão se existirem quantidades fraccionárias.

## Pendências para validação externa

1. Obter e validar a versão aplicável do Decreto Executivo de especificações técnicas referido no art. 36.º do DP 71/25 e a fase/âmbito de obrigatoriedade aplicável a cada empresa.
2. Confirmar certificação/validação AGT do software, credenciais e autorização antes de qualquer ambiente de produção.
3. Validar com contabilista/jurista a classificação de serviços, incidência, base e taxa de retenção por prestador/adquirente e regime, bem como o tratamento do IVA.
4. Completar a nota de crédito com evidência auditável da ciência do adquirente; implementar rectificação parcial sem cancelar a factura original.
5. Validar todos os campos legais nos PDFs, SAF-T(AO), encadeamento/hash e séries contra as especificações AGT aplicáveis.
6. Executar migrations e cenários de concorrência/persistência numa base isolada MySQL. Nenhuma migration foi executada nesta revisão.

## Impostos documentais e estimativas

- Facturas, facturas-recibo e proformas guardam snapshots independentes do IVA: tipo, taxa, base, montante, descrição, confirmação, total bruto e valor líquido.
- A configuração é versionada por empresa e período de vigência. A activação exige que o administrador registe uma referência de validação legal; sem regra activa, o backend rejeita o imposto seleccionado.
- A taxa inicial de IPU (15%) é apenas um valor de configuração solicitado, não uma afirmação de taxa legal universal. A taxa, base e vigência têm de ser validadas antes da activação.
- A selecção só é aceite para linhas de serviço classificadas no catálogo e após confirmação do enquadramento pelo utilizador. A classificação e o tipo efectivo do artigo são recarregados do banco no backend; vendas de mercadorias não qualificam para IPU.
- O motor central calcula IVA separadamente, aplica a base configurada por tipo de imposto, arredonda a cêntimos com `PHP_ROUND_HALF_UP` e impede a duplicação da retenção na fonte documental com retenções por artigo.
- Proformas apresentam os valores como estimativas e não executam retenção, pagamento ou comunicação AGT. A conversão revalida a configuração na data da factura e recalcula os totais.
- Os snapshots preservam os valores históricos dos documentos; uma alteração administrativa de taxa cria nova vigência e não reescreve documentos existentes.
- `tests/document_tax_withholding_test.php` cobre os cálculos centrais, bases, IVA, descontos, elegibilidade, configuração inactiva, arredondamento e dedução duplicada. Fluxos com MySQL e renderização de PDF ainda devem ser validados numa base isolada.
