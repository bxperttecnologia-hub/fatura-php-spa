module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `stock_movements` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `stock_id` int(11) NOT NULL,\n  `item_id` int(11) NOT NULL,\n  `movement_type` enum('entrada','saida','transferencia','ajuste','devolucao','inventario') NOT NULL,\n  `quantity` int(11) NOT NULL,\n  `unit_price` decimal(10,2) DEFAULT NULL,\n  `source_stock_id` int(11) DEFAULT NULL,\n  `destination_stock_id` int(11) DEFAULT NULL,\n  `reference_type` varchar(100) DEFAULT NULL,\n  `reference_id` int(11) DEFAULT NULL,\n  `notes` text DEFAULT NULL,\n  `created_by` int(11) DEFAULT NULL,\n  `created_at` datetime DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `stock_id` (`stock_id`),\n  KEY `item_id` (`item_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('stock_movements');
  },
};
