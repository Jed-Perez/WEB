# Room photo text removal

Method: built-in image generation/editing tool. Original JPEGs retained. The cleaned photos are saved under `assets/img/`.

Family Room 02 prompt:

> Use case: precise-object-edit. Edit target: the supplied Family Room 02 photograph with wooden bunk beds. Remove ONLY the black overlaid text 'Room 4' at the top center. Reconstruct the plain ceiling seamlessly beneath those letters, continuing any ceiling seams naturally. Preserve the exact room, furniture, number and positions of beds, colors, lighting, camera angle, framing and aspect ratio. Do not crop, redesign, retouch or change any other object. Output one clean photograph without text.

Ocean Room 01 prompt:

> Use case: precise-object-edit. Edit target: the supplied Ocean Room 01 photograph with dark bed frames and brown sofa. Remove ONLY the black overlaid text 'Room 3' at the top center. Reconstruct the plain ceiling seamlessly beneath those letters, continuing any ceiling seams naturally. Preserve the exact room, furniture, number and positions of beds, colors, lighting, camera angle, framing and aspect ratio. Do not crop, redesign, retouch or change any other object. Output one clean photograph without text.

## New room photos

The supplied Room 1 and Room 2 photos have their top-center labels removed. The cleaned copies are `assets/img/family-room-1-clean.png` and `assets/img/ocean-room-2-clean.png`; their paths are saved on the Family Room 1 and Ocean Room 2 facility records.

The subsequently supplied Room 1 photo is saved as `assets/img/family-room-2-clean.png` and assigned to Family Room 2. The Room 2 photo is saved as `assets/img/ocean-room-1-clean.png` and assigned to Ocean Room 1.

Facility photos are stored in the `facilities.image_path` column as paths relative to the project root. The Room & cottages gallery, facility table, reservation selector, and availability results read this value from the facility record. For another photo, place the image in `assets/img/` and save a path such as `assets/img/room-photo.jpg` in the facility form.
