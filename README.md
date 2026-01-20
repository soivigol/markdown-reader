# Markdown Reader

A simple PHP application that reads markdown files and displays them with formatted HTML output. All pages include a `noindex` meta tag for SEO purposes.

**Perfect for shared hosting** - No SSH access or Composer required! Just upload files via FTP/cPanel.

## Features

- Clean URL routing (no `.md` extension in URLs)
- Automatic markdown to HTML conversion
- Beautiful, responsive styling
- Noindex meta tag on all pages
- 404 error handling
- **No Composer required** - Single file library included

## Installation for Shared Hosting (No SSH Required)

### Quick Setup

1. **Download all files** from this repository:
   - `index.php`
   - `Parsedown.php`
   - `.htaccess`
   - Your markdown files (`.md`)

2. **Upload via FTP or cPanel File Manager**:
   - Upload all files to your web root directory (usually `public_html` or `www`)
   - Make sure `.htaccess` is uploaded (it may be hidden in some FTP clients)

3. **Place your markdown files**:
   - Put `.md` files in the root or subdirectories
   - Access them via clean URLs (see URL Structure below)

4. **That's it!** No Composer, no SSH, no command line needed.

### Local Development (Optional)

If you want to test locally before uploading:

```bash
composer install  # Only needed if you want to update Parsedown library
php -S localhost:8000
```

## URL Structure

- **Root**: `www.domain.com` → shows list of folders and markdown files
- **Root file**: `www.domain.com/file-name-example` → displays `file-name-example.md`
- **Folder**: `www.domain.com/folder-name` → shows list of markdown files in that folder
- **Subdirectory file**: `www.domain.com/folder-name/file-name` → displays `folder-name/file-name.md`

## Server Configuration

### Apache

The included `.htaccess` file should work automatically if `mod_rewrite` is enabled.

### Nginx

Add this configuration to your Nginx server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### PHP Built-in Server (Development)

For development, you can use PHP's built-in server:

```bash
php -S localhost:8000
```

Note: The built-in server doesn't support `.htaccess`, so URLs will need to include `index.php`:
- `localhost:8000/index.php/file-name-example`

## Example Files

Create markdown files like:

- `file-name-example.md` - accessible at `/file-name-example`
- `folder-name/file-name.md` - accessible at `/folder-name/file-name`

**Folder Navigation:**
- Accessing `/folder-name` will show a list of all markdown files in that folder
- The root URL (`/`) shows both folders and files for easy navigation

## Requirements

- PHP 7.4 or higher
- Web server with URL rewriting support (Apache with mod_rewrite or Nginx)
- **No Composer required** - Parsedown library is included as a single file

## Deployment Checklist for Shared Hosting

- [ ] Upload `index.php` to web root
- [ ] Upload `Parsedown.php` to web root (same directory as `index.php`)
- [ ] Upload `.htaccess` to web root (enable "Show hidden files" in FTP client if needed)
- [ ] Upload your markdown files (`.md`) to root or subdirectories
- [ ] Test by visiting `yourdomain.com/file-name-example` (replace with your actual file name)
- [ ] Verify `.htaccess` is working (if URLs show 404, check if mod_rewrite is enabled on your hosting)

## Troubleshooting

**URLs return 404 errors:**
- Check if `.htaccess` file was uploaded correctly
- Verify your hosting supports Apache mod_rewrite (most shared hosting does)
- Try accessing `yourdomain.com/index.php/file-name-example` as a workaround

**Files not showing:**
- Make sure markdown files have `.md` extension
- Check file permissions (should be readable, typically 644)
- Verify files are in the correct directory structure
