# Offloading WordPress images to a AWS S3 bucket

### TODO:

- How do we access the ~/.aws/credentials file on the server. This is required to keep the access keys hidden. They are now hardcoded in the plugin for development purposes.
- Should all WP thumbnail creations be added to s3?
- How do we process srcset attributes?
- Currently the Admin Images are all loaded in full quality, as we have not yet fixed the uploading of multiple image-sizes. Have a look at the function `alter_attachment_preview_source` to see what's happening.
- ...

### Optional todo:

-
