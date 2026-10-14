module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `products` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `name` varchar(255) NOT NULL,\n  `current_stock` double DEFAULT NULL,\n  `min_stock` double DEFAULT NULL,\n  `cost_price` double DEFAULT NULL,\n  `active` tinyint(1) DEFAULT 1,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('products');
  },
};
