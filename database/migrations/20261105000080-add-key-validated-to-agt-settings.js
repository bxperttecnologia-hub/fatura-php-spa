// Chave AGT guardada mesmo com a AGT em baixo: key_validated_at = NULL até a AGT aceitar
// uma operação assinada com ela. As chaves existentes já foram validadas.
module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `agt_settings` ADD COLUMN `key_validated_at` datetime DEFAULT NULL AFTER `key_bits`;");
    await queryInterface.sequelize.query("UPDATE `agt_settings` SET `key_validated_at` = `updated_at`;");
  },

  async down(queryInterface) {
    await queryInterface.sequelize.query("ALTER TABLE `agt_settings` DROP COLUMN `key_validated_at`;");
  },
};
