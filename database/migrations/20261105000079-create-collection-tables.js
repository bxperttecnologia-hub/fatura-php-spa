// Cobranças automáticas: configuração por empresa e registo dos avisos enviados (um por fatura/etapa/canal).
const SETTINGS = "CREATE TABLE IF NOT EXISTS `collection_settings` ("
  + " `company_id` int(11) NOT NULL,"
  + " `enabled` tinyint(1) NOT NULL DEFAULT 0,"
  + " `reminder_days` varchar(60) NOT NULL DEFAULT '-3,0,7,15,30' COMMENT 'dias em relação ao vencimento (negativo = antes)',"
  + " `email_subject` varchar(200) DEFAULT NULL,"
  + " `email_message` text DEFAULT NULL,"
  + " `updated_by` int(11) DEFAULT NULL,"
  + " `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),"
  + " PRIMARY KEY (`company_id`)"
  + ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

const REMINDERS = "CREATE TABLE IF NOT EXISTS `collection_reminders` ("
  + " `id` int(10) unsigned NOT NULL AUTO_INCREMENT,"
  + " `company_id` int(11) NOT NULL,"
  + " `invoice_id` int(11) NOT NULL,"
  + " `stage_day` int(11) NOT NULL COMMENT 'etapa: dias em relação ao vencimento',"
  + " `channel` varchar(10) NOT NULL DEFAULT 'email',"
  + " `recipient` varchar(190) DEFAULT NULL,"
  + " `amount_due` decimal(15,2) NOT NULL DEFAULT 0,"
  + " `status` varchar(10) NOT NULL COMMENT 'sent | failed',"
  + " `error` text DEFAULT NULL,"
  + " `trigger` varchar(10) NOT NULL DEFAULT 'cron' COMMENT 'cron | manual',"
  + " `created_at` timestamp NOT NULL DEFAULT current_timestamp(),"
  + " PRIMARY KEY (`id`),"
  + " UNIQUE KEY `uq_collection_invoice_stage` (`invoice_id`,`stage_day`,`channel`),"
  + " KEY `idx_collection_company` (`company_id`,`created_at`)"
  + ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query(SETTINGS);
    await queryInterface.sequelize.query(REMINDERS);
  },

  async down(queryInterface) {
    await queryInterface.sequelize.query('DROP TABLE IF EXISTS `collection_reminders`');
    await queryInterface.sequelize.query('DROP TABLE IF EXISTS `collection_settings`');
  },
};
