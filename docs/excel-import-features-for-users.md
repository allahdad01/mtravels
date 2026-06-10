# 📥 M.Travels Excel Import Module — Features

Bulk-import your data from Excel spreadsheets — tickets, visas, hotel bookings, Umrah packages, and more — all at once with automatic validation and error reporting.

---

## 📋 What You Can Import

| Data Type | What Gets Created |
|-----------|------------------|
| **Ticket Bookings** | Full ticket records with PNR, passenger, supplier, pricing, payment |
| **Ticket Refunds** | Refund records linked to original PNR |
| **Ticket Date Changes** | Date change records linked to original PNR |
| **Ticket Weights** | Weight sale records linked to original PNR |
| **Ticket Reservations** | Reservation records with all booking details |
| **Visa Applications** | Visa records with applicant, destination, pricing |
| **Hotel Bookings** | Hotel records with check-in/out, room type, pricing |
| **Families** | Family group records for Umrah bookings |
| **Umrah Bookings** | Umrah package records with members and services |

---

## 📤 How It Works

1. **Download the Template** — Click "Download Template" to get a pre-formatted Excel file with instructions and one sheet per data type.
2. **Fill in Your Data** — Add your records starting from row 2 (row 1 is the header). Each column has a clear label.
3. **Upload** — Drag and drop your file or click to browse. Only `.xlsx` and `.xls` files are accepted (up to 50MB).
4. **Confirm** — Review the data types detected and click "Start Import."
5. **Review Results** — See how many records were imported successfully and download any error details for rows that failed.

---

## 🧠 Smart Processing

- **Auto-Linking** — The system recognizes supplier names, client names, and account names from your spreadsheet and links them automatically. If a supplier or client doesn't exist yet, it's created for you.
- **Flexible Dates** — Enter dates as Excel date values, text (YYYY-MM-DD), or any common format.
- **Skip Rows** — Rows starting with "NOTES" or "- " are automatically skipped — use them for comments.
- **Error Tolerance** — If a row has an error, the system skips it and continues with the rest. You'll get a full error report at the end.

---

## 📊 Import Results

After import, you'll see:
- Total records processed
- Records imported successfully
- Errors (if any) with the row number and description
- A visual progress bar during processing

---

## 🔐 Access Control

| Role | Can Import? |
|------|-------------|
| **Admin** | Yes |
| **Finance** | Yes |
| **Sales** | Yes |
| **Umrah Staff** | Yes |
| **Staff** | No |

---

## 📱 Language Support

Available in all 3 languages: English, Dari (فارسی), and Pashto (پښتو).

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Import Page | 1 |
| Backend Handler Class | 1 (1,035 lines) |
| Importable Data Types | 9 |
| Template Generator | 1 |
| Max File Size | 50MB |
| Supported Formats | `.xlsx`, `.xls` |

---

*Save hours of manual data entry — import hundreds of records in seconds.*
