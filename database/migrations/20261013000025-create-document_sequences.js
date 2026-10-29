module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `document_sequences` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `company_id` int(11) NOT NULL,\n  `document_type` varchar(50) NOT NULL,\n  `year` int(11) NOT NULL,\n  `last_number` int(11) NOT NULL DEFAULT 0,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uniq_sequence` (`company_id`,`document_type`,`year`),\n  KEY `idx_company` (`company_id`),\n  KEY `idx_doc_type` (`document_type`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('document_sequences');
  },
};
