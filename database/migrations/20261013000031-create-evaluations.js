module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `evaluations` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `cycle_id` int(11) NOT NULL,\n  `employee_id` int(11) NOT NULL,\n  `evaluator_id` int(11) NOT NULL,\n  `template_id` int(11) NOT NULL,\n  `status` enum('pendente','em_curso','concluida') DEFAULT 'pendente',\n  `final_score` decimal(5,2) DEFAULT NULL,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('evaluations');
  },
};
