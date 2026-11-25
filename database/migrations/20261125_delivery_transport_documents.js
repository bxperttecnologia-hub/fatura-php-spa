'use strict';

module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query(
      "ALTER TABLE delivery_notes ADD COLUMN IF NOT EXISTS status VARCHAR(32) NOT NULL DEFAULT 'draft', ADD COLUMN IF NOT EXISTS emitted_at DATETIME NULL, ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL, ADD COLUMN IF NOT EXISTS cancelled_by INT NULL, ADD COLUMN IF NOT EXISTS delivery_status VARCHAR(32) NOT NULL DEFAULT 'pending', ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL, ADD COLUMN IF NOT EXISTS receiver_name VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS receiver_document VARCHAR(100) NULL, ADD COLUMN IF NOT EXISTS signature_reference VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS origin_warehouse_id INT NULL, ADD COLUMN IF NOT EXISTS destination_warehouse_id INT NULL, ADD COLUMN IF NOT EXISTS origin_document_type VARCHAR(32) NULL, ADD COLUMN IF NOT EXISTS origin_document_id INT UNSIGNED NULL, ADD COLUMN IF NOT EXISTS proforma_id INT NULL, ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL"
    );
    await queryInterface.sequelize.query(
      "ALTER TABLE delivery_note_items ADD COLUMN IF NOT EXISTS delivered_quantity DECIMAL(14,3) NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS returned_quantity DECIMAL(14,3) NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS in_transit_quantity DECIMAL(14,3) NOT NULL DEFAULT 0"
    );
    await queryInterface.sequelize.query(
      "ALTER TABLE guides ADD COLUMN IF NOT EXISTS company_id INT NULL, ADD COLUMN IF NOT EXISTS number INT UNSIGNED NULL, ADD COLUMN IF NOT EXISTS status VARCHAR(32) NOT NULL DEFAULT 'draft', ADD COLUMN IF NOT EXISTS emitted_at DATETIME NULL, ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL, ADD COLUMN IF NOT EXISTS cancelled_by INT NULL, ADD COLUMN IF NOT EXISTS transport_status VARCHAR(32) NOT NULL DEFAULT 'em_preparacao', ADD COLUMN IF NOT EXISTS transporter_name VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS transporter_document VARCHAR(100) NULL, ADD COLUMN IF NOT EXISTS driver_name VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS driver_document VARCHAR(100) NULL, ADD COLUMN IF NOT EXISTS driver_phone VARCHAR(50) NULL, ADD COLUMN IF NOT EXISTS vehicle_description VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS departure_at DATETIME NULL, ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL, ADD COLUMN IF NOT EXISTS origin_warehouse_id INT NULL, ADD COLUMN IF NOT EXISTS destination_warehouse_id INT NULL, ADD COLUMN IF NOT EXISTS origin_document_type VARCHAR(32) NULL, ADD COLUMN IF NOT EXISTS origin_document_id INT NULL, ADD COLUMN IF NOT EXISTS invoice_id INT NULL, ADD COLUMN IF NOT EXISTS proforma_id INT NULL, ADD COLUMN IF NOT EXISTS user_id INT NULL, ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL"
    );
    await queryInterface.sequelize.query(
      "ALTER TABLE guide_items MODIFY COLUMN quantity DECIMAL(14,3) NOT NULL DEFAULT 1, ADD COLUMN IF NOT EXISTS delivered_quantity DECIMAL(14,3) NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS returned_quantity DECIMAL(14,3) NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS in_transit_quantity DECIMAL(14,3) NOT NULL DEFAULT 0"
    );
    await queryInterface.sequelize.query(
      "ALTER TABLE stock_items MODIFY COLUMN quantity DECIMAL(14,3) NOT NULL DEFAULT 0"
    );
    await queryInterface.sequelize.query(
      "ALTER TABLE stock_movements MODIFY COLUMN quantity DECIMAL(14,3) NOT NULL"
    );
    await queryInterface.sequelize.query(
      "CREATE TABLE IF NOT EXISTS delivery_document_events (id INT UNSIGNED NOT NULL AUTO_INCREMENT, company_id INT NOT NULL, document_type VARCHAR(32) NOT NULL, document_id INT UNSIGNED NOT NULL, event_type VARCHAR(32) NOT NULL, previous_status VARCHAR(32) NULL, new_status VARCHAR(32) NULL, payload JSON NULL, user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_delivery_document_events_document (document_type, document_id), KEY idx_delivery_document_events_company (company_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    await queryInterface.sequelize.query(
      "CREATE TABLE IF NOT EXISTS document_reprints (id INT UNSIGNED NOT NULL AUTO_INCREMENT, company_id INT NOT NULL, document_type VARCHAR(32) NOT NULL, document_id INT UNSIGNED NOT NULL, copy_label VARCHAR(32) NOT NULL DEFAULT 'SEGUNDA VIA', user_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_document_reprints_document (document_type, document_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
  },

  async down() {
    // Intentionally additive-only: do not remove operational or audit history.
  },
};
