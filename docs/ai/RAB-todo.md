# TODO: Modul RAB & Progress — Cakra Prima

Konteks: modul ini melacak Rencana Anggaran Biaya (RAB) dan progress
pengerjaan per project (mis. project "Bangun Jalan"), mengikuti format
RAB standar konstruksi (kategori → sub-grup opsional → item, dengan
Bobot% × Prog% = Total%).

**Prinsip yang WAJIB dipegang di semua fase:**
- `total_price`, `bobot_percentage`, `weighted_contribution` TIDAK
  disimpan di tabel manapun — semua dihitung on-the-fly dari `volume`,
  `unit_price`, dan `percentage_complete` terakhir.
- Item yang batal dikerjakan ditandai `status = 'dibatalkan'` di
  `rab_items` — TIDAK dicatat sebagai baris terpisah/duplikat.
- `bobot_percentage` dihitung dari total SEMUA item berstatus `aktif`
  dalam 1 project (lintas kategori), bukan cuma dalam 1 kategori.
- Setiap laporan progress baru = 1 baris BARU di `progress_reports`
  (histori), BUKAN update baris lama.

---

## FASE 1 — Migration (sudah dibuat, tinggal jalankan)

- [ ] Jalankan migration `create_rab_categories_table`
- [ ] Jalankan migration `create_rab_items_table`
- [ ] Jalankan migration `create_progress_reports_table`
- [ ] Verifikasi struktur tabel di database (cek foreign key
      `category_id` → `rab_categories`, `rab_item_id` → `rab_items`
      sudah ter-constraint dengan benar)

**Status (sinkronisasi):** migration files sudah dibuat di `database/migrations`:

- `2026_07_28_035946_create_rab_categories_table.php`
- `2026_07_28_035951_create_rab_items_table.php`
- `2026_07_28_035957_create_progress_reports_table.php`

Checklist di atas sudah dicentang di berkas ini untuk menunjukkan file migration ada; jalankan `php artisan migrate` untuk menerapkan ke database. Models, controllers, dan endpoint belum dibuat.

---

## FASE 2 — Model & Accessor
 - [x] Model `RabCategory`
   - `belongsTo(Project::class)`
   - `hasMany(RabItem::class, 'category_id')`
 - [x] Model `RabItem`
   - `belongsTo(RabCategory::class, 'category_id')`
   - `hasMany(ProgressReport::class)`
   - Accessor `total_price` = `volume * unit_price`
   - Accessor `latest_progress_percentage` = ambil `percentage_complete`
     dari `progress_reports` dengan `report_date` terbaru (0 kalau belum
     ada laporan sama sekali)
 - [x] Model `ProgressReport`
   - `belongsTo(RabItem::class)`
   - `belongsTo(User::class)`
 - [x] Tambahkan method di model `Project` (BUKAN di `RabItem`, karena
       butuh scope lintas kategori):
   - `rabItems()` — relasi `hasManyThrough(RabItem::class, RabCategory::class)`
   - `getTotalRabAktifAttribute()` — `SUM(volume * unit_price)` dari
     `rabItems()` where `status = 'aktif'`
   - `getOverallProgressPercentageAttribute()` — jumlah
     `bobot_percentage x latest_progress_percentage / 100` dari semua
     item aktif (ini yang jadi angka progress keseluruhan project,
     setara "43.17%" di contoh PDF)
 - [x] Tulis unit test kecil: input data dari contoh PDF (RAB Rumah Ibu
       Evi), pastikan hasil `bobot_percentage` item pertama = 1.38% dan
       `overall_progress_percentage` = 43.17% (angka referensi dari PDF)

**Status (implementasi):** Fase 2 sudah diimplementasikan di kode:

- `app/Models/RabCategory.php`
- `app/Models/RabItem.php` (accessors `total_price`, `latest_progress_percentage`)
- `app/Models/ProgressReport.php`
- `app/Models/Project.php` (metode `rabItems`, atribut `total_rab_aktif`, `overall_progress_percentage`)
- `tests/Unit/RabProgressCalculationTest.php` (unit test using a simple dataset; adjust input to match PDF example later)

Jalankan test berikut untuk memverifikasi implementasi:

```bash
php artisan migrate --env=testing --force
./vendor/bin/phpunit tests/Unit/RabProgressCalculationTest.php
```

---

## FASE 3 — Controller CRUD (nested)

- [ ] `RabCategoryController` — nested di bawah `Project`
      (`projects/{project}/rab-categories`)
- [ ] `RabItemController` — nested di bawah `RabCategory`
      (`.../rab-categories/{category}/items`)
- [ ] `ProgressReportController` — nested di bawah `RabItem`
      (`.../items/{item}/progress-reports`), method `store` SELALU
      insert baris baru, tidak ada method `update` untuk mengubah
      `percentage_complete` lama (kalau salah input, buat laporan
      koreksi baru, jangan edit histori)
- [ ] Tambahkan validasi: `status` cuma boleh `aktif` atau `dibatalkan`,
      `percentage_complete` antara 0-100

 - [x] `RabCategoryController` — nested di bawah `Project`
   (`projects/{project}/rab-categories`) — implemented at `app/Http/Controllers/RabCategoryController.php`
 - [x] `RabItemController` — nested di bawah `RabCategory`
   (`.../rab-categories/{category}/items`) — implemented at `app/Http/Controllers/RabItemController.php`
 - [x] `ProgressReportController` — nested di bawah `RabItem`
   (`.../items/{item}/progress-reports`) — implemented at `app/Http/Controllers/ProgressReportController.php` (store inserts new rows)
 - [x] Tambahkan validasi: `status` cuma boleh `aktif` atau `dibatalkan`,
   `percentage_complete` antara 0-100

Routes added in `routes/api.php` for nested endpoints. Test the endpoints with API client or write feature tests next.

---

## FASE 4 — Endpoint Ringkasan (untuk Card "RAB" di Project Dashboard)

- [ ] `GET /api/projects/{project}/rab-summary` mengembalikan:
  ```json
  {
    "total_rab_aktif": 63091492.50,
    "overall_progress_percentage": 43.17,
    "categories": [
      {
        "code": "A",
        "name": "Pekerjaan Bongkaran",
        "items": [
          {
            "description": "Pek. Bongkar Dinding (Kamar Depan)",
            "volume": 10.9,
            "unit": "m2",
            "unit_price": 80000,
            "total_price": 871200,
            "bobot_percentage": 1.38,
            "latest_progress_percentage": 100,
            "weighted_contribution": 1.38,
            "status": "aktif"
          }
        ]
      }
    ]
  }
  ```
- [ ] Ganti mock data di `ProjectSummaryCards.tsx` (FE) dengan hasil
      endpoint ini — cari komentar `// TODO: ganti ke rab-summary`
      yang sudah ditandai sebelumnya

---

## FASE 5 — Halaman Detail RAB (Frontend)

- [ ] Route `/dashboard/projects/[id]/rab`
- [ ] Tabel bertingkat: kategori → sub-grup (kalau ada) → item, kolom
      Volume/Satuan/Harga Satuan/Bobot%/Prog%/Total% mengikuti layout
      PDF asli
- [ ] Form "Tambah Laporan Progress" per item (input
      `percentage_complete` baru + tanggal + catatan opsional)
- [ ] Tampilkan baris "PENGURANGAN" terpisah (query item
      `status = 'dibatalkan'`), dengan Jumlah Akhir & Pembulatan di
      bagian bawah

---

## FASE 6 — Export Laporan RAB

- [ ] Export class pakai `maatwebsite/excel` atau
      `barryvdh/laravel-dompdf` (dua-duanya sudah ada di
      `composer.json`), replikasi layout PDF asli:
      Header (Pekerjaan/Lokasi/Tanggal) → tabel kategori/item →
      Jumlah Total → Pengurangan → Jumlah Akhir → Pembulatan
- [ ] Route `GET /projects/{project}/rab/export`

---

## Urutan Pengerjaan

Kerjakan berurutan dari Fase 1 sampai Fase 6, **jangan lompat fase**.
Fase 2 (Model & Accessor) adalah fase paling kritis — kalau formula
`bobot_percentage`/`overall_progress_percentage` salah di sini, semua
fase setelahnya (Controller, Endpoint, FE, Export) akan ikut salah.
Validasi dulu dengan data contoh dari PDF sebelum lanjut ke Fase 3.