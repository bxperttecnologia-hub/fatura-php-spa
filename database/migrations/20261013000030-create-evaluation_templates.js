module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `evaluation_templates` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `name` varchar(150) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('evaluation_templates');
  },
};
