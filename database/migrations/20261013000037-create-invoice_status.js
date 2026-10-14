module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `invoice_status` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `name` varchar(255) NOT NULL,\n  `color` varchar(7) NOT NULL,\n  `text_color` varchar(7) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('invoice_status');
  },
};
