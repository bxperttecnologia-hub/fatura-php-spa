module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `guide_items` (\n  `id` int(10) unsigned NOT NULL,\n  `guide_id` int(10) unsigned NOT NULL,\n  `item_id` int(10) unsigned NOT NULL,\n  `description` varchar(255) NOT NULL,\n  `unit_price` decimal(14,2) NOT NULL,\n  `quantity` int(10) unsigned NOT NULL DEFAULT 1,\n  `tax` varchar(8) NOT NULL,\n  `discount` decimal(6,2) DEFAULT 0.00\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('guide_items');
  },
};
