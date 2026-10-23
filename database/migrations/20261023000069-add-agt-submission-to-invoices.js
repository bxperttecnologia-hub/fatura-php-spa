// Envio das faturas à AGT (registarFactura / obterEstado) e código de isenção por omissão da empresa.
module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `invoices` ADD COLUMN `agt_series_id` int(11) DEFAULT NULL, ADD COLUMN `agt_status` varchar(12) DEFAULT NULL COMMENT 'pending | sent | valid | invalid | error', ADD COLUMN `agt_request_id` varchar(20) DEFAULT NULL, ADD COLUMN `agt_errors` text DEFAULT NULL, ADD COLUMN `agt_system_entry_date` datetime DEFAULT NULL, ADD COLUMN `agt_sent_at` datetime DEFAULT NULL, ADD COLUMN `agt_checked_at` datetime DEFAULT NULL, ADD KEY `idx_invoices_agt_status` (`company_id`,`agt_status`);");
    await queryInterface.sequelize.query("ALTER TABLE `agt_settings` ADD COLUMN `exemption_code` varchar(3) NOT NULL DEFAULT 'M00' AFTER `establishment`;");
  },

  async down(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `agt_settings` DROP COLUMN `exemption_code`;");
    await queryInterface.sequelize.query("ALTER TABLE `invoices` DROP KEY `idx_invoices_agt_status`, DROP COLUMN `agt_series_id`, DROP COLUMN `agt_status`, DROP COLUMN `agt_request_id`, DROP COLUMN `agt_errors`, DROP COLUMN `agt_system_entry_date`, DROP COLUMN `agt_sent_at`, DROP COLUMN `agt_checked_at`;");
  },
};
