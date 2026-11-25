# Matriz de conformidade — Notas de Entrega e Guias de Transporte

Data de análise: 2026-11-25

## Limitação desta análise

Durante esta implementação não foi possível obter o texto integral e verificável do Decreto Presidencial n.º 71/25, de 20 de Março, nem confirmar alterações posteriores em fontes oficiais acessíveis a partir do ambiente. Por isso, este módulo não simula comunicações à AGT, não atribui automaticamente IVA a guias de transporte e não declara que uma guia é fiscalmente equivalente a uma factura.

Os requisitos abaixo são separados em obrigação legal, boa prática operacional e implementação técnica. Antes de activar comunicação fiscal automática, um responsável fiscal deve validar o texto oficial aplicável à operação e a configuração fiscal da empresa.

## Diploma e fontes a validar

| Fonte | Utilização nesta implementação |
|---|---|
| Diário da República / Portal oficial | Confirmar texto consolidado do Decreto Presidencial n.º 71/25 e alterações posteriores |
| AGT — Administração Geral Tributária | Confirmar regimes de facturação electrónica, séries, submissões e documentos expedidos |
| Ministério das Finanças | Confirmar IVA, facturação, arquivo e responsabilidades aplicáveis |

## Matriz

| Tema | Classificação | Decisão implementada |
|---|---|---|
| Distinção entre nota de entrega e guia de transporte | Regra funcional de conformidade | Tabelas, estados e interfaces distintas; nenhuma guia cria factura |
| Nota de entrega como documento comercial | Boa prática até confirmação fiscal | Liga-se à factura existente e registra quantidades entregues |
| Guia de transporte como acompanhamento | Obrigação operacional a confirmar por regime | Regista origem, destino, transportador, veículo, motorista e documento de origem |
| Submissão à AGT | Não concluída sem texto oficial | Nenhuma submissão automática; integração não é simulada |
| Facturação posterior | Requer validação fiscal da operação | A nota permite controlar quantidade elegível; o BXPERT não fatura automaticamente |
| Evitar facturação duplicada | Integridade operacional | Quantidade entregue já existente é excluída da criação de notas a partir da factura |
| IVA numa guia | Não é inferido | Não apresenta IVA como liquidação fiscal da guia |
| Cancelamento | Integridade operacional | Cancelamento com motivo e historial; documentos expedidos/entregues não são silenciosamente apagados |
| Imutabilidade após emissão | Boa prática | O separador emitido não permite apagar; cancelamento exige fluxo próprio |
| Numeração | Integridade operacional | Máximo + 1 dentro da empresa e série, com bloqueio transacional |
| Stock na criação | Boa prática operacional | Rascunho não move stock |
| Saída física | Regra do módulo | Saída ocorre ao iniciar o transporte; entrega pode aumentar o armazém de destino |
| Entrega parcial | Operacional | Quantidades entregues e pendentes são controladas |
| Devolução | Operacional | Devolvida com movimento de entrada e historial |
| Arquivo | Boa prática/a exigência depende do diploma | PDF digital e eventos de impressão/reimpressão podem ser auditados |
| Permissões | Segurança técnica | Endpoints exigem sessão, empresa ativa e permissão de módulo |

## Alterações fiscais que continuam fora do âmbito

O módulo não determina unilateralmente:

- tipos documentais legalmente equivalentes;
- obrigação de comunicação à AGT;
- prazo obrigatório de emissão ou arquivo;
- requisitos de assinatura ou certificação;
- casos em que uma guia substitui ou acompanha factura;
- taxa ou valor de IVA aplicável a uma operação.
