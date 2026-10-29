module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `invoice_items` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `invoice_id` int(11) NOT NULL,\n  `item_id` int(11) NOT NULL,\n  `quantity` int(11) NOT NULL,\n  `unit_price` double NOT NULL,\n  `tax` double DEFAULT NULL,\n  `discount` double DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `invoice_id` (`invoice_id`),\n  KEY `item_id` (`item_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('invoice_items');
  },
};
