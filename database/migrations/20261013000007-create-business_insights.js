module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `business_insights` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `category` enum('oportunidade','risco','financeiro','rh','comercial','stock') NOT NULL,\n  `severity` enum('critico','atencao','aviso','info','oportunidade') NOT NULL DEFAULT 'info',\n  `entity_type` varchar(255) DEFAULT NULL,\n  `entity_id` int(11) DEFAULT NULL,\n  `title` varchar(255) NOT NULL,\n  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence`)),\n  `recommendation` text DEFAULT NULL,\n  `estimated_impact` double DEFAULT NULL,\n  `dedupe_key` varchar(255) NOT NULL,\n  `status` enum('aberto','resolvido','ignorado') NOT NULL DEFAULT 'aberto',\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `business_insights_company_id_dedupe_key` (`company_id`,`dedupe_key`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('business_insights');
  },
};
