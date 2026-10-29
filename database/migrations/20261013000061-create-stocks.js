module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `stocks` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `name` varchar(255) NOT NULL,\n  `description` text DEFAULT NULL,\n  `created_at` datetime DEFAULT current_timestamp(),\n  `color` varchar(20) DEFAULT NULL,\n  `icon` varchar(50) DEFAULT 'box',\n  `location_type` enum('interna','externa') DEFAULT 'interna',\n  `location_detail` varchar(255) DEFAULT NULL,\n  `display_name` text DEFAULT NULL,\n  `address` varchar(255) DEFAULT NULL,\n  `address_number` varchar(100) NOT NULL,\n  `neighborhood` varchar(255) NOT NULL,\n  `city` varchar(100) DEFAULT NULL,\n  `county` varchar(100) DEFAULT NULL,\n  `state` varchar(100) DEFAULT NULL,\n  `state_district` varchar(100) DEFAULT NULL,\n  `region` varchar(100) DEFAULT NULL,\n  `country` varchar(100) DEFAULT NULL,\n  `continent` varchar(100) DEFAULT NULL,\n  `iso_region_code` varchar(20) DEFAULT NULL,\n  `osm_type` varchar(20) DEFAULT NULL,\n  `osm_id` bigint(20) DEFAULT NULL,\n  `boundingbox` text DEFAULT NULL,\n  `place_class` varchar(50) DEFAULT NULL,\n  `place_type` varchar(50) DEFAULT NULL,\n  `zip_code` varchar(50) NOT NULL,\n  `latitude` decimal(10,8) DEFAULT NULL,\n  `longitude` decimal(11,8) DEFAULT NULL,\n  `updated_by` varchar(255) DEFAULT NULL,\n  `updated_at` datetime DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `fk_stocks_company` (`company_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('stocks');
  },
};
