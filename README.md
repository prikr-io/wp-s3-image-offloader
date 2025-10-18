# Offloading WordPress images to a AWS S3 bucket

### Notes

- AWS credentials are loaded from the WordPress options (`wps3_image_offloader`) via the custom credentials provider in `offloader/MediaOffloaderInit.php`, so nothing needs to touch `~/.aws/credentials`.
- We intentionally upload alleen het brondbestand; de CDN (Thumbor filters) genereert variant-groottes on-the-fly, dus losse WP-thumbnailbestanden hoeven niet naar S3.
- `functions/customSizesClass.php` neemt de `srcset`, `wp_get_attachment_image_src`, en admin-thumbnail filters over zodat CDN URL’s en gewenste afmetingen gebruikt worden.
- Admin previews gebruiken nu de CDN-variant via `filterAdminPostThumbnailHtml` en `buildImageAttributes`, waardoor onnodig grote originials vermeden worden.

### Pending ideas

- Maak het CDN-domein en/of S3-pad configureerbaar via de instellingenpagina als we meerdere buckets of CDN-hostnames willen ondersteunen.
