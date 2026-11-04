// Notas de crédito (NC) na faturação eletrónica: número "NC <série>/<n>", envio à AGT
// e NC parcial (credit_note_items.invoice_item_id liga cada linha creditada à linha da fatura).
module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `credit_notes` ADD COLUMN `reference` varchar(200) DEFAULT NULL AFTER `invoice_id`, ADD COLUMN `agt_series_id` int(11) DEFAULT NULL, ADD COLUMN `agt_status` varchar(12) DEFAULT NULL COMMENT 'sent | valid | invalid | error', ADD COLUMN `agt_request_id` varchar(20) DEFAULT NULL, ADD COLUMN `agt_errors` text DEFAULT NULL, ADD COLUMN `agt_system_entry_date` datetime DEFAULT NULL, ADD COLUMN `agt_sent_at` datetime DEFAULT NULL, ADD COLUMN `agt_checked_at` datetime DEFAULT NULL, ADD KEY `idx_credit_notes_agt_status` (`company_id`,`agt_status`);");
    await queryInterface.sequelize.query("ALTER TABLE `credit_note_items` ADD COLUMN `invoice_item_id` int(11) DEFAULT NULL AFTER `credit_note_id`, ADD KEY `idx_cni_invoice_item` (`invoice_item_id`);");
  },

  async down(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `credit_note_items` DROP KEY `idx_cni_invoice_item`, DROP COLUMN `invoice_item_id`;");
    await queryInterface.sequelize.query("ALTER TABLE `credit_notes` DROP KEY `idx_credit_notes_agt_status`, DROP COLUMN `reference`, DROP COLUMN `agt_series_id`, DROP COLUMN `agt_status`, DROP COLUMN `agt_request_id`, DROP COLUMN `agt_errors`, DROP COLUMN `agt_system_entry_date`, DROP COLUMN `agt_sent_at`, DROP COLUMN `agt_checked_at`;");
  },
};
