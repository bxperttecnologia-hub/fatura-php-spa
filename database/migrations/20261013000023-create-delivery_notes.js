module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `delivery_notes` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `invoice_id` int(11) NOT NULL,\n  `contact_id` int(11) NOT NULL,\n  `company_id` int(11) NOT NULL,\n  `serie` varchar(10) NOT NULL DEFAULT 'NE',\n  `number` int(10) unsigned NOT NULL,\n  `issue_date` date NOT NULL,\n  `delivery_address` varchar(255) DEFAULT NULL,\n  `notes` text DEFAULT NULL,\n  `user_id` int(11) DEFAULT NULL,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_dn_company_serie_number` (`company_id`,`serie`,`number`),\n  KEY `idx_dn_invoice` (`invoice_id`),\n  KEY `idx_dn_company` (`company_id`),\n  CONSTRAINT `fk_dn_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('delivery_notes');
  },
};
