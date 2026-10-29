module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `plans` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `name` varchar(50) NOT NULL,\n  `description` text DEFAULT NULL,\n  `price` decimal(10,2) NOT NULL,\n  `duration_months` int(11) NOT NULL,\n  `features` text DEFAULT NULL,\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('plans');
  },
};
