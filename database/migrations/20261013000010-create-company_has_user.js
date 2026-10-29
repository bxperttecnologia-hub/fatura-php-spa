module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `company_has_user` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `user_id` int(11) NOT NULL,\n  `role` enum('owner','admin','employee','viewer') DEFAULT 'employee',\n  `created_at` datetime DEFAULT current_timestamp(),\n  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `company_id` (`company_id`),\n  KEY `user_id` (`user_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('company_has_user');
  },
};
