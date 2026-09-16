# Spec: Inventory & Warehouse Module — Cakra Prima

## Konteks Proyek
Ini adalah modul baru untuk sistem Cakra Prima yang sudah berjalan:
- Backend: Laravel 12.x, monorepo `backend-cakra-prima`
- Frontend: Next.js, monorepo `frontend-cakra-prima`
- Database: PostgreSQL (Neon.tech)
- Sistem sudah punya modul: Kas & Keuangan (`transactions` table), Projects, RAB/RAP per project
- Modul baru ini HARUS terintegrasi dengan modul Kas yang sudah ada (lihat Fase 4)

## Tujuan
Membangun sistem inventory/warehouse bergaya Odoo Inventory: setiap pergerakan barang (masuk, transfer, pemakaian) tercatat sebagai ledger/histori yang immutable, dengan stok saat ini dihitung dari kalkulasi histori tersebut. Ujungnya, pemakaian barang di sebuah project bisa langsung diposting sebagai transaksi pengeluaran di modul Kas project tersebut.

## Keputusan Desain yang Sudah Difinalkan
1. Setiap pergerakan tercatat sebagai dokumen (`stock_transfers`) yang bisa berisi banyak item sekaligus (mirip Odoo Delivery/Receipt), bukan satu baris per item per transaksi.
2. Semua pergerakan **langsung final** saat disimpan — TIDAK ada state draft → approval. Begitu dokumen dibuat, stok langsung terpotong/tertambah.
3. Ada 2 tipe warehouse: `main` (gudang utama) dan `project` (gudang project, terhubung ke `project_id`). Gudang project diperlakukan sama seperti gudang biasa dalam hal balance & pergerakan.
4. Harga (`unit_price`) WAJIB dicatat di setiap baris pergerakan — untuk kebutuhan pembukuan/keuangan.
5. Tidak perlu valuasi FIFO atau weighted-average untuk sekarang — asumsikan satu item punya satu harga yang konsisten (foundation-first, bisa dikembangkan nanti).
6. Saat input stok masuk, ada pilihan sumber: `purchase` (beli baru) atau `warehouse` (dari stok gudang lain — kasus ini sebenarnya masuk kategori transfer, bukan "in").
7. Pemakaian barang di gudang project (`usage`) dicatat per baris di tabel `stock_usages`, dan masing-masing baris usage bisa diposting SATU PER SATU ke modul Kas sebagai transaksi expense terpisah (bukan digabung jadi satu transaksi per dokumen).
8. Guard: satu baris usage tidak boleh diposting ke Kas dua kali.

## Skema Database (ERD)

### `warehouses`
| kolom | tipe | keterangan |
|---|---|---|
| id | uuid PK | |
| name | string | |
| type | enum('main', 'project') | |
| project_id | uuid FK nullable | wajib diisi kalau type='project', referensi ke tabel `projects` yang sudah ada |
| location | string nullable | |
| is_active | boolean | default true |

### `items`
| kolom | tipe | keterangan |
|---|---|---|
| id | uuid PK | |
| name | string | contoh: "Semen" |
| sku | string nullable | |
| unit | string | contoh: "zak", "kg", "m3" |
| category | string nullable | |
| is_active | boolean | default true |

### `stock_transfers` (dokumen/header)
| kolom | tipe | keterangan |
|---|---|---|
| id | uuid PK | |
| reference_number | string unique | auto-generate, contoh: TRF-2026-0001 |
| type | enum('in', 'transfer', 'usage') | |
| source_type | enum('purchase', 'warehouse') nullable | relevan kalau type='in' |
| source_warehouse_id | uuid FK nullable | null kalau type='in' & source_type='purchase' |
| destination_warehouse_id | uuid FK nullable | null kalau type='usage' (barang habis terpakai) |
| notes | text nullable | |
| created_by | uuid FK users | |
| created_at | timestamp | |

### `stock_transfer_lines` (detail item per dokumen)
| kolom | tipe | keterangan |
|---|---|---|
| id | uuid PK | |
| stock_transfer_id | uuid FK | |
| item_id | uuid FK | |
| quantity | decimal | |
| unit_price | decimal | |
| total_price | decimal | quantity * unit_price |

### `stock_balances` (cache, auto-update)
| kolom | tipe | keterangan |
|---|---|---|
| id | uuid PK | |
| warehouse_id | uuid FK | |
| item_id | uuid FK | |
| quantity_on_hand | decimal | |
| updated_at | timestamp | |
| unique constraint | (warehouse_id, item_id) | |

### `stock_usages`
| kolom | tipe | keterangan |
|---|---|---|
| id | uuid PK | |
| stock_transfer_line_id | uuid FK | |
| warehouse_id | uuid FK | gudang project asal pemakaian |
| item_id | uuid FK | |
| quantity | decimal | |
| usage_note | string nullable | |
| posted_to_kas | boolean | default false |
| kas_transaction_id | uuid FK nullable | referensi ke tabel `transactions` (modul Kas) |
| used_at | timestamp | |

## Flow / Business Logic

### Flow 1: Input Stok Masuk (Receipt)
1. User pilih gudang tujuan (biasanya gudang utama)
2. User pilih sumber: beli baru (`purchase`) atau dari stok existing
3. User input 1 atau lebih item + quantity + harga per item
4. Sistem membuat `stock_transfer` (type='in') + N baris `stock_transfer_lines`
5. Dalam 1 DB transaction: update `stock_balances` untuk tiap item di gudang tujuan (tambah quantity)

### Flow 2: Transfer Antar Gudang
1. User pilih gudang asal dan gudang tujuan
2. User input 1 atau lebih item + quantity yang mau ditransfer
3. Sistem validasi: `stock_balances.quantity_on_hand` di gudang asal harus cukup untuk SETIAP item, sebelum commit apapun
4. Sistem membuat `stock_transfer` (type='transfer') + N baris `stock_transfer_lines`
5. Dalam 1 DB transaction: kurangi balance di gudang asal, tambah balance di gudang tujuan, untuk tiap item

### Flow 3: Pemakaian Barang di Gudang Project
1. User (di gudang project) input item + quantity yang dipakai + catatan pemakaian
2. Sistem validasi stok cukup di gudang project tersebut
3. Sistem membuat `stock_transfer` (type='usage') + N baris `stock_transfer_lines`
4. Untuk tiap baris, sistem juga membuat 1 row `stock_usages` (posted_to_kas=false)
5. Balance gudang project dikurangi

### Flow 4: Posting ke Kas
1. User melihat daftar `stock_usages` yang `posted_to_kas=false` untuk sebuah project
2. User klik tombol "Jadikan Kas" pada satu baris usage
3. Sistem hitung total = quantity * unit_price (dari stock_transfer_line terkait)
4. Sistem insert ke tabel `transactions` (modul Kas existing): type=expense, project_id sesuai, description auto (misal "Pemakaian Semen 50 zak - [usage_note]")
5. Update `stock_usages.posted_to_kas=true` dan `kas_transaction_id` diisi
6. Guard: kalau `posted_to_kas` sudah true, tolak request (idempotent)

## Kebutuhan Query/Endpoint untuk FE

- `GET /inventory/stock-balances` — semua stok, agregat lintas gudang (group by item, sum quantity, hitung jumlah lokasi)
- `GET /inventory/stock-balances?item_id=X` — breakdown 1 item per lokasi/gudang (untuk modal/drawer breakdown)
- `GET /inventory/stock-balances?warehouse_id=X` — semua stok di 1 gudang/project tertentu
- `GET /inventory/stock-moves?item_id=&warehouse_id=` — histori pergerakan (timeline in/transfer/usage)
- `GET /projects/{id}/stock-report` — rekap stok diterima + terpakai + sisa + total nilai (harga) untuk 1 project
- `POST /inventory/warehouses`
- `POST /inventory/items`
- `POST /inventory/stock-transfers` (menangani ketiga tipe: in, transfer, usage — body membedakan lewat `type` + array `lines`)
- `POST /inventory/stock-usages/{id}/post-to-kas`

## Kebutuhan Halaman FE (Next.js)

- `/inventory` — index dengan tab: "Semua Stok" (agregat) dan "Per Gudang/Project" (dropdown pilih lokasi)
- Modal/drawer breakdown: klik total stok 1 item → tampil breakdown per gudang/lokasi
- `/inventory/[item_id]` — detail item: breakdown lokasi + histori pergerakan
- Form input stok masuk (pilihan sumber: beli/existing, multi-item dalam 1 form)
- Form transfer antar gudang (multi-item, validasi stok cukup di FE sebelum submit)
- Form pemakaian barang (di konteks gudang project)
- Tombol "Jadikan Kas" pada daftar usage yang belum diposting, per baris

## Best Practice yang Harus Diikuti (Laravel)
- Gunakan Service class (`StockTransferService`, `StockUsageService`) untuk business logic — jangan taruh logic di Controller
- Gunakan PHP 8.1+ native enum untuk `type`, `source_type` (bukan magic string)
- Bungkus semua operasi multi-tabel (create transfer + update balance) dalam `DB::transaction()`
- Gunakan Model Observer/Event Listener untuk auto-update `stock_balances` setiap `StockTransferLine` dibuat — controller cukup insert dokumen, listener yang handle balance
- Index database: `stock_transfer_lines(item_id)`, `stock_balances(warehouse_id, item_id)` unique composite, `stock_transfers(type, created_at)`
- Validasi stok cukup dilakukan di Service layer sebelum commit apapun, throw custom exception kalau gagal

## Urutan Pengerjaan (Fase)
1. Migration + Model + Enum untuk 6 tabel di atas
2. Service: `createReceipt()` (stok masuk)
3. Service: `createTransfer()` (transfer antar gudang, dengan validasi stok)
4. Service: `createUsage()` (pemakaian, auto-generate `stock_usages`)
5. Service: `postToKas()` (posting ke modul Kas existing, dengan guard anti-double-post)
6. Endpoint query/rekap untuk kebutuhan FE
7. FE: halaman inventory, modal breakdown, form-form terkait
8. Polish: reference number generator, role/permission, export laporan
