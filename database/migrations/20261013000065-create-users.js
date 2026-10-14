module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `users` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `username` varchar(255) NOT NULL,\n  `name` varchar(1000) NOT NULL,\n  `phone` varchar(255) NOT NULL,\n  `email` varchar(255) NOT NULL,\n  `password` varchar(255) NOT NULL,\n  `gender` enum('Masculino','Feminino','Outro','prefer_not_to_say') NOT NULL,\n  `identification_type` int(11) NOT NULL,\n  `identification` varchar(255) NOT NULL,\n  `address` text NOT NULL,\n  `address_number` varchar(255) NOT NULL,\n  `address_neighborhood` varchar(255) NOT NULL,\n  `address_district` varchar(255) NOT NULL,\n  `zip_code` varchar(255) NOT NULL,\n  `country` varchar(255) NOT NULL,\n  `city` varchar(200) DEFAULT NULL,\n  `blocked` tinyint(1) NOT NULL,\n  `image` varchar(255) NOT NULL DEFAULT 'default.png',\n  `lang` varchar(255) NOT NULL DEFAULT 'angola',\n  `currency` varchar(100) NOT NULL,\n  `is_active` tinyint(1) NOT NULL,\n  `adm` tinyint(1) NOT NULL DEFAULT 0,\n  `created_at` datetime NOT NULL DEFAULT current_timestamp(),\n  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,\n  `phone_verified_at` datetime DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `email` (`email`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('users');
  },
};
