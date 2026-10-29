module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `currencies` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `iso_code` varchar(10) DEFAULT NULL,\n  `currency` varchar(100) DEFAULT NULL,\n  `symbol` varchar(10) DEFAULT NULL,\n  `position` enum('left','right') DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `iso_code` (`iso_code`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('currencies');
  },
};
