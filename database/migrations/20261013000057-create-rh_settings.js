module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `rh_settings` (\n  `company_id` int(11) NOT NULL,\n  `salario_minimo_nacional` decimal(10,2) NOT NULL DEFAULT 70000.00,\n  `aviso_previo_demissao_dias` int(11) NOT NULL DEFAULT 30,\n  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`company_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('rh_settings');
  },
};
