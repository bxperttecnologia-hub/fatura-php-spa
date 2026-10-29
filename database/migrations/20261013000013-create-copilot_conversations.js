module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `copilot_conversations` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `user_id` int(11) DEFAULT NULL,\n  `title` varchar(255) DEFAULT NULL,\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('copilot_conversations');
  },
};
