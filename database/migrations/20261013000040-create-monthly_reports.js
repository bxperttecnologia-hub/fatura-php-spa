module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `monthly_reports` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `year` int(11) NOT NULL,\n  `month` int(11) NOT NULL,\n  `total_faturas` int(11) DEFAULT NULL,\n  `receita_total` decimal(18,2) DEFAULT NULL,\n  `receita_paga` decimal(18,2) DEFAULT NULL,\n  `valor_em_atraso` decimal(18,2) DEFAULT NULL,\n  `top_clients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`top_clients`)),\n  `suggestions` longtext DEFAULT NULL,\n  `ai_provider` varchar(255) DEFAULT NULL,\n  `created_at` datetime NOT NULL,\n  `updated_at` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `monthly_reports_company_id_year_month` (`company_id`,`year`,`month`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('monthly_reports');
  },
};
