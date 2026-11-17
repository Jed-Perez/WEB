ALTER TABLE facilities
    ADD COLUMN image_path VARCHAR(255) NOT NULL DEFAULT '' AFTER description;
