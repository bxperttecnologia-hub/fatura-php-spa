module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `proforma_sequences` (\n  `id` int(11) NOT NULL,\n  `company_id` int(11) NOT NULL,\n  `year` int(11) NOT NULL,\n  `last_number` int(11) NOT NULL DEFAULT 0,\n  `created_at` timestamp NOT NULL DEFAULT current_timestamp()\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('proforma_sequences');
  },
};
