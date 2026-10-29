module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `password_resets` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `user_id` int(11) NOT NULL,\n  `token` varchar(255) NOT NULL,\n  `expires_at` datetime NOT NULL,\n  `used` tinyint(1) NOT NULL DEFAULT 0,\n  `created_at` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `token` (`token`),\n  KEY `user_id` (`user_id`),\n  KEY `expires_at` (`expires_at`),\n  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('password_resets');
  },
};
