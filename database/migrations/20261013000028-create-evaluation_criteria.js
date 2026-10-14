module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `evaluation_criteria` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `template_id` int(11) NOT NULL,\n  `label` varchar(255) NOT NULL,\n  `weight` decimal(5,2) DEFAULT 1.00,\n  PRIMARY KEY (`id`),\n  KEY `template_id` (`template_id`),\n  CONSTRAINT `evaluation_criteria_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `evaluation_templates` (`id`) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('evaluation_criteria');
  },
};
