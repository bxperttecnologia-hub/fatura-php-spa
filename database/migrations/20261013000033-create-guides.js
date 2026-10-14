module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `guides` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `contact_id` int(10) unsigned NOT NULL,\n  `vehicle_plate` varchar(32) DEFAULT NULL,\n  `cargo_date` date NOT NULL,\n  `transport_reason` varchar(255) DEFAULT NULL,\n  `observations` varchar(255) DEFAULT NULL,\n  `retention` decimal(5,2) DEFAULT 0.00,\n  `cargo_address` varchar(255) NOT NULL,\n  `cargo_city` varchar(120) NOT NULL,\n  `cargo_po_box` varchar(40) DEFAULT NULL,\n  `delivery_address` varchar(255) NOT NULL,\n  `delivery_city` varchar(120) NOT NULL,\n  `delivery_po_box` varchar(40) DEFAULT NULL,\n  `document_date` date NOT NULL,\n  `reference` varchar(90) DEFAULT NULL,\n  `series` varchar(32) DEFAULT NULL,\n  `currency` varchar(8) DEFAULT 'AOA',\n  `total_sum` decimal(14,2) DEFAULT 0.00,\n  `total_discount` decimal(14,2) DEFAULT 0.00,\n  `subtotal_without_tax` decimal(14,2) DEFAULT 0.00,\n  `total_tax` decimal(14,2) DEFAULT 0.00,\n  `retention_value` decimal(14,2) DEFAULT 0.00,\n  `final_total` decimal(14,2) DEFAULT 0.00,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('guides');
  },
};
