module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `attendance` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `employee_id` int(11) NOT NULL,\n  `company_id` int(11) NOT NULL,\n  `date` date NOT NULL,\n  `type` enum('presença','falta','atestado','folga') DEFAULT 'presença',\n  `justification` text DEFAULT NULL,\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('attendance');
  },
};
