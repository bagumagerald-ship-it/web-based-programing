# UMU Voting (Student Guild Elections)

## What this app does
- Admin sets up positions and candidates.
- Students self-register with:
  - University email (e.g. `baguma.gerald@stud.umu.ac.ug`)
  - Registration number format `2023-B072-31712`
  - Password
  - **Photo upload** (required)
- Admin approves student accounts.
- Approved students cast **one vote per position**.

## Tech
- PHP + MySQL (PDO)
- Vanilla JS/CSS

---

## 1) Configure database
Edit `config.php`:
- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

---

## 2) Import database
Run:

```bash
mysql -u root -p < database.sql
```

---

## 3) Create admin account password (first time only)
1. Open `setup_admin.php` in your browser.
2. After it runs, **delete `setup_admin.php`**.



---

## 4) Create upload folder (required for student photos)
This project stores student photos in:

- `uploads/user-photos/`

Ensure the folder exists and is writable by the web server.

---

## Photo requirements
- Allowed types: **JPG, PNG, WEBP**
- Max size: **5MB**

---

## How student self-registration works
On `register.php`:
- Fields are validated:
  - Email must match `*@stud.umu.ac.ug`
  - Reg number must match `YYYY-L000-00000` pattern like `2023-B072-31712`
  - Photo upload is required
- New student accounts are created with `is_approved = 0`.

---

## Ballot/photos
- The navigation bar and the ballot page show the **logged-in student photo** when available.
- Candidates keep the current behavior (initials-based avatar). Candidate photos are not added in this version.

---

## Notes / troubleshooting
### Missing photo on ballot
- Check that `uploads/user-photos/` exists and is writable.
- If images do not display, ensure `photo_path` in `users` points to a valid relative path (e.g. `uploads/user-photos/xxx.jpg`).


