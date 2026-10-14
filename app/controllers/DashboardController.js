export class DashboardController {
  static async stats(req, res) {
    return res.status(200).json({
      success: true,
      message: 'Estatísticas carregadas.',
      data: {
        totalUsers: 12,
        revenue: 24500,
        conversionRate: 6.8,
        activeAccounts: 78,
      },
      meta: null,
    });
  }

  static async charts(req, res) {
    return res.status(200).json({
      success: true,
      message: 'Dados dos gráficos carregados.',
      data: {
        line: {
          labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
          values: [120, 160, 190, 220, 210, 260],
        },
        doughnut: {
          labels: ['Vendas', 'Suporte', 'Marketing', 'Operações'],
          values: [35, 25, 20, 20],
        },
      },
      meta: null,
    });
  }
}
