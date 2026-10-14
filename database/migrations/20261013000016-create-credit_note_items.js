module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `credit_note_items` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `credit_note_id` int(11) NOT NULL,\n  `item_id` int(11) DEFAULT NULL,\n  `description` text DEFAULT NULL,\n  `quantity` double NOT NULL DEFAULT 0,\n  `unit_price` double NOT NULL DEFAULT 0,\n  `tax` double DEFAULT NULL,\n  `discount` double DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `credit_note_id` (`credit_note_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('credit_note_items');
  },
};
