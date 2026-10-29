module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `proformas` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `contact_id` int(11) NOT NULL,\n  `company_id` int(11) NOT NULL,\n  `user_id` int(11) NOT NULL,\n  `status` int(11) NOT NULL DEFAULT 1,\n  `issue_date` date NOT NULL,\n  `due_date` int(11) DEFAULT NULL,\n  `reference` varchar(100) NOT NULL,\n  `observation` text DEFAULT NULL,\n  `series` int(11) NOT NULL,\n  `currency` varchar(10) NOT NULL,\n  `manual_exchange_rate` double NOT NULL DEFAULT 1,\n  `retention` double DEFAULT 0,\n  `retention_value` double DEFAULT 0,\n  `total_sum` double NOT NULL DEFAULT 0,\n  `total_discount` double NOT NULL DEFAULT 0,\n  `subtotal_without_tax` double NOT NULL DEFAULT 0,\n  `total_tax` double NOT NULL DEFAULT 0,\n  `final_total` double NOT NULL DEFAULT 0,\n  `converted_total` double NOT NULL DEFAULT 0,\n  `converted_invoice_id` int(11) DEFAULT NULL,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  `converted_at` datetime DEFAULT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('proformas');
  },
};
