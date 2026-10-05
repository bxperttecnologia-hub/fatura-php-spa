<?php
require_once '../app/views/layout_creation.php';
$company_id = $_GET['company_id'];
?>

<style>
    /* ===== TABELA ESTILO ===== */
    #usersTable {
        border-collapse: separate;
        border-spacing: 0 12px;
        width: 100%;
    }

    /* HEADER */
    #usersTable thead th {
        border: none;
        font-size: 12px;
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 12px 16px;
        text-align: left;
        border-right: 1px solid #e5e7eb57;
    }

    #usersTable thead th:last-child {
        border-right: none;
    }

    #usersTable tbody tr td {
        border-right: 1px solid #e5e7eb57;
    }

    /* ROW */
    #usersTable tbody tr {
        background: #fff !important;
        border-radius: 14px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        text-align: left !important;
    }


    /* HOVER PRO */
    #usersTable tbody tr:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }

    /* CELLS */
    #usersTable tbody td {
        border: none;
        padding: 18px 16px;
        vertical-align: middle;
        font-size: 0.95rem;
        background: #fff !important;
        text-align: left !important;
    }

    #usersTable thead td {
        background: #111 !important;
        display: none;
        max-width: 80px !important;
    }

    /* BORDAS ARREDONDADAS */
    #usersTable tbody td:first-child {
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
        background: #fff !important;
    }

    #usersTable tbody th {
        text-align: left !important;
    }

    #usersTable tbody td:last-child {
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
        text-align: right;
        padding-right: 24px;
    }

    /* ===== NOME (PRINCIPAL) ===== */
    #usersTable tbody td:first-child {
        font-weight: 600;
        color: #111;
    }

    /* SUBINFO */
    #usersTable tbody td small {
        display: block;
        color: #6b7280;
    }
</style>

<body>
    <main>
        <div class="container mt-5">
            <h2 class="mb-4">Gestão de Usuários</h2>
            <div class="mb-3">
                <button type="button" id="addCollaboratorBtn" class="btn btn-outline-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Adicionar Colaborador">
                    Adicionar Colaborador <span class="material-icons-round ms-2" style="vertical-align: middle;">person_add</span>
                </button>
            </div>

            <!-- Modal 1: Verificar email -->
            <div class="modal fade" id="checkCollaboratorModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Adicionar colaborador</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="checkCollaboratorForm">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Email do colaborador</label>
                                    <input type="email" class="form-control" name="email" placeholder="email@exemplo.com" required autocomplete="off" inputmode="email">
                                    <div class="form-text">Digite o email e clique em Continuar para verificar se já existe conta no sistema.</div>
                                </div>
                                <!-- loading removido a pedido -->
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="checkContinueBtn" disabled>Continuar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal 2A: Usuário existente (vincular) -->
            <div class="modal fade" id="linkCollaboratorModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Vincular colaborador</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="linkCollaboratorForm">
                            <div class="modal-body">
                                <div class="alert alert-info mb-3" id="linkUserInfo">
                                    Este colaborador já possui conta. Vamos apenas vincular à empresa.
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Nome</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" readonly required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" placeholder="Opcional (se vazio, geramos automaticamente)">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Perfil (hierarquia)</label>
                                        <select class="form-select" name="role" required>
                                            <option value="employee">Funcionário</option>
                                            <option value="viewer">Visualizador</option>
                                            <option value="admin">Administrador</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="is_active" required>
                                            <option value="1" selected>Ativo</option>
                                            <option value="0">Inativo</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-text mt-2">
                                    Um email será enviado ao colaborador com um link para definir a própria senha de acesso.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Vincular</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal 2B: Usuário novo (criar) -->
            <div class="modal fade" id="createCollaboratorModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Criar colaborador</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="createCollaboratorForm">
                            <div class="modal-body">
                                <div class="alert alert-warning mb-3">
                                    Não encontramos conta com este email. Vamos criar um novo usuário.
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Nome</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" readonly required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="username" placeholder="Opcional (se vazio, geramos automaticamente)">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Perfil (hierarquia)</label>
                                        <select class="form-select" name="role" required>
                                            <option value="employee">Funcionário</option>
                                            <option value="viewer">Visualizador</option>
                                            <option value="admin">Administrador</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="is_active" required>
                                            <option value="1" selected>Ativo</option>
                                            <option value="0">Inativo</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-text mt-2">
                                    Um email será enviado ao colaborador com um link para definir a própria senha de acesso.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Criar e vincular</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Modal: Mudar Role -->
            <div class="modal fade" id="changeRoleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Mudar role</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="changeRoleForm">
                            <div class="modal-body">
                                <input type="hidden" name="user_id" id="changeRoleUserId">
                                <div class="mb-2"><b id="changeRoleUserName"></b></div>
                                <div class="text-muted small mb-3" id="changeRoleUserEmail"></div>

                                <label class="form-label">Novo role</label>
                                <select class="form-select" name="role" id="changeRoleSelect" required>
                                    <option value="viewer">Visualizador</option>
                                    <option value="employee">Funcionário</option>
                                    <option value="admin">Administrador</option>
                                    <option value="owner">Proprietário</option>
                                </select>
                                <div class="form-text">Observação: admin não pode alterar owner.</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal: Remover usuário da empresa -->
            <div class="modal fade" id="unlinkUserModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Remover colaborador</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="unlinkUserForm">
                            <div class="modal-body">
                                <input type="hidden" name="user_id" id="unlinkUserId">
                                <p class="mb-2">Você tem certeza que deseja remover o colaborador abaixo desta empresa?</p>
                                <div class="p-3 border rounded bg-light">
                                    <div><b id="unlinkUserName"></b></div>
                                    <div class="text-muted small" id="unlinkUserEmail"></div>
                                </div>
                                <div class="form-text mt-2">Isso apenas remove o vínculo com esta empresa (não apaga a conta do usuário).</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-danger">Remover</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <table id="usersTable" class="table">
                <thead>
                    <tr>
                        <th>Foto</th> <!-- Adicionando coluna para a imagem -->
                        <th>Nome</th>
                        <th>Perfil</th>
                        <th>Status</th> <!-- Coluna para o status (ativo/inativo) -->
                        <th>Email</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- As linhas da tabela serão preenchidas via AJAX -->
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Usando jQuery para fazer a requisição AJAX
        $(document).ready(function() {
            const company_id = <?php echo (int)$company_id; ?>; // Obtém o company_id diretamente do PHP
            window.MANAGE_USERS_COMPANY_ID = company_id;

            $.ajax({
                url: 'manage_users/ajax/get_manage_users.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    company_id: company_id
                },
                success: function(response) {
                    const users = response.users || [];

                    // limpa a tabela antes de preencher
                    $('#usersTable tbody').empty();

                    if (users && users.length > 0) {
                        users.forEach(user => {
                            // Verifica se há imagem, caso contrário, usa uma imagem padrão
                            const userImage = user.image ? `<img src="assets/img/profiles/${user.image}" class="rounded-circle" width="40" height="40" />` : '<span class="badge bg-secondary">Sem Foto</span>';

                            // Verifica o status do usuário (ativo ou inativo)
                            const isActiveBadge = user.is_active ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>';
                            // Traduzindo a role
                            let roleTranslation = '';
                            switch (user.role) {
                                case 'owner':
                                    roleTranslation = 'Proprietário';
                                    break;
                                case 'admin':
                                    roleTranslation = 'Administrador';
                                    break;
                                case 'employee':
                                    roleTranslation = 'Funcionário';
                                    break;
                                case 'viewer':
                                    roleTranslation = 'Visualizador';
                                    break;
                                default:
                                    roleTranslation = 'Desconhecido';
                            }
                            // Monta a linha da tabela
                            const row = `
                                <tr>
                                    <td>${userImage}</td>
                                    <td>${user.name}</td>
                                    <td>${roleTranslation}</td>
                                    <td>${isActiveBadge}</td>
                                    <td>${user.email}</td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn text-warning btn-sm js-change-role" data-user-id="${user.id}" data-user-name="${user.name}" data-user-email="${user.email}" data-current-role="${user.role}"><i class="bi bi-person-gear"></i></button>
                                        <button type="button" class="btn text-danger btn-sm js-unlink-user" data-user-id="${user.id}" data-user-name="${user.name}" data-user-email="${user.email}" data-current-role="${user.role}"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            `;
                            $('#usersTable tbody').append(row);
                        });
                    }

                    // Inicializa o DataTable DEPOIS de preencher
                    if ($.fn.DataTable.isDataTable('#usersTable')) {
                        $('#usersTable').DataTable().destroy();
                    }
                    $('#usersTable').DataTable({
                        paging: true,
                        searching: true,
                        ordering: true,
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json',
                            emptyTable: 'Nenhum usuário encontrado.'
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao carregar os dados dos usuários:', error);
                }
            });
        });
    </script>

    <script src="manage_users/manage_users.js?v=0.1"></script>

    <?php require_once '../app/views/footer.php'; ?>
</body>

</html>