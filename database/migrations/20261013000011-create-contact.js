module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `contact` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) DEFAULT NULL,\n  `type` varchar(50) DEFAULT NULL,\n  `name` varchar(255) NOT NULL,\n  `contributor` varchar(100) DEFAULT NULL,\n  `address` tinytext DEFAULT NULL,\n  `website` varchar(255) DEFAULT NULL,\n  `country` varchar(100) DEFAULT 'Angola',\n  `city` varchar(100) DEFAULT NULL,\n  `email` varchar(150) DEFAULT NULL,\n  `telephone_ddi` varchar(5) DEFAULT NULL,\n  `telephone` varchar(60) DEFAULT NULL,\n  `cellphone_ddi` varchar(10) DEFAULT NULL,\n  `cellphone` varchar(50) DEFAULT NULL,\n  `po_box` varchar(255) DEFAULT NULL,\n  `fax` varchar(50) DEFAULT NULL,\n  `pref_name` varchar(255) DEFAULT NULL,\n  `pref_email` varchar(150) DEFAULT NULL,\n  `pref_telephone_ddi` varchar(5) DEFAULT NULL,\n  `pref_telephone` varchar(60) DEFAULT NULL,\n  `pref_cellphone_ddi` varchar(5) DEFAULT NULL,\n  `pref_cellphone` varchar(60) DEFAULT NULL,\n  `numberCopys` int(11) DEFAULT NULL,\n  `observations` text DEFAULT NULL,\n  `due_date` int(11) DEFAULT NULL,\n  `language` varchar(3) DEFAULT NULL,\n  `payment_method` varchar(50) DEFAULT NULL,\n  `currency` varchar(100) DEFAULT NULL,\n  `is_active` tinyint(1) NOT NULL DEFAULT 1,\n  `created_at` timestamp NULL DEFAULT current_timestamp(),\n  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `company_id` (`company_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('contact');
  },
};
