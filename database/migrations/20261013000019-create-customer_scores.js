module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `customer_scores` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `contact_id` int(11) NOT NULL,\n  `total_invoices` int(11) NOT NULL DEFAULT 0,\n  `total_purchased` double NOT NULL DEFAULT 0,\n  `avg_ticket` double NOT NULL DEFAULT 0,\n  `avg_frequency_days` double DEFAULT NULL,\n  `avg_delay_days` double DEFAULT NULL,\n  `first_purchase_date` date DEFAULT NULL,\n  `last_purchase_date` date DEFAULT NULL,\n  `days_since_last_purchase` int(11) DEFAULT NULL,\n  `trend_pct` double DEFAULT NULL,\n  `risk_level` enum('baixo','medio','alto') NOT NULL DEFAULT 'baixo',\n  `potential_level` enum('baixo','medio','alto','muito_alto') NOT NULL DEFAULT 'baixo',\n  `classifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`classifications`)),\n  `calculated_at` datetime NOT NULL,\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `customer_scores_company_id_contact_id` (`company_id`,`contact_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('customer_scores');
  },
};
