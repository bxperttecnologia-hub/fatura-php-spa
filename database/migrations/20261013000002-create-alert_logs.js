module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `alert_logs` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `rule_id` int(11) DEFAULT NULL,\n  `entity_type` enum('invoice','employee','payroll') NOT NULL,\n  `entity_id` int(11) NOT NULL,\n  `step` varchar(255) NOT NULL,\n  `channel` varchar(255) NOT NULL,\n  `status` enum('sent','failed','pending') DEFAULT 'pending',\n  `sent_at` datetime DEFAULT NULL,\n  `response_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_meta`)),\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `alert_logs_entity_type_entity_id_step_channel` (`entity_type`,`entity_id`,`step`,`channel`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('alert_logs');
  },
};
