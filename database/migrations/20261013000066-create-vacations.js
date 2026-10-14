module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `vacations` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `employee_id` int(11) NOT NULL,\n  `company_id` int(11) NOT NULL,\n  `type` enum('Férias','Licença Médica','Licença Maternidade','Outros') DEFAULT 'Férias',\n  `start_date` date NOT NULL,\n  `end_date` date NOT NULL,\n  `reason` text DEFAULT NULL,\n  `status` enum('Aprovado','Pendente','Rejeitado') DEFAULT 'Pendente',\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `employee_id` (`employee_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('vacations');
  },
};
