module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `items` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `code` varchar(50) NOT NULL,\n  `name` varchar(255) NOT NULL,\n  `description` text NOT NULL,\n  `item_type` enum('product','service','raw_material','finished_good','consumable') NOT NULL DEFAULT 'product',\n  `unit_measure` enum('unit','kg','g','liter','meter','box','pack','service') NOT NULL DEFAULT 'unit',\n  `category` varchar(150) DEFAULT NULL,\n  `brand` varchar(150) DEFAULT NULL,\n  `barcode` varchar(150) DEFAULT NULL,\n  `sku` varchar(150) DEFAULT NULL,\n  `retention` float NOT NULL DEFAULT 0,\n  `unit_price` double NOT NULL,\n  `currency` varchar(10) NOT NULL DEFAULT 'AOA',\n  `track_stock` tinyint(1) NOT NULL DEFAULT 1,\n  `global_min_stock` int(11) DEFAULT 1,\n  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',\n  `created_by` int(11) DEFAULT NULL,\n  `updated_by` int(11) DEFAULT NULL,\n  `tax` enum('0','5','7','14') DEFAULT '0',\n  `cost_price` decimal(15,2) DEFAULT 0.00,\n  `sale_price` decimal(15,2) DEFAULT 0.00,\n  `pvp` double DEFAULT NULL,\n  `profit_margin` decimal(10,2) DEFAULT 0.00,\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `unique_company_code` (`company_id`,`code`),\n  UNIQUE KEY `unique_company_sku` (`company_id`,`sku`),\n  KEY `items_ibfk_1` (`company_id`),\n  KEY `items_ibfk_2` (`currency`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('items');
  },
};
