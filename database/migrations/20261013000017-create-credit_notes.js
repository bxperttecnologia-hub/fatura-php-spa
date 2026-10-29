module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `credit_notes` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `invoice_id` int(11) NOT NULL,\n  `contact_id` int(11) NOT NULL,\n  `company_id` int(11) NOT NULL,\n  `issue_date` date NOT NULL,\n  `reason` text DEFAULT NULL,\n  `currency` varchar(10) NOT NULL,\n  `retention` double DEFAULT NULL,\n  `retention_value` double NOT NULL DEFAULT 0,\n  `total_sum` double NOT NULL DEFAULT 0,\n  `total_discount` double NOT NULL DEFAULT 0,\n  `subtotal_without_tax` double NOT NULL DEFAULT 0,\n  `total_tax` double NOT NULL DEFAULT 0,\n  `final_total` double NOT NULL DEFAULT 0,\n  `user_id` int(11) NOT NULL,\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `invoice_id` (`invoice_id`),\n  KEY `company_id` (`company_id`),\n  KEY `contact_id` (`contact_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('credit_notes');
  },
};
