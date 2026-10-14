module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `instructions` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) DEFAULT NULL,\n  `title` varchar(180) NOT NULL,\n  `question` text DEFAULT NULL,\n  `content` text NOT NULL,\n  `steps` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`steps`)),\n  `tips` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tips`)),\n  `youtube_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`youtube_urls`)),\n  `keywords` varchar(500) DEFAULT NULL,\n  `active` tinyint(1) NOT NULL DEFAULT 1,\n  `priority` int(11) NOT NULL DEFAULT 0,\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  KEY `instructions_company_id_active` (`company_id`,`active`),\n  KEY `instructions_priority` (`priority`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('instructions');
  },
};
