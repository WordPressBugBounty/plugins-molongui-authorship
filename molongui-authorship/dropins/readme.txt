Understanding the Drop-in Folder Functionality
==============================================

**Functionality**: All PHP files within this drop-in folder, except those named `index.php`, will be automatically
executed by the plugin. Please proceed with caution when adding files to this folder, as they will be removed during the
next plugin update.

Why is this folder useful?
--------------------------
This drop-in folder is designed to facilitate temporary modifications, such as filters and actions that you may want to
test or implement temporarily. It is also used for custom modifications provided by the Molongui Support Team to address
specific issues you might be encountering.

Important Notes:
----------------

* **Temporary Use**: Files placed in this folder are intended for short-term use. Since files are removed upon updating
the plugin, any permanent changes should be implemented differently.
* **Safety Precautions**: Ensure that only trusted and tested PHP files are added to this folder to prevent errors or
security issues.