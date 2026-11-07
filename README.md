# News Website

A news site with a public front page and a password-protected admin area, built with PHP and SQLite.

**For visitors:** latest articles, categories, search, pagination, article pages and dark mode.

**For admins:** create, edit, publish, unpublish and delete articles, upload pictures, manage categories and change the password.

## Run it

You need PHP 8.1 or newer with the `pdo_sqlite` and `fileinfo` extensions.

```bash
php -S localhost:8000 -t public
```

Open http://localhost:8000. The database is created on the first visit, with made-up sample articles.

Go to http://localhost:8000/admin/ to create the admin account. That page only works while no admin exists, so create the account right away if you put the site online. Point your web server at the `public` folder, never the project root, so the `data` folder can't be downloaded.

## Project layout

```
public/          Pages the web server can reach
  index.php      Front page, categories and search
  article.php    One article
  admin/         Login, articles, categories and account pages
  assets/        CSS and JavaScript
  uploads/       Pictures uploaded by admins
src/             Shared code: database, helpers and page layout
data/            Database and sessions, created on first run and not committed
```

## Security

- Passwords are stored as hashes, and repeated failed logins are locked out for 15 minutes.
- Every admin form has a CSRF token, and all database queries use prepared statements.
- All output is escaped, and a Content Security Policy blocks inline and third-party scripts.
- Uploads are checked by their actual contents, limited to 2 MB and saved under random names.

## Credit

This began as a course project that used a front-end page credited to Hardik Desai. It has since been rewritten as a PHP application.
