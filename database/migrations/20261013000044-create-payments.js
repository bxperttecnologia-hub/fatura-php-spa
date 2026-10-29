module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `payments` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `plan_id` int(11) NOT NULL,\n  `amount` decimal(10,2) NOT NULL,\n  `reference` varchar(100) DEFAULT NULL,\n  `payment_method` enum('reference','qrcode') NOT NULL,\n  `status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',\n  `transaction_id` varchar(255) DEFAULT NULL,\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `company_id` (`company_id`),\n  KEY `plan_id` (`plan_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('payments');
  },
};
