module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `reports` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `period_type` enum('month','quarter','year') NOT NULL,\n  `period_key` varchar(255) NOT NULL,\n  `start_date` date NOT NULL,\n  `end_date` date NOT NULL,\n  `total_faturas` int(11) DEFAULT NULL,\n  `receita_total` decimal(18,2) DEFAULT NULL,\n  `receita_paga` decimal(18,2) DEFAULT NULL,\n  `valor_em_atraso` decimal(18,2) DEFAULT NULL,\n  `top_clients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`top_clients`)),\n  `suggestions` longtext DEFAULT NULL,\n  `ai_provider` varchar(255) DEFAULT NULL,\n  `comparison` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`comparison`)),\n  `created_at` datetime NOT NULL,\n  `updated_at` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `reports_company_id_period_type_period_key` (`company_id`,`period_type`,`period_key`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('reports');
  },
};
