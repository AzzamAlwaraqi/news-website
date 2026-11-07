<?php
declare(strict_types=1);

/** Opens the database, creating it with sample content on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . DATA_DIR . '/news.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE
        );
        CREATE TABLE IF NOT EXISTS articles (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            summary TEXT NOT NULL,
            body TEXT NOT NULL,
            author TEXT NOT NULL,
            image TEXT,
            category_id INTEGER NOT NULL REFERENCES categories(id),
            status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
            published_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_articles_public ON articles (status, published_at);
        CREATE TABLE IF NOT EXISTS login_attempts (
            ip TEXT NOT NULL,
            attempted_at INTEGER NOT NULL
        );
    SQL);
    if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        seed($pdo);
    }
    return $pdo;
}

/** Fills a new database with made-up articles so the site has something to show. */
function seed(PDO $pdo): void
{
    $categories = ['World', 'Business', 'Technology', 'Sports', 'Science', 'Entertainment'];
    $ids = [];
    $insert = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
    foreach ($categories as $name) {
        $insert->execute([$name, strtolower($name)]);
        $ids[$name] = (int) $pdo->lastInsertId();
    }

    $samples = [
        ['Technology', 'City library opens a free coding lab for teenagers', 'After-school sessions teach web basics, with laptops provided for anyone who needs one.', "The lab runs three evenings a week and is open to anyone aged 13 to 18.\n\nVolunteers from local companies lead the sessions, starting with HTML and moving on to small projects the students choose themselves."],
        ['Business', 'Family bakery expands to a third neighbourhood', 'Weekend queues pushed the owners to open a new branch and hire twelve more staff.', "The bakery started with a single oven twenty years ago.\n\nIts owners say the new branch will focus on bread baked through the morning, so shelves stay full after the early rush."],
        ['Sports', 'Local runners raise funds with a sunrise 10K', 'More than four hundred people took part, and every entry fee goes to the children\'s ward.', "Runners set off before dawn to beat the heat.\n\nOrganisers plan to make the race an annual event and hope to double the number of entries next year."],
        ['Science', 'Students track migrating birds with homemade sensors', 'A university project gives volunteers cheap kits for recording bird calls at night.', "Each kit costs less than a textbook and runs on a small solar panel.\n\nRecordings are uploaded each morning, and a simple model sorts the calls by species for the students to check."],
        ['World', 'Coastal towns share a plan for cleaner beaches', 'Five towns agreed on one recycling scheme and a shared calendar of volunteer clean-ups.', "The plan grew out of a meeting between school groups in each town.\n\nBins will be emptied on the same schedule along the coast, and results will be published every three months."],
        ['Entertainment', 'Open-air cinema returns to the old town square', 'Free screenings run every Friday this summer, with films picked by public vote.', "Organisers will set up the screen after sunset, and seating is first come, first served.\n\nLocal food stalls will stay open until the end of each film."],
        ['Technology', 'Volunteers repair old phones for job seekers', 'A weekend repair club has handed out more than two hundred working phones this year.', "Donated phones are cleaned, repaired and wiped before they are passed on.\n\nThe club also runs short classes on writing a CV and applying for jobs online."],
        ['Business', 'Market traders try a shared delivery van', 'Sharing one van has cut costs for a dozen small stalls and reduced morning traffic.', "Traders split the running costs according to how much each one delivers.\n\nAfter a three-month trial, the group is deciding whether to buy a second van."],
    ];
    $insert = $pdo->prepare("INSERT INTO articles (title, slug, summary, body, author, category_id, status, published_at) VALUES (?, ?, ?, ?, 'News Desk', ?, 'published', ?)");
    foreach ($samples as $i => [$category, $title, $summary, $body]) {
        $date = date('Y-m-d H:i:s', strtotime('-' . ($i * 2 + 1) . ' days'));
        $insert->execute([$title, slugify($title), $summary, $body, $ids[$category], $date]);
    }
}

function all_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
}

/** Makes search text literal, so % and _ are not treated as wildcards. */
function like_pattern(string $text): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%';
}
