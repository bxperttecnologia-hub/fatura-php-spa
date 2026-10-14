module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `receipts` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `invoice_id` int(11) NOT NULL,\n  `serie` varchar(10) NOT NULL,\n  `number` int(11) NOT NULL,\n  `reference` varchar(200) DEFAULT NULL,\n  `pay_date` date NOT NULL,\n  `amount_paid` double NOT NULL,\n  `payment_method` varchar(60) NOT NULL,\n  `notes` text DEFAULT NULL,\n  `pending_amount` decimal(18,2) NOT NULL DEFAULT 0.00,\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `u_serie_num` (`serie`,`number`),\n  KEY `invoice_id` (`invoice_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('receipts');
  },
};
