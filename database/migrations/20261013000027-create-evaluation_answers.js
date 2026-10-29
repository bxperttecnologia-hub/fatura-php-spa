module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `evaluation_answers` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `evaluation_id` int(11) NOT NULL,\n  `criterion_id` int(11) NOT NULL,\n  `score` decimal(5,2) NOT NULL,\n  `comment` text DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `evaluation_id` (`evaluation_id`),\n  KEY `criterion_id` (`criterion_id`),\n  CONSTRAINT `evaluation_answers_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE,\n  CONSTRAINT `evaluation_answers_ibfk_2` FOREIGN KEY (`criterion_id`) REFERENCES `evaluation_criteria` (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('evaluation_answers');
  },
};
