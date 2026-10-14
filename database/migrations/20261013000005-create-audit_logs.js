module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `audit_logs` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `actor_type` enum('sistema','utilizador','autopilot') NOT NULL DEFAULT 'sistema',\n  `actor_id` int(11) DEFAULT NULL,\n  `action` varchar(255) NOT NULL,\n  `entity_type` varchar(255) DEFAULT NULL,\n  `entity_id` int(11) DEFAULT NULL,\n  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('audit_logs');
  },
};
