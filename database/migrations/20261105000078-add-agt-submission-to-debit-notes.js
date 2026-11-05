// Notas de débito (ND) na faturação eletrónica: número "ND <série>/<n>" e envio à AGT.
// agt_status deixa de ser 'blocked' por omissão (NULL = sem numeração AGT).
module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `debit_notes` MODIFY `serie` varchar(60) NOT NULL DEFAULT 'ND', MODIFY `agt_status` varchar(12) DEFAULT NULL COMMENT 'sent | valid | invalid | error', ADD COLUMN `reference` varchar(200) DEFAULT NULL AFTER `invoice_id`, ADD COLUMN `agt_series_id` int(11) DEFAULT NULL, ADD COLUMN `agt_request_id` varchar(20) DEFAULT NULL, ADD COLUMN `agt_errors` text DEFAULT NULL, ADD COLUMN `agt_system_entry_date` datetime DEFAULT NULL, ADD COLUMN `agt_sent_at` datetime DEFAULT NULL, ADD COLUMN `agt_checked_at` datetime DEFAULT NULL;");
    await queryInterface.sequelize.query("UPDATE `debit_notes` SET `agt_status` = NULL WHERE `agt_status` = 'blocked';");
  },

  async down(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `debit_notes` DROP COLUMN `reference`, DROP COLUMN `agt_series_id`, DROP COLUMN `agt_request_id`, DROP COLUMN `agt_errors`, DROP COLUMN `agt_system_entry_date`, DROP COLUMN `agt_sent_at`, DROP COLUMN `agt_checked_at`, MODIFY `agt_status` varchar(12) NOT NULL DEFAULT 'blocked', MODIFY `serie` varchar(10) NOT NULL DEFAULT 'ND';");
  },
};
