Local Shepherd installation

This project prefers to load Shepherd from a local copy when available to avoid CDN failures and to support offline development.

To install Shepherd locally, download the Shepherd distribution and place the files below under `webroot/assets/vendors/shepherd/`:

- `shepherd.min.js`  (Shepherd UMD build)
- `shepherd.css`     (Shepherd CSS)

You can obtain these files from the official distribution (example):
- https://unpkg.com/shepherd.js/dist/js/shepherd.min.js
- https://unpkg.com/shepherd.js/dist/css/shepherd.css

Place those two files into this folder. The loader will attempt to use the local files first; if they are not present it will fall back to the CDN.

Notes:
- If you want to vendor the library via npm, copy the build artifacts into this folder during your build step.
- Keep file names exact: `shepherd.min.js` and `shepherd.css`.
- After placing the files, hard-refresh your browser or clear cache to ensure the local versions load.
