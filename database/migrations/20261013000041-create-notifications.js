module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `notifications` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `user_id` int(10) unsigned NOT NULL,\n  `company_id` int(11) DEFAULT NULL,\n  `type` varchar(50) NOT NULL,\n  `title` varchar(150) NOT NULL,\n  `message` text NOT NULL,\n  `is_read` tinyint(1) NOT NULL DEFAULT 0,\n  `created_at` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `idx_notifications_company` (`company_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('notifications');
  },
};
