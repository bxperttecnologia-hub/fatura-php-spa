module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `phone_verifications` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `name` varchar(150) NOT NULL,\n  `phone` varchar(20) NOT NULL,\n  `channel` enum('sms','whatsapp') NOT NULL,\n  `code_hash` varchar(255) NOT NULL,\n  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,\n  `ip` varchar(45) DEFAULT NULL,\n  `expires_at` datetime NOT NULL,\n  `verified_at` datetime DEFAULT NULL,\n  `created_at` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  KEY `idx_phone_created` (`phone`,`created_at`),\n  KEY `idx_ip_created` (`ip`,`created_at`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('phone_verifications');
  },
};
