module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `agt_logs` (\n  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `tipo` enum('SERIE','FATURA','ESTADO') NOT NULL,\n  `ambiente` enum('test','production') NOT NULL DEFAULT 'test',\n  `nif` varchar(20) NOT NULL,\n  `nome_empresa` varchar(255) DEFAULT NULL,\n  `document_type` varchar(2) DEFAULT NULL,\n  `document_no` varchar(60) DEFAULT NULL,\n  `establishment` varchar(50) DEFAULT NULL,\n  `submission_uuid` varchar(36) DEFAULT NULL,\n  `request_id` varchar(100) DEFAULT NULL,\n  `sucesso` tinyint(1) NOT NULL DEFAULT 0,\n  `http_status_agt` smallint(6) DEFAULT NULL,\n  `mensagem_erro` text DEFAULT NULL,\n  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),\n  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_payload`)),\n  `criado_em` datetime(3) NOT NULL DEFAULT current_timestamp(3),\n  PRIMARY KEY (`id`),\n  KEY `idx_logs_tipo` (`tipo`),\n  KEY `idx_logs_nif` (`nif`),\n  KEY `idx_logs_document_no` (`document_no`),\n  KEY `idx_logs_request_id` (`request_id`),\n  KEY `idx_logs_criado_em` (`criado_em`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('agt_logs');
  },
};
