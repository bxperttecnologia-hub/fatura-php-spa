'use strict';

const bcrypt = require('bcryptjs');
const crypto = require('node:crypto');
const { QueryTypes } = require('sequelize');

const adminEmail = () => (process.env.SEED_ADMIN_EMAIL || 'admin@exemplo.com').trim().toLowerCase();
const companyName = () => process.env.SEED_COMPANY_NAME || 'Empresa Demo';

module.exports = {
  async up(queryInterface) {
    const { sequelize } = queryInterface;
    const email = adminEmail();

    const existing = await sequelize.query(
      'SELECT id FROM `users` WHERE `email` = :email LIMIT 1',
      { replacements: { email }, type: QueryTypes.SELECT },
    );
    if (existing.length) {
      console.log(`[seed-admin] ${email} já existe; nada a fazer.`);
      return;
    }

    let password = process.env.SEED_ADMIN_PASSWORD;
    let generated = false;
    if (!password) {
      password = crypto.randomBytes(9).toString('base64url');
      generated = true;
    }
    const passwordHash = await bcrypt.hash(password, 10);

    const [companyId] = await sequelize.query(
      'INSERT INTO `companies` (`name`, `registration_number`, `phone_ddi`) VALUES (:name, :nif, :ddi)',
      { replacements: { name: companyName(), nif: '0000000000', ddi: '+244' }, type: QueryTypes.INSERT },
    );

    const [userId] = await sequelize.query(
      `INSERT INTO \`users\`
        (\`username\`, \`name\`, \`phone\`, \`email\`, \`password\`, \`gender\`, \`identification_type\`,
         \`identification\`, \`address\`, \`address_number\`, \`address_neighborhood\`, \`address_district\`,
         \`zip_code\`, \`country\`, \`blocked\`, \`currency\`, \`is_active\`, \`adm\`)
       VALUES
        (:username, :name, :phone, :email, :password, 'prefer_not_to_say', 1,
         '', '', '', '', '', '', 'Angola', 0, 'AOA', 1, 1)`,
      {
        replacements: {
          username: email.split('@')[0],
          name: 'Administrador',
          phone: '000000000',
          email,
          password: passwordHash,
        },
        type: QueryTypes.INSERT,
      },
    );

    await sequelize.query(
      "INSERT INTO `company_has_user` (`company_id`, `user_id`, `role`) VALUES (:companyId, :userId, 'owner')",
      { replacements: { companyId, userId }, type: QueryTypes.INSERT },
    );

    console.log(`[seed-admin] Administrador criado: ${email}`);
    if (generated) {
      console.log(`[seed-admin] Palavra-passe temporária (mostrada só agora): ${password}`);
    }
  },

  async down(queryInterface) {
    const { sequelize } = queryInterface;
    const email = adminEmail();
    const users = await sequelize.query(
      'SELECT id FROM `users` WHERE `email` = :email',
      { replacements: { email }, type: QueryTypes.SELECT },
    );
    for (const { id } of users) {
      await sequelize.query('DELETE FROM `company_has_user` WHERE `user_id` = :id', { replacements: { id } });
      await sequelize.query('DELETE FROM `users` WHERE `id` = :id', { replacements: { id } });
    }
  },
};
