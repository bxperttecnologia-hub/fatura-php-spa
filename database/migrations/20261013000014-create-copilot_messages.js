module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `copilot_messages` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `conversation_id` int(11) NOT NULL,\n  `role` enum('user','assistant') NOT NULL,\n  `content` text NOT NULL,\n  `intent` varchar(255) DEFAULT NULL,\n  `data_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_used`)),\n  `provider` varchar(255) DEFAULT NULL,\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  KEY `conversation_id` (`conversation_id`),\n  CONSTRAINT `copilot_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `copilot_conversations` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('copilot_messages');
  },
};
