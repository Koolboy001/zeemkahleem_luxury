# ZEEMKAHLEEM LUXURY - Multi-file Prototype

Files:
- index.php  : Home page (inline HTML/CSS/JS)
- admin.php  : Admin area (inline HTML/CSS/JS)
- cart.php   : Cart and checkout handling (inline HTML/CSS/JS)
- config.php : Shared config and DB bootstrap
- uploads/    : Image uploads (must be writable)

Quick setup:
1. Put these files into your web server directory.
2. Edit `config.php` for DB credentials and WhatsApp number.
3. Ensure `uploads/` is writable.
4. Open `index.php` in a browser. Default admin: owner / secret123

Notes:
- Each PHP file contains inline HTML/CSS/JS.
- For production, split front/back, add CSRF protections, and secure uploads.
