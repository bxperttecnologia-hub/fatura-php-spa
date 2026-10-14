module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `proforma_status` (\n  `id` int(11) NOT NULL,\n  `name` varchar(100) NOT NULL,\n  `color` varchar(7) NOT NULL,\n  `text_color` varchar(7) NOT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('proforma_status');
  },
};
