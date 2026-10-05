# RH — Fase 0 + Fase 1 (até este ponto)

Este zip contém a árvore completa que me enviaste (já com a Fase 0
aplicada) **mais** as alterações da Fase 1 feitas até agora.

## Novos ficheiros

- `sql/fase1_migration.sql` — cria `departments`, adiciona
  `department_id` a `positions`, adiciona `position_id` /
  `department_id` / `manager_id` a `employees`.
- `rh/migrations/migrate_fase1_position_id.php` — script CLI,
  idempotente, que preenche `employees.position_id` a partir do texto
  existente em `employees.position`. Suporta `--dry-run`.
- `rh/ajax/list_departments.php`, `save_department.php`,
  `delete_department.php` — CRUD de departamentos.
- `departments.php` — página de gestão de departamentos (mesmo padrão
  visual de `positions.php`).

## Ficheiros alterados

- `rh/ajax/search_employees.php` — JOIN por `position_id`, com
  fallback por texto (TRIM) só para quem ainda não foi migrado.
- `rh/ajax/delete_position.php` — mesma lógica id-primeiro/texto-
  fallback antes de bloquear a eliminação de um cargo em uso.
- `employees.php` — novos campos "Departamento" e "Chefia direta" no
  formulário de criação e de edição; nova coluna "Departamento" na
  tabela; JS para carregar/atualizar esses selects.
- `rh/ajax/list_employees.php` — passa a devolver `position_id`,
  `department_id`, `manager_id`, `department_name`, `manager_name`.
- `rh/ajax/save_employee.php` — resolve `position_id` a partir do
  texto do cargo (o `<select>` de cargo continua a enviar texto, sem
  mexer no frontend existente); valida `department_id`/`manager_id`
  (mesma empresa, sem auto-chefia, sem ciclos de chefia).
- `positions.php` — novo campo "Departamento" (opcional) no formulário
  de cargo e nova coluna "Departamento" na tabela.
- `rh/ajax/list_positions.php` — passa a devolver `department_id` e
  `department_name`.
- `rh/ajax/save_position.php` — valida e grava `department_id`.

## ORDEM DE DEPLOY (importante)

1. Faz backup da base de dados.
2. Corre `sql/fase1_migration.sql` na tua base.
3. Corre `php rh/migrations/migrate_fase1_position_id.php --dry-run`
   primeiro para ver o que vai acontecer, depois sem a flag para
   aplicar.
4. Só depois substitui os ficheiros PHP/JS pelos desta pasta.

## Por resolver / precisa da tua validação

- Não testado contra uma base de dados real (não tenho ligação MySQL
  neste ambiente) — revisto por leitura e lint (`php -l`), não
  executado.
- `payroll.php`, `ponto.php`, `vacations.php` ainda não filtram/mostram
  por departamento.
- Ponto pendente da Fase 0: ainda não sinalizei os registos de férias
  existentes com datas invertidas (end_date < start_date).
- Assunção que fiz e que pode não ser o que queres: o `<select>` de
  cargo no formulário de funcionário continua a enviar o NOME do cargo
  (texto), não o `position_id`. Resolvo o id no backend por
  correspondência de texto. Se preferires que o próprio select passe a
  enviar o id diretamente, é uma mudança pequena, mas fica para o próximo
  turno.
