UPDATE facilities
SET description=CONCAT_WS(CHAR(10),
    'Room Inclusions:',
    'Airconditioned Rooms',
    'Bed + Extra Mattress',
    'Own/ Common CR',
    'Own Kitchen',
    'Free use of Kitchen Utensils',
    'Free WiFi',
    'Free open cottage / Tambayan Area',
    'Free use of Videoke until 10 PM',
    'Details:',
    'Beach Front + Swimming Pool Access',
    'No Entrance Fee',
    'No Corkage Fee',
    'Highlight:',
    'PERFECT FOR FAMILY, BARKADA & TEAM OUTING!')
WHERE name IN ('Ocean Room 1','Ocean Room 2','Family Room 1','Family Room 2')
  AND type='room';
