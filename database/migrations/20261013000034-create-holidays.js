module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `holidays` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `date` date NOT NULL,\n  `name` varchar(150) NOT NULL,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uniq_company_date` (`company_id`,`date`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('holidays');
  },
};
