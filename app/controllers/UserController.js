export class UserController {
  static async index(req, res) {
    return res.status(200).json({
      success: true,
      message: 'Utilizadores carregados.',
      data: [],
      meta: { total: 0, page: 1, perPage: 10 },
    });
  }

  static async show(req, res) {
    return res.status(200).json({
      success: true,
      message: 'Utilizador carregado.',
      data: {
        id: Number(req.params.id),
        name: 'Usuário exemplo',
        email: 'user@example.com',
      },
      meta: null,
    });
  }

  static async store(req, res) {
    return res.status(201).json({
      success: true,
      message: 'Utilizador criado com sucesso.',
      data: { ...req.body },
      meta: null,
    });
  }

  static async update(req, res) {
    return res.status(200).json({
      success: true,
      message: 'Utilizador atualizado com sucesso.',
      data: { id: Number(req.params.id), ...req.body },
      meta: null,
    });
  }

  static async destroy(req, res) {
    return res.status(204).send();
  }
}
