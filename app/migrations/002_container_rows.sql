-- Container boxes (PLAN.md section 6.13): a row may live inside a container box.
ALTER TABLE rows ADD COLUMN parent_box_id INTEGER NULL;
CREATE INDEX rows_parent ON rows (parent_box_id, position);
