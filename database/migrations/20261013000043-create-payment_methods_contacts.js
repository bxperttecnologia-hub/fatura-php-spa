module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `payment_methods_contacts` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `code` varchar(50) NOT NULL,\n  `name` varchar(255) NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `code` (`code`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('payment_methods_contacts');
  },
};
