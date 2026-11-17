UPDATE facilities
SET image_path=CASE name
    WHEN 'Ocean Room 1' THEN 'assets/img/ocean-room-1-clean.png'
    WHEN 'Family Room 2' THEN 'assets/img/family-room-2-clean.png'
END
WHERE name IN ('Ocean Room 1','Family Room 2') AND type='room';
