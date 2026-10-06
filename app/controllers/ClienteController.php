<?php
class ClienteController {
    private function validate(array $in): array {
        $d = [
            'nome'     => trim($in['nome'] ?? ''),
            'email'    => trim($in['email'] ?? ''),
            'telefone' => trim($in['telefone'] ?? ''),
        ];
        $err = [];
        if ($d['nome'] === '') $err['nome'] = 'O nome é obrigatório';
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Email inválido';
        if ($err) throw new HttpException(422, 'Dados inválidos', $err);
        return $d;
    }

    private function find(string $id): array {
        $st = Database::pdo()->prepare('SELECT * FROM contacts WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: throw new HttpException(404, 'Cliente não encontrado');
    }

    public function index(): array {
        $q = '%' . addcslashes(trim((string)($_GET['q'] ?? '')), '%_\\') . '%';
        $st = Database::pdo()->prepare('SELECT * FROM contacts WHERE nome LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 200');
        $st->execute([$q, $q]);
        return ['data' => $st->fetchAll()];
    }

    public function show(array $in, array $p): array {
        return ['data' => $this->find($p['id'])];
    }

    public function store(array $in): array {
        $d = $this->validate($in);
        Database::pdo()->prepare('INSERT INTO clientes (nome,email,telefone) VALUES (?,?,?)')
            ->execute([$d['nome'], $d['email'] ?: null, $d['telefone'] ?: null]);
        http_response_code(201);
        return ['data' => $this->find(Database::pdo()->lastInsertId()), 'message' => 'Cliente criado'];
    }

    public function update(array $in, array $p): array {
        $this->find($p['id']);
        $d = $this->validate($in);
        Database::pdo()->prepare('UPDATE clientes SET nome=?, email=?, telefone=? WHERE id=?')
            ->execute([$d['nome'], $d['email'] ?: null, $d['telefone'] ?: null, $p['id']]);
        return ['data' => $this->find($p['id']), 'message' => 'Cliente atualizado'];
    }

    public function destroy(array $in, array $p): array {
        $this->find($p['id']);
        Database::pdo()->prepare('DELETE FROM clientes WHERE id = ?')->execute([$p['id']]);
        return ['message' => 'Cliente removido'];
    }
}
