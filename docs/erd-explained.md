Ini daftar lengkap 14 tabel yang sudah dibuat, dipetakan ke asal sheet/bagian di Excel-mu:

| Tabel (DB) | Terjemahan nama | Asal sheet/bagian di Excel |
|---|---|---|
| `accounts` | Akun/rekening kas | Sheet JAN, FEB, REKENING BNI, BNI WAHYU — daftar Cash, Rek BNI, Rek BNI Wahyu |
| `transactions` | Transaksi kas | Sheet JAN, FEB, REKENING BNI — baris IN/OUT harian |
| `projects` | Project/perusahaan | Kolom "keterangan" yang menyebut nama proyek (LPSE, Jomboran, Kreasi Muda, PMO, dst) — sebelumnya cuma teks bebas, sekarang jadi master data |
| `debts` | Hutang piutang perorangan | Sheet MAS RYAN |
| `installments` | Angsuran/cicilan | Sheet ANGSURAN (Pick Up Carry, Tanah Samping Kantor, BPJS) |
| `cash_advances` | Dana talangan | Sheet talangan, + kolom CASH BON |
| `potentials` | Potensi/proyeksi piutang | Sheet POTENSI, POTENSI 1 |
| `debt_groups` | Grup hutang majemuk | Blok "HUTANG JOMBORAN JAN-APRIL" (dan pola serupa: "Akomodasi Admin Jembatan") |
| `debt_items` | Item pembentuk hutang | Baris NO 10-38 di blok HUTANG JOMBORAN |
| `debt_payments` | Pembayaran/cicilan hutang | Baris "Dibayar", "Cicil Bayar", "Bayar dari Uang Brankas PMO" di blok HUTANG JOMBORAN |
| `cash_flow_periods` | Periode laporan arus kas | Header sheet ARUS KAS_JAN s/d ARUS KAS_JULI ("PERIODE 01 JULI - 31 JULI 2026", HAL-01) |
| `operational_cash_flow_items` | Item ringkasan arus kas operasional | Blok "ARUS KAS DARI OPRASIONAL" — Modal Awal (A), Balance Saldo (B), Cashflow (C), Saldo Mengendap, Jumlah Saldo |
| `cash_flow_transactions` | Detail transaksi arus kas | Kolom kanan sheet ARUS KAS_* — Tanggal/Keterangan/CASH-REK/OUT/IN |
| `budget_needs` *(baru, belum di-migrate)* | Kebutuhan anggaran | Blok "LIST KEBUTUHAN ANGGARAN" (mis. TAGIHAN KONKURITO, KELISTRIKAN D'PALM) |

**Belum ada tabelnya** (masih di tahap diskusi, belum diputuskan):
- Sheet **BNI WAHYU** — bagian hutang LPSE ke Pak Wahyu (arah kebalikan dari `debts`)
- Sheet **PENGADAAN BARANG** — belum jelas mau jadi modul rutin atau cukup jadi transaksi biasa

Mau saya update juga `cakra-prima-project-guide.md` biar tabel ini (dengan mapping sheet asalnya) masuk sebagai referensi di dalam file guide-nya?