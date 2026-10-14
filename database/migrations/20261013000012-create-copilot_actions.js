module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `copilot_actions` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `conversation_id` int(11) NOT NULL,\n  `message_id` int(11) DEFAULT NULL,\n  `action_type` enum('send_email_collection','send_whatsapp_collection','send_report_email') NOT NULL,\n  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),\n  `status` enum('pendente','aprovada','rejeitada','executada','falhou') NOT NULL DEFAULT 'pendente',\n  `authorized_by` int(11) DEFAULT NULL,\n  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),\n  `executed_at` datetime DEFAULT NULL,\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  KEY `conversation_id` (`conversation_id`),\n  CONSTRAINT `copilot_actions_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `copilot_conversations` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('copilot_actions');
  },
};
