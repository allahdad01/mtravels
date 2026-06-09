# 📄 M.Travels Maktob (Letter Management) Module — Features

Create, send, archive, and track all your official correspondence — in English, Dari, or Pashto — with automatic numbering, PDF generation, file attachments, and a complete audit trail.

---

## ✉️ Create & Manage Letters

- **Create a Letter** — Write official correspondence with subject, content, recipient company, date, and sender. Choose the language (English, Dari, or Pashto). The letter number is generated automatically.
- **Edit a Letter** — Update any letter details or replace attached files.
- **View Details** — See all letter information and download attached files in a single modal.
- **Delete a Letter** — Remove a letter and its files permanently. Every deletion is logged.

---

## 🔢 Automatic Numbering

Every letter gets a unique number automatically in this format: `{tenant}-{branch}-{YYYYMMDD}-{NNN}`.

For example: `2-2-20260111-001`. The sequence resets daily and auto-increments.

---

## 📋 Letter Status Workflow

Each letter goes through three stages:

| Status | Meaning |
|--------|---------|
| **Draft** | Working draft — not yet finalized |
| **Sent** | Finalized and sent to the recipient |
| **Archived** | Stored for record-keeping |

Only draft letters can be sent or archived. All status changes are logged with who did it and when.

---

## 📄 PDF Download & Print

Every letter can be viewed as a professional A4-formatted document with:
- Company letterhead with gradient header
- Logo, name, address, phone, and email
- Reference number and date
- Recipient name, subject, and full letter body
- Signature section

For Dari and Pashto letters, the document uses right-to-left (RTL) formatting with appropriate fonts.

---

## 📎 File Attachments

Attach up to two files to each letter:
1. **PDF Letter File** — The official letter as a PDF
2. **Supporting Document** — Any related document (receipt, contract, photo, etc.)

Supported formats: JPG, PNG, PDF, DOC, DOCX, TXT, XLS, XLSX (up to 10MB each).

---

## 📊 Dashboard Stats

At a glance, see:
- Total letters created
- Letters sent
- Drafts waiting to be finalized
- Archived letters
- Letters created this month

---

## 📜 Full Audit Trail

Every action on every letter is recorded:
- When it was created, by whom
- What changed on each edit (before/after snapshots)
- When it was sent, archived, or deleted
- The IP address of the person who made the change

---

## 🔐 Access Control

| Role | What They Can Do |
|------|-----------------|
| **Admin** | Full control — create, edit, delete, send, archive, download |
| **Finance** | Full control — create, edit, delete, send, archive, download |
| **Other Roles** | No access |

---

## 🌐 Multi-Language Support

| Language | RTL Support |
|----------|-------------|
| English | No |
| Dari (فارسی) | Yes |
| Pashto (پښتو) | Yes |

The letter form has a language selector, and PDF output adjusts fonts and text direction automatically.

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Management Page | 1 |
| API Endpoints | 6 |
| Modal Templates | 3 |
| JavaScript Files | 1 |
| Database Tables | 2 (`maktobs` + `maktob_logs`) |
| Letter Statuses | 3 (Draft, Sent, Archived) |
| Supported Languages | 3 |
| File Attachments Per Letter | 2 (PDF + document) |
| Auto-Numbering Format | Tenant-Branch-Date-Sequence |

---

*Never lose track of your official correspondence again — create, send, archive, and audit every letter from one place.*
