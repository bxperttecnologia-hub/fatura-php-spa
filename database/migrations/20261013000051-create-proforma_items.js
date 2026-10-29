module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `proforma_items` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `proforma_id` int(11) NOT NULL,\n  `item_id` int(11) NOT NULL,\n  `quantity` double NOT NULL,\n  `unit_price` double NOT NULL,\n  `tax` double DEFAULT 0,\n  `discount` double DEFAULT 0,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('proforma_items');
  },
};
