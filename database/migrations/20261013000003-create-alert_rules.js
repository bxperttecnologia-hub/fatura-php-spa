module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `alert_rules` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `type` enum('invoice_due','payroll_open','payroll_close','contract_expiry') NOT NULL,\n  `threshold_days` int(11) NOT NULL DEFAULT 7,\n  `channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`channels`)),\n  `active` tinyint(1) DEFAULT 1,\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('alert_rules');
  },
};
