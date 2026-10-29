module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `subscription_orders` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `plan_code` varchar(40) NOT NULL,\n  `period_months` int(11) NOT NULL DEFAULT 3,\n  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,\n  `status` enum('pending','paid','canceled') NOT NULL DEFAULT 'pending',\n  `gateway` varchar(30) DEFAULT NULL,\n  `payment_method` varchar(10) DEFAULT NULL,\n  `merchant_transaction_id` varchar(80) DEFAULT NULL,\n  `gateway_charge_id` varchar(80) DEFAULT NULL,\n  `gateway_status` varchar(40) DEFAULT NULL,\n  `gateway_response_raw` mediumtext DEFAULT NULL,\n  `payment_provider` varchar(40) DEFAULT NULL,\n  `payment_ref` varchar(120) DEFAULT NULL,\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  `paid_at` datetime DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `company_id` (`company_id`),\n  KEY `status` (`status`),\n  KEY `idx_subscription_orders_merchant` (`merchant_transaction_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('subscription_orders');
  },
};
