module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `sessions` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `user_id` int(11) NOT NULL,\n  `session_token` varchar(255) NOT NULL,\n  `expires_at` datetime NOT NULL,\n  `created_at` datetime DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `session_token` (`session_token`),\n  KEY `user_id` (`user_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('sessions');
  },
};
