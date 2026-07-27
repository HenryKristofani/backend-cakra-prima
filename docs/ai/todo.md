# Project Guide: Cakra Prima — Digitalisasi Kas Kantor

## Konteks

Sistem ini menggantikan pembukuan manual Excel (`LPSE_23_JULI.xlsx`) milik
kantor Cakra Prima. Backend: Laravel 13 (PHP 8.3), database PostgreSQL
(Neon), frontend: Next.js. Admin kantor input data manual lewat form web
(bukan otomasi penuh), dan goal akhirnya bisa mencetak/export laporan
"ARUS KAS DARI OPRASIONAL" per bulan dalam format yang sama seperti Excel
aslinya.

**Prinsip desain yang dipegang di seluruh proyek ini:**
- Saldo/total yang bisa dihitung dari data (SUM transaksi, dsb) **tidak**
  disimpan manual — selalu dihitung on-the-fly lewat query/accessor, supaya
  tidak pernah nggak sinkron dengan data aslinya.
- Kalau ada selisih antara catatan sistem dan kondisi riil (kas fisik,
  rekening koran), penyesuaian dilakukan lewat **transaksi baru**
  ("Penyesuaian Saldo"), bukan lewat override angka manual — supaya ada
  jejak audit dan satu sumber kebenaran.
- Entitas yang sifatnya independen dari waktu (hutang yang belum lunas,
  dst) **tidak** diikat ke periode bulanan tertentu — dia ditampilkan di
  laporan bulan manapun selama masih outstanding.

---

## FASE 0 — Fondasi Skema Database

Tujuan: seluruh tabel yang dibutuhkan sudah ada dan berelasi dengan benar
sebelum masuk ke logic aplikasi.

### Tabel yang sudah ada (referensi, jangan dibuat ulang)
- `accounts` — master akun kas (Cash, Rek BNI, Rek BNI Wahyu)
- `transactions` (+ `account_id`, `project_id`, `user_id`)
- `projects` — master data project/perusahaan (LPSE, Jomboran, dst)
- `debts` — hutang piutang perorangan sederhana (mis. Mas Ryan)
- `installments` — cicilan berkala ke pihak ketiga (leasing, BPJS, dst)
- `cash_advances` — dana talangan / cash bon
- `potentials` — potensi/proyeksi piutang yang belum pasti
- `debt_groups` / `debt_items` / `debt_payments` — hutang majemuk
  (banyak item kecil → 1 total → dicicil bertahap). **Catatan penting:**
  tabel ini generik, dipakai baik untuk "Hutang Jomboran" maupun kasus
  reimbursement kecil seperti "Akomodasi Admin Jembatan" — bukan cuma
  untuk 1 kasus spesifik.
- `cash_flow_periods` — header laporan arus kas per bulan
- `operational_cash_flow_items` — item ringkasan per periode (Modal Awal,
  Balance Saldo, Saldo Mengendap, Jumlah Saldo), kolom `section`/`code`/
  `label`/`amount`
- `cash_flow_transactions` — detail transaksi harian per periode

### Tabel baru yang perlu ditambahkan di fase ini
- [x] `budget_needs` — daftar kebutuhan anggaran per bulan (berbeda dari
      `operational_cash_flow_items`: ini rencana/wishlist, bukan realisasi)
  - `id`
  - `period_id` (FK ke `cash_flow_periods`, cascadeOnDelete)
  - `description` (string)
  - `amount` (decimal 15,2)
  - `timestamps()`

### Checklist Fase 0
- [x] Migration `budget_needs` dibuat dan dijalankan
- [ ] Cross-check ERD vs data riil Excel (Juni & Juli) — pastikan semua
      section yang muncul di kedua bulan (termasuk yang cuma muncul di
      salah satu bulan, seperti sub-kolom Saldo Mengendap "P.Ganang, Mas
      Femo, dst" yang cuma ada di Juli) bisa ketampung oleh struktur
      `section`/`label`/`amount` yang fleksibel

---

## FASE 1 — Model & Relasi Eloquent

Tujuan: setiap tabel punya Eloquent model dengan `$fillable`, `$casts`,
dan relasi yang benar.

- [x] `Account` — `hasMany(Transaction)`, accessor `current_balance`
      (`initial_balance + SUM(income) - SUM(expense)`)
- [x] `Project` — `hasMany(Transaction)`, scope `active()`
- [x] `Transaction` — `belongsTo(Account)`, `belongsTo(Project)`,
      `belongsTo(User)`
- [x] `Debt` — `belongsTo(User)`, accessor `remaining_amount`
      (`amount - paid_amount`)
- [x] `Installment` — `belongsTo(User)`
- [x] `CashAdvance` — `belongsTo(User)`
- [x] `Potential` — `belongsTo(User)`
- [x] `DebtGroup` — `hasMany(DebtItem)`, `hasMany(DebtPayment)`, method
      `recalculate()` yang hitung ulang `total_amount` &
      `remaining_amount` setiap kali item/payment berubah
- [x] `DebtItem` — `belongsTo(DebtGroup)`
- [x] `DebtPayment` — `belongsTo(DebtGroup)`
- [x] `CashFlowPeriod` — `hasMany(OperationalCashFlowItem)`,
      `hasMany(CashFlowTransaction)`, `hasMany(BudgetNeed)`
- [x] `OperationalCashFlowItem` — `belongsTo(CashFlowPeriod)`
- [x] `CashFlowTransaction` — `belongsTo(CashFlowPeriod)`
- [x] `BudgetNeed` (baru) — `belongsTo(CashFlowPeriod)`

---

## FASE 2 — CRUD Controller Manual per Modul

Tujuan: admin kantor bisa input/edit/hapus data lewat form web untuk
semua modul. Semua controller pakai pola standar
(`index/create/store/show/edit/update/destroy`), validasi di method
`store`/`update`.

- [x] `AccountController`
- [x] `ProjectController`
- [x] `DebtController`
- [x] `InstallmentController`
- [x] `CashAdvanceController`
- [x] `PotentialController`
- [x] `DebtGroupController` + `DebtItemController` (nested) +
      `DebtPaymentController` (nested) — setiap perubahan item/payment
      panggil `$debtGroup->recalculate()`
- [x] `CashFlowPeriodController` + `OperationalCashFlowItemController`
      (nested) + `CashFlowTransactionController` (nested)
- [x] `BudgetNeedController` (baru, nested di bawah `CashFlowPeriod`)
- [x] `TransactionController` — form input transaksi kas harian, field
      `project` berupa **dropdown** dari `Project::active()`, bukan
      input teks bebas

### Routes
- [x] Tambahkan semua route resource + nested route ke `routes/web.php`
      (atau `routes/api.php` kalau backend murni API untuk Next.js)

---

## FASE 3 — Dashboard Kas & Keuangan

Tujuan: halaman ringkasan real-time yang menampilkan 4 angka utama.

- [ ] `DashboardController@index` mengembalikan:
  - `total_saldo_kas` = `Account::all()->sum(fn($a) => $a->current_balance)`
    — akumulasi **dari seluruh riwayat transaksi**, bukan net bulan
    berjalan
  - `pemasukan_bulan_ini` = `SUM(income)` transaksi bulan berjalan
  - `pengeluaran_bulan_ini` = `SUM(expense)` transaksi bulan berjalan
  - `total_saldo_cash` = sama seperti `total_saldo_kas`, tapi difilter
    `Account::where('type', 'cash')`
- [ ] Endpoint API (`GET /api/dashboard`) untuk dikonsumsi FE Next.js,
      ganti dummy data di FE dengan response asli ini

---

## FASE 4 — Laporan Kas per Project

Tujuan: menu laporan yang menampilkan transaksi & posisi neto khusus 1
project, bukan gabungan semua project.

- [ ] `ProjectReportController@index($project)`:
  - Daftar transaksi: `Transaction::where('project_id', $project->id)`,
    filter tanggal opsional
  - Total masuk/keluar: `SUM(income)`, `SUM(expense)` dari query yang sama
  - "Saldo project" = `total_income - total_expense` — **posisi neto**,
    bukan saldo rekening riil (karena kas bersifat fungible/gabungan
    lintas project)
- [ ] Tampilkan disclaimer di UI kalau perlu: saldo per project adalah
      posisi neto, bukan representasi uang fisik yang benar-benar
      terpisah per project

---

## FASE 5 — Export/Cetak Laporan ARUS KAS per Periode

Tujuan: reproduksi laporan "ARUS KAS DARI OPRASIONAL" (format HAL-01)
persis seperti Excel asli, bisa di-generate untuk periode bulan manapun.

- [ ] Install/pastikan `maatwebsite/excel` sudah aktif (sudah ada di
      `composer.json`)
- [ ] Buat Export class (`CashFlowPeriodExport`) yang menyusun ulang:
  - Header: nama periode, HAL-01
  - Section A: Modal Awal
  - Section B: Balance Saldo (REK/CASH/HT FM/HT JB)
  - Section C: Cashflow
  - Saldo Mengendap (semua item yang ada untuk periode tsb — jumlah item
    bisa beda tiap bulan, jangan hardcode)
  - Jumlah Saldo (Balance/Mengendap/Potongan/Efektif)
  - Detail transaksi harian (dari `cash_flow_transactions`)
  - Hutang yang masih outstanding (query `debt_groups` yang
    `remaining_amount > 0`, tanpa filter periode — tampil di laporan
    bulan manapun)
  - Kebutuhan anggaran bulan itu (`budget_needs` where `period_id`)
- [ ] Route: `GET /cash-flow-periods/{period}/export` → trigger download
      `.xlsx` (atau `.csv` kalau format sederhana cukup)
- [ ] Uji: generate laporan untuk 2 bulan yang sudah ada datanya (Juni,
      Juli), bandingkan manual dengan Excel asli — pastikan carry-forward
      Modal Awal = Balance Saldo bulan sebelumnya

---

## FASE 6 — Deployment

Tujuan: backend live di Render, frontend live di Vercel, database di
Neon, semua saling terhubung.

- [ ] Backend (`backend-cakra-prima`):
  - Root Directory di Render = `backend-cakra-prima`
  - Environment = Docker (PHP tidak punya native runtime di Render)
  - Dockerfile multi-stage: build asset Vite (Node) → install Composer →
    runtime PHP-FPM + nginx (lihat file Dockerfile yang sudah dibuat)
  - Env var: `DATABASE_URL` (Neon, dengan `sslmode=require`),
    `DB_CONNECTION=pgsql`, `APP_KEY`, `APP_ENV=production`,
    `APP_DEBUG=false`
  - `start.sh` menjalankan `php artisan migrate --force` setiap deploy
- [ ] Frontend (`frontend-cakra-prima`):
  - Root Directory di Vercel = `frontend-cakra-prima`
  - Env var `NEXT_PUBLIC_API_URL` diisi setelah backend live di Render,
    lalu redeploy
- [ ] Verifikasi end-to-end: buka FE production, cek dashboard menampilkan
      data asli (bukan dummy) dari backend

---

## FASE 7 (Goal Jangka Panjang — belum dikerjakan sekarang)

- [ ] Export Excel gabungan multi-modul (kas + hutang + angsuran + dst
      dalam 1 file, tiap modul jadi sheet terpisah) — baru dikerjakan
      setelah Fase 0-6 solid, karena butuh semua `project_id` sudah
      konsisten di semua tabel
- [ ] Evaluasi apakah `installments` perlu tabel jadwal cicilan terpisah
      (kalau ternyata "Angsuran Pick Up Carry" dkk itu cicilan berulang
      per bulan, bukan 1x kewajiban)
- [ ] Evaluasi kolom `direction` (hutang/piutang) di `debts` untuk
      menampung kasus seperti sheet BNI WAHYU (hutang LPSE ke pihak
      ketiga, arah kebalikan dari `debts` yang sudah ada)
- [ ] Modul `procurements` untuk sheet PENGADAAN BARANG (kalau memang mau
      jadi modul rutin, bukan dicatat sebagai transaksi biasa)

---

## Catatan Tambahan untuk Agent

- Semua controller & model yang disebut di Fase 1-2 kerangka awalnya
  sudah pernah dibuat dalam sesi sebelumnya — cek dulu apakah file-nya
  sudah ada di project sebelum generate ulang dari nol.
- Jangan buat kolom "saldo total" atau "saldo cash" yang bisa diisi
  manual admin — ini sudah didiskusikan dan diputuskan **tidak** dipakai,
  karena berisiko nggak sinkron dengan data transaksi asli.
- Kalau nemu data yang keliatan "aneh" pas migrasi dari Excel (kayak
  kasus Akomodasi Admin Jembatan yang di Excel masih nunjukin sisa hutang
  padahal sudah ada transaksi pelunasannya), itu bug di pencatatan manual
  lama — bukan sesuatu yang perlu ditiru di sistem baru.