module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `delivery_note_items` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `delivery_note_id` int(10) unsigned NOT NULL,\n  `invoice_item_id` int(11) DEFAULT NULL,\n  `item_id` int(11) DEFAULT NULL,\n  `description` varchar(500) DEFAULT NULL,\n  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000,\n  PRIMARY KEY (`id`),\n  KEY `idx_dni_note` (`delivery_note_id`),\n  KEY `idx_dni_invoice_item` (`invoice_item_id`),\n  CONSTRAINT `fk_dni_invoice_item` FOREIGN KEY (`invoice_item_id`) REFERENCES `invoice_items` (`id`),\n  CONSTRAINT `fk_dni_note` FOREIGN KEY (`delivery_note_id`) REFERENCES `delivery_notes` (`id`) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('delivery_note_items');
  },
};
