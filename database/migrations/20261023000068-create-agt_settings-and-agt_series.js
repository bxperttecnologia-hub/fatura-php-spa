// Faturação eletrónica AGT: chave privada do contribuinte (cifrada) e séries de numeração por empresa.
module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `agt_settings` (\n  `company_id` int(11) NOT NULL,\n  `environment` enum('test','production') NOT NULL DEFAULT 'test',\n  `tax_id` varchar(20) NOT NULL,\n  `establishment` varchar(200) NOT NULL DEFAULT 'SEDE',\n  `private_key_enc` text NOT NULL,\n  `key_fingerprint` char(64) NOT NULL,\n  `key_bits` int(11) DEFAULT NULL,\n  `updated_by` int(11) DEFAULT NULL,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`company_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    await queryInterface.sequelize.query("CREATETABLE `agt_series` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `environment` enum('test','production') NOT NULL DEFAULT 'test',\n  `series_code` varchar(60) NOT NULL,\n  `document_type` char(2) NOT NULL,\n  `series_year` smallint(6) NOT NULL,\n  `establishment` varchar(200) NOT NULL DEFAULT 'SEDE',\n  `contingency` char(1) NOT NULL DEFAULT 'N',\n  `status` char(1) DEFAULT NULL,\n  `invoicing_method` varchar(4) DEFAULT NULL,\n  `first_document_no` varchar(60) DEFAULT NULL,\n  `last_document_no` varchar(60) DEFAULT NULL,\n  `last_document_created` varchar(60) DEFAULT NULL,\n  `authorized_quantity` varchar(20) DEFAULT NULL,\n  `is_default` tinyint(1) NOT NULL DEFAULT 0,\n  `created_by` int(11) DEFAULT NULL,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  `synced_at` timestamp NULL DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_agt_series` (`company_id`,`environment`,`series_code`),\n  KEY `idx_agt_series_type` (`company_id`,`environment`,`document_type`,`series_year`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  },

  async down(queryInterface) {
    await queryInterface.dropTable("agt_series");
    await queryInterface.dropTable("agt_settings");
  },
};
