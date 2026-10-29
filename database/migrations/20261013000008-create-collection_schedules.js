module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("CREATE TABLE `collection_schedules` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `invoice_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`invoice_ids`)),\n  `channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`channels`)),\n  `subject` varchar(255) NOT NULL,\n  `message` text NOT NULL,\n  `scheduled_at` datetime NOT NULL,\n  `status` enum('scheduled','processing','sent','partial','failed') DEFAULT 'scheduled',\n  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),\n  `createdAt` datetime NOT NULL,\n  `updatedAt` datetime NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  },

  async down(queryInterface) {
    await queryInterface.dropTable('collection_schedules');
  },
};
