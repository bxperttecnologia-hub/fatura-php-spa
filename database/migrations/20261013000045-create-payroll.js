module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `payroll` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `employee_id` int(11) NOT NULL,\n  `company_id` int(11) NOT NULL,\n  `reference_month` varchar(7) NOT NULL,\n  `base_salary` decimal(10,2) NOT NULL,\n  `bonuses` decimal(10,2) DEFAULT 0.00,\n  `food_allowance` decimal(10,2) DEFAULT 0.00,\n  `transport_allowance` decimal(10,2) DEFAULT 0.00,\n  `vacation_subsidy_pct` tinyint(4) DEFAULT 0,\n  `thirteenth_subsidy_pct` tinyint(4) DEFAULT 0,\n  `commissions` decimal(10,2) DEFAULT 0.00,\n  `sales` decimal(10,2) DEFAULT 0.00,\n  `discounts` decimal(10,2) DEFAULT 0.00,\n  `total_discounts` decimal(10,2) DEFAULT 0.00,\n  `inss_value` decimal(10,2) DEFAULT 0.00,\n  `inss_employer_value` decimal(10,2) DEFAULT 0.00,\n  `irt_value` decimal(10,2) DEFAULT 0.00,\n  `net_salary` decimal(10,2) NOT NULL,\n  `payment_date` date DEFAULT NULL,\n  `status` enum('Pago','Pendente') DEFAULT 'Pendente',\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `employee_id` (`employee_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('payroll');
  },
};
