module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `countries` (\n  `code` varchar(10) NOT NULL,\n  `phone` varchar(10) DEFAULT NULL,\n  `iso` varchar(5) DEFAULT NULL,\n  `iso3` varchar(5) DEFAULT NULL,\n  `name` varchar(100) DEFAULT NULL,\n  `formal_name` varchar(255) DEFAULT NULL,\n  PRIMARY KEY (`code`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('countries');
  },
};
