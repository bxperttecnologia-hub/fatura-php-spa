module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `positions` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `name` varchar(100) NOT NULL,\n  `suggested_salary` decimal(10,2) DEFAULT 0.00,\n  `food_allowance` decimal(10,2) DEFAULT 0.00,\n  `transport_allowance` decimal(10,2) DEFAULT 0.00,\n  `vacation_subsidy_pct` tinyint(4) DEFAULT 0,\n  `thirteenth_subsidy_pct` tinyint(4) DEFAULT 0,\n  `department_id` int(11) DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `department_id` (`department_id`),\n  CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('positions');
  },
};
