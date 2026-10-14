module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `preset_items` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `code` varchar(50) NOT NULL,\n  `name` varchar(255) NOT NULL,\n  `description` text DEFAULT NULL,\n  `item_type` enum('product','service','raw_material','finished_good','consumable') NOT NULL DEFAULT 'product',\n  `unit_measure` enum('unit','kg','g','liter','meter','box','pack','service') NOT NULL DEFAULT 'unit',\n  `category` varchar(150) DEFAULT NULL,\n  `suggested_price` decimal(15,2) DEFAULT 0.00,\n  `tax` enum('0','5','7','14') DEFAULT '14',\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `unique_preset_code` (`code`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('preset_items');
  },
};
