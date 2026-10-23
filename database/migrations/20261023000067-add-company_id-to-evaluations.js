module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `evaluations` ADD COLUMN `company_id` int(11) NOT NULL AFTER `id`, ADD KEY `idx_evaluations_company` (`company_id`);");
  },

  async down(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `evaluations` DROP KEY `idx_evaluations_company`, DROP COLUMN `company_id`;");
  },
};
