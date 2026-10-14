module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `support_agents` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) DEFAULT NULL,\n  `name` varchar(150) NOT NULL,\n  `email` varchar(180) NOT NULL,\n  `password_hash` varchar(255) NOT NULL,\n  `role` enum('commercial_support','support_admin') NOT NULL DEFAULT 'commercial_support',\n  `active` tinyint(1) NOT NULL DEFAULT 1,\n  `last_login_at` datetime DEFAULT NULL,\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `email` (`email`),\n  KEY `support_agents_company_id_active` (`company_id`,`active`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('support_agents');
  },
};
