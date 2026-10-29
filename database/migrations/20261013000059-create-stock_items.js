module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `stock_items` (\n  `id` int(11) NOT NULL,\n  `stock_id` int(11) NOT NULL,\n  `item_id` int(11) NOT NULL,\n  `quantity` int(11) DEFAULT 0,\n  `min_quantity` int(11) DEFAULT 1,\n  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('stock_items');
  },
};
