module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query(
      "CREATE TABLE `business_health_scores` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `snapshot_date` date NOT NULL,\n  `overall_score` int(11) NOT NULL,\n  `financeiro_score` int(11) DEFAULT NULL,\n  `vendas_score` int(11) DEFAULT NULL,\n  `clientes_score` int(11) DEFAULT NULL,\n  `stock_score` int(11) DEFAULT NULL,\n  `rh_score` int(11) DEFAULT NULL,\n  `explanation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`explanation`)),\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `business_health_scores_company_id_snapshot_date` (`company_id`,`snapshot_date`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    );
  },

  async down(queryInterface) {
    await queryInterface.dropTable("business_health_scores");
  },
};
