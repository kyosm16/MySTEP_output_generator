# MySTEP Output Generator

A PHP-based web application for preparing monthly MySTEP output evaluation forms. Users can enter staff details, record task targets and achievements, calculate achievement percentages and eligible wages automatically, and generate a printable evaluation form that can be printed or saved as a PDF.

## Features

### Monthly output form
- Collects staff name, identification number, division/unit, assessment month and year, and gross wage.
- Supports multiple task cards in one form.
- Includes predefined task categories, with a custom option for **Lain-lain**.
- Lets users describe each task's scope, add notes, and provide a justification when a target is not met.
- Supports two ways to enter both targets and achievements:
  - **Jadual (table):** add multiple items with a description, quantity, and unit.
  - **Teks (text):** enter a free-text description and a manual total quantity.
- Allows task cards and target/achievement items to be added or removed.

### Automatic calculations
- Calculates total target and achievement quantities for each task.
- Calculates the achievement percentage from achievement quantity divided by target quantity.
- Caps the percentage used for eligible-wage calculations at **100% per task**.
- Calculates each task's eligible wage from the capped percentage and the gross wage.
- Updates the wage summary as the form is filled in.

> **Calculation note:** The application uses the quantities entered by the user to calculate the percentage. Ensure the target and achievement quantities are comparable and use suitable units. If the target quantity is zero, the generated percentage is zero.

### Printable output
- Generates a monthly **Borang Penilaian Pencapaian Output**.
- Displays staff details, task information, targets, achievements, calculation details, justifications, notes, and signature sections.
- Provides a **Cetak / simpan PDF** button using the browser's print dialog.
- Formats the print layout for A4 landscape.

### Draft management
The `draft.php` module adds a sliding draft drawer and draft controls:
- Save a form as a draft.
- Load, rename, and delete saved drafts.
- Start a new form.
- Show draft count, save status, and last-updated information.
- Automatically suggest a draft name based on the assessment month, year, and staff name.

Drafts are stored in the browser's `localStorage`, not in the PHP user account or a server database. They are available only in the same browser and device, and may be lost if browser site data is cleared.

### Accounts and guest access
- Guests can generate up to **3 forms per PHP session** before being prompted to register.
- Users can register with a username and password and log in to generate forms without the guest trial limit.
- Passwords are hashed with PHP's `password_hash()` and checked with `password_verify()`.
- User credentials are stored in a local `users.json` file.

**Deployment warning:** The included account handling is a basic implementation. Before public or production deployment, strengthen authentication and storage security, add protections such as CSRF controls and login rate limiting, and ensure `users.json` cannot be downloaded or accessed directly over the web. Use HTTPS in deployment.

### Appearance and usability
- Three themes: **Sage**, **Blush**, and **Midnight**.
- Responsive layout for desktop and smaller screens.
- Interactive task cards, live totals, progress bars, and form feedback.
- Keyboard support for closing the login and registration dialogs with `Escape`.
- Reduced-motion styling for users who prefer less animation.

## Technologies

- **PHP** — form processing, session-based guest limits, account handling, output generation, and wage calculations.
- **HTML5** — form and printable document structure.
- **CSS** — responsive interface, themes, and print styles.
- **JavaScript** — dynamic task cards, live calculations, theme selection, and draft interface integration.
- **Browser `localStorage`** — local draft and theme preferences.
- **JSON file storage** — basic user account records in `users.json`.
- **Google Fonts** — DM Sans, Fraunces, Caladea, and Source Serif 4.

No database is configured in the supplied `index.php`; user accounts are stored in `users.json`, while drafts are stored in the browser.

## Project structure

At minimum, the application uses these files:

```text
mystep-output-generator/
├── index.php       # Main application, form processing, calculations, UI and output
├── draft.php       # Draft drawer, styling, JavaScript and localStorage draft handling
└── users.json      # Created automatically for basic user account storage
```

Keep `index.php` and `draft.php` in the same directory. The application attempts to create `users.json` in that directory when it does not already exist. The PHP process must have permission to write to the project directory for this to work.

## Requirements

- PHP with session support enabled.
- A web server capable of executing PHP, such as Apache through XAMPP.
- A modern browser with JavaScript and `localStorage` enabled.
- Internet access for the externally hosted Google Fonts; the core application may still render using fallback fonts if they are unavailable.

## Run locally with XAMPP

1. Install and start **Apache** in XAMPP.
2. Copy the project folder into the XAMPP `htdocs` directory. For example:

   ```text
   C:\xampp\htdocs\mystep-output-generator\
   ```

3. Confirm that `index.php` and `draft.php` are in the same folder.
4. Make sure the PHP process can create and write to `users.json`.
5. Open the application in your browser:

   ```text
   http://localhost/mystep-output-generator/
   ```

6. Use the form to enter staff details and tasks. Select **Jadual** or **Teks** for target and achievement entry, review the calculated summary, then choose **Jana borang penilaian**.
7. Use **Cetak / simpan PDF** to print the generated form or save it as a PDF.

Do not open `index.php` directly as a local file or use a static-only development server. PHP must run through a PHP-enabled web server.

## Draft module integration

`index.php` includes `draft.php` using:

```php
require_once __DIR__ . '/draft.php';
```

The main page calls the draft module's integration functions at these points:

```php
// Inside <head>
<?php draft_styles(); ?>

// Before the closing </body>
<?php draft_markup(); ?>
<?php draft_script(); ?>
```

The page also provides the form elements and JavaScript hooks used by the draft module, including `mainForm`, `tasksWrapper`, `draftName`, `saveStatus`, `menuBtn`, and `initDrafts()`.

If you change the form's input names or task structure, review the draft module's save/load logic too so saved drafts continue to map to the form correctly.

## Privacy and data storage

- **Drafts:** stored in browser `localStorage`; they are not synced between devices or browsers.
- **Theme preference:** stored in browser `localStorage` under `mystep-theme`.
- **Accounts:** stored in `users.json` on the server.
- **Generated form data:** processed by PHP when the form is submitted; the supplied code does not configure a database for generated forms.

The form includes personal information such as a name and identification number. Use only appropriate test data while developing, restrict access to the deployed application, and follow your organisation's data-handling requirements.

## Current scope and limitations

- The supplied implementation generates and prints the form; it does not include a database-backed archive of generated reports.
- Drafts are browser-specific and are not linked to registered accounts.
- The account system is a simple file-based example and should be hardened before production use.
- Calculation results depend on the accuracy and comparability of the target and achievement quantities entered.
- Browser print-to-PDF output can vary slightly depending on the browser and print settings.

## License

No license is specified in the supplied source files. Add a `LICENSE` file if you intend to publish this project for reuse, and choose terms that match your intended distribution.
