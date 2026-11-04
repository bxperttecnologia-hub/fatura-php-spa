// Recibos (RC) na faturação eletrónica: numeração "RC <série>/<n>" (série até 60 caracteres) e envio à AGT.
module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `receipts` MODIFY `serie` varchar(60) NOT NULL, ADD COLUMN `agt_series_id` int(11) DEFAULT NULL, ADD COLUMN `agt_status` varchar(12) DEFAULT NULL COMMENT 'sent | valid | invalid | error', ADD COLUMN `agt_request_id` varchar(20) DEFAULT NULL, ADD COLUMN `agt_errors` text DEFAULT NULL, ADD COLUMN `agt_system_entry_date` datetime DEFAULT NULL, ADD COLUMN `agt_sent_at` datetime DEFAULT NULL, ADD COLUMN `agt_checked_at` datetime DEFAULT NULL, ADD KEY `idx_receipts_agt_status` (`agt_status`);");
  },

  async down(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `receipts` DROP KEY `idx_receipts_agt_status`, DROP COLUMN `agt_series_id`, DROP COLUMN `agt_status`, DROP COLUMN `agt_request_id`, DROP COLUMN `agt_errors`, DROP COLUMN `agt_system_entry_date`, DROP COLUMN `agt_sent_at`, DROP COLUMN `agt_checked_at`, MODIFY `serie` varchar(10) NOT NULL;");
  },
};
