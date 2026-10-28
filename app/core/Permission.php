<?php
declare(strict_types=1);

class Permission
{
    private static ?bool $schemaAvailable = null;

    private const MODULES = [
        'dashboard' => 'Painel de controlo',
        'contacts' => 'Clientes/Empresas',
        'items' => 'Produtos/Serviços',
        'invoices' => 'Faturas',
        'proformas' => 'Proformas',
        'stock' => 'Stock',
        'guides' => 'Guias',
        'subscription' => 'Subscrição',
        'companies' => 'Empresas e definições',
        'users' => 'Utilizadores',
        'profile' => 'Perfil',
        'notifications' => 'Notificações',
        'insights' => 'Insights',
        'intelligence' => 'Inteligência',
        'customer_intelligence' => 'Inteligência de clientes',
        'ai_actions' => 'Ações IA',
        'rh' => 'Recursos Humanos',
    ];

    private const ROUTE_MODULES = [
        '/' => 'dashboard',
        '/contacts' => 'contacts',
        '/items' => 'items',
        '/invoices' => 'invoices',
        '/invoices/create' => 'invoices',
        '/invoices/view' => 'invoices',
        '/credit-note' => 'invoices',
        '/proformas' => 'proformas',
        '/proformas/create' => 'proformas',
        '/proformas/view' => 'proformas',
        '/stock' => 'stock',
        '/stock/view' => 'stock',
        '/guides' => 'guides',
        '/guides/list' => 'guides',
        '/guides/view' => 'guides',
        '/subscription' => 'subscription',
        '/companies' => 'companies',
        '/companies/edit' => 'companies',
        '/settings' => 'companies',
        '/users' => 'users',
        '/profile' => 'profile',
        '/notificacoes' => 'notifications',
        '/insights' => 'insights',
        '/intelligence' => 'intelligence',
        '/customer-intelligence' => 'customer_intelligence',
        '/ai-actions' => 'ai_actions',
        '/rh' => 'rh',
        '/employees' => 'rh',
        '/departments' => 'rh',
        '/positions' => 'rh',
        '/organograma' => 'rh',
        '/payroll' => 'rh',
        '/ponto' => 'rh',
        '/vacations' => 'rh',
        '/avaliacoes' => 'rh',
    ];

    public static function modules(): array
    {
        return self::MODULES;
    }

    public static function moduleForRoute(string $route): ?string
    {
        $path = '/' . trim($route, '/');
        if (isset(self::ROUTE_MODULES[$path])) {
            return self::ROUTE_MODULES[$path];
        }
        foreach (self::ROUTE_MODULES as $base => $module) {
            if ($base !== '/' && str_starts_with($path, $base . '/')) {
                return $module;
            }
        }
        return $path === '/' ? 'dashboard' : null;
    }

    public static function routeModules(array $pages): array
    {
        $modules = [];
        foreach (array_keys($pages) as $route) {
            $module = self::moduleForRoute((string)$route);
            if ($module !== null) {
                $modules[$route] = $module;
            }
        }
        return $modules;
    }

    public static function requestModule(): ?string
    {
        if (isset($_GET['__route'])) {
            return self::moduleForRoute((string)$_GET['__route']);
        }

        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (in_array(strtolower(basename($script)), [
            'appypay.php',
            'credit_note_public.php',
            'guide_public.php',
            'invoice_public.php',
            'proform_public.php',
            'consult_nif.php',
        ], true)) {
            return null;
        }

        $fileModules = [
            'data_company_notify.php' => 'notifications',
            'data_user_notify.php' => 'notifications',
            'get_notifications.php' => 'notifications',
            'notifications_actions.php' => 'notifications',
            'fetch_stock_data.php' => 'stock',
            'fetch_stocks.php' => 'stock',
            'get_stock_dashboard.php' => 'stock',
            'get_hr_insights.php' => 'rh',
            'get_ai_insights.php' => 'insights',
            'get_daily_report.php' => 'insights',
            'get_monthly_report.php' => 'insights',
            'get_sell_insights.php' => 'insights',
            'generate_saft.php' => 'invoices',
        ];
        $fileModule = $fileModules[strtolower(basename($script))] ?? null;
        if ($fileModule !== null) {
            return $fileModule;
        }

        $segments = explode('/', strtolower(trim($script, '/')));
        $folderModules = [
            'index' => 'dashboard',
            'contacts' => 'contacts',
            'items' => 'items',
            'invoices' => 'invoices',
            'create_invoices' => 'invoices',
            'credit_notes' => 'invoices',
            'proform' => 'proformas',
            'proforms' => 'proformas',
            'create_proform' => 'proformas',
            'stock' => 'stock',
            'guides' => 'guides',
            'subscription' => 'subscription',
            'companies' => 'companies',
            'edit_company' => 'companies',
            'manage_users' => 'users',
            'users' => 'users',
            'perfil' => 'profile',
            'notificacoes' => 'notifications',
            'insights' => 'insights',
            'intelligence' => 'intelligence',
            'customer_intelligence' => 'customer_intelligence',
            'ai_actions' => 'ai_actions',
            'rh' => 'rh',
        ];

        foreach ($segments as $segment) {
            if (isset($folderModules[$segment])) {
                return $folderModules[$segment];
            }
        }

        $fileModules = [
            'list_invoices.php' => 'invoices',
            'invoice.php' => 'invoices',
            'create_invoices.php' => 'invoices',
            'list_proforms.php' => 'proformas',
            'create_proform.php' => 'proformas',
            'manage_users.php' => 'users',
            'edit_company.php' => 'companies',
        ];
        return $fileModules[strtolower(basename($script))] ?? null;
    }

    public static function requestCompanyId(?array $user = null): int
    {
        foreach ([$_GET['company_id'] ?? null, $_POST['company_id'] ?? null, $user['company_id'] ?? null] as $value) {
            if (filter_var($value, FILTER_VALIDATE_INT) !== false && (int)$value > 0) {
                return (int)$value;
            }
        }
        return 0;
    }

    public static function hasAccess(PDO $pdo, int $userId, int $companyId, string $module): bool
    {
        if (!isset(self::MODULES[$module]) || $userId <= 0 || $companyId <= 0) {
            return false;
        }

        $membership = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
        $membership->execute([$userId, $companyId]);
        $role = $membership->fetchColumn();
        if (!is_string($role) || $role === false) {
            return false;
        }
        if ($role === 'owner') {
            return true;
        }
        if (!self::schemaAvailable($pdo)) {
            return true;
        }

        $permission = $pdo->prepare(
            'SELECT can_access FROM company_user_permissions WHERE company_id = ? AND user_id = ? AND module = ? LIMIT 1'
        );
        $permission->execute([$companyId, $userId, $module]);
        $allowed = $permission->fetchColumn();

        return $allowed === false || (int)$allowed === 1;
    }

    public static function allowedModules(PDO $pdo, int $userId, int $companyId): array
    {
        if ($userId <= 0 || $companyId <= 0) {
            return [];
        }

        $membership = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
        $membership->execute([$userId, $companyId]);
        $role = $membership->fetchColumn();
        if (!is_string($role) || $role === false) {
            return [];
        }
        if ($role === 'owner') {
            return array_keys(self::MODULES);
        }
        if (!self::schemaAvailable($pdo)) {
            return array_keys(self::MODULES);
        }

        $query = $pdo->prepare('SELECT module, can_access FROM company_user_permissions WHERE company_id = ? AND user_id = ?');
        $query->execute([$companyId, $userId]);
        $overrides = [];
        foreach ($query->fetchAll() as $row) {
            $overrides[(string)$row['module']] = (bool)$row['can_access'];
        }

        return array_values(array_filter(
            array_keys(self::MODULES),
            static fn(string $module): bool => $overrides[$module] ?? true
        ));
    }

    public static function canManageUsers(PDO $pdo, int $userId, int $companyId): bool
    {
        if ($userId <= 0 || $companyId <= 0) {
            return false;
        }

        $query = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
        $query->execute([$userId, $companyId]);
        return in_array($query->fetchColumn(), ['owner', 'admin'], true);
    }

    public static function requireCompanyManager(PDO $pdo, int $userId, int $companyId): string
    {
        $query = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
        $query->execute([$userId, $companyId]);
        $role = $query->fetchColumn();
        if (!in_array($role, ['owner', 'admin'], true)) {
            throw new HttpException(403, 'Apenas proprietários e administradores podem gerir permissões.');
        }
        return (string)$role;
    }

    public static function userPermissions(PDO $pdo, int $userId, int $companyId): array
    {
        $membership = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
        $membership->execute([$userId, $companyId]);
        $role = $membership->fetchColumn();
        if (!is_string($role) || $role === false) {
            throw new HttpException(404, 'Utilizador não está associado a esta empresa.');
        }
        if ($role === 'owner') {
            return array_fill_keys(array_keys(self::MODULES), true);
        }
        if (!self::schemaAvailable($pdo)) {
            throw new HttpException(503, 'Execute a migração de permissões antes de configurar este utilizador.');
        }

        $permissions = array_fill_keys(array_keys(self::MODULES), true);
        $query = $pdo->prepare('SELECT module, can_access FROM company_user_permissions WHERE company_id = ? AND user_id = ?');
        $query->execute([$companyId, $userId]);
        foreach ($query->fetchAll() as $row) {
            $module = (string)$row['module'];
            if (isset($permissions[$module])) {
                $permissions[$module] = (bool)$row['can_access'];
            }
        }
        return $permissions;
    }

    public static function saveUserPermissions(PDO $pdo, int $companyId, int $userId, array $allowedModules, int $requesterId, string $requesterRole): void
    {
        if (!self::schemaAvailable($pdo)) {
            throw new HttpException(503, 'Execute a migração de permissões antes de guardar alterações.');
        }

        $membership = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
        $membership->execute([$userId, $companyId]);
        $targetRole = $membership->fetchColumn();
        if (!is_string($targetRole) || $targetRole === false) {
            throw new HttpException(404, 'Utilizador não está associado a esta empresa.');
        }
        if ($requesterRole === 'admin' && $targetRole === 'owner') {
            throw new HttpException(403, 'Um administrador não pode alterar as permissões do proprietário.');
        }
        if ($targetRole === 'owner') {
            throw new HttpException(422, 'O proprietário tem sempre acesso total.');
        }

        $allowed = array_fill_keys(array_keys(self::MODULES), false);
        foreach ($allowedModules as $module) {
            if (!is_string($module) || !isset(self::MODULES[$module])) {
                throw new HttpException(422, 'Foi indicada uma permissão inválida.');
            }
            $allowed[$module] = true;
        }
        if ($requesterRole === 'admin' && $userId === $requesterId && !$allowed['users']) {
            throw new HttpException(422, 'Um administrador não pode remover o próprio acesso à gestão de utilizadores.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM company_user_permissions WHERE company_id = ? AND user_id = ?')
                ->execute([$companyId, $userId]);
            $insert = $pdo->prepare(
                'INSERT INTO company_user_permissions (company_id, user_id, module, can_access) VALUES (?, ?, ?, ?)'
            );
            foreach ($allowed as $module => $canAccess) {
                $insert->execute([$companyId, $userId, $module, $canAccess ? 1 : 0]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function guardCurrentRequest(PDO $pdo): void
    {
        $module = self::requestModule();
        if ($module === null) {
            return;
        }
        if (!Auth::check()) {
            self::jsonError(401, 'Sessão expirada ou inexistente');
        }

        $user = Auth::user();
        $companyId = self::requestCompanyId($user);
        if (!$user || !self::hasAccess($pdo, (int)$user['id'], $companyId, $module)) {
            self::jsonError(403, 'Não tem permissão para aceder a este módulo.');
        }
    }

    private static function jsonError(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE));
    }

    private static function schemaAvailable(PDO $pdo): bool
    {
        if (self::$schemaAvailable !== null) {
            return self::$schemaAvailable;
        }

        try {
            $pdo->query('SELECT 1 FROM company_user_permissions LIMIT 0');
            return self::$schemaAvailable = true;
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02' && (int)($e->errorInfo[1] ?? 0) !== 1146) {
                throw $e;
            }
            error_log('Permission schema is missing; run app/migrations/20261020_create_company_user_permissions.sql.');
            return self::$schemaAvailable = false;
        }
    }
}