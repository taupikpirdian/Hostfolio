# HOSTFOLIO
## Product Requirements Document — v1.0

**Product:** Hostfolio  
**Description:** Infrastructure Inventory & Billing Management  
**Platform:** Internal Web Application  
**Architecture:** Single Organization, Modular Monolith  
**Technology:** Laravel, Livewire, Filament, MySQL  
**Deployment:** Self-hosted HomeServer  
**Date:** 7 November 2026  
**Status:** Draft siap implementasi

---

## 1. Product Overview

### 1.1 Background

Hostfolio merupakan aplikasi internal untuk mengelola infrastruktur server dan operasional layanan hosting dalam satu sistem.

Sebelumnya, pencatatan dilakukan menggunakan Google Sheets dengan informasi:

- Nama service
- Server/provider
- Jumlah CPU Core
- RAM
- Storage
- Tanggal mulai dan berakhir
- Nominal biaya
- Status layanan

Pencatatan ini memiliki keterbatasan, khususnya dalam membedakan physical server, virtual machine, aplikasi, dan transaksi keuangan.

### 1.2 Product Objectives

1. Menyediakan pusat pencatatan seluruh infrastruktur server.
2. Mengelola hubungan physical server, VM/container, dan deployed service.
3. Mencatat alokasi resource secara terstruktur.
4. Mengelola kontrak hosting dan biaya operasional.
5. Mencatat invoice dan pembayaran, termasuk pembayaran sebagian.
6. Melacak tanggal renewal layanan dan domain.
7. Menyediakan laporan inventory dan keuangan yang akurat.
8. Mempertahankan histori layanan dan transaksi.

### 1.3 Success Criteria

- Seluruh 13 service pada Sheet lama dapat dimigrasikan dan diverifikasi.
- Service aktif dan tidak aktif dapat dibedakan.
- Resource allocation tidak dihitung ganda.
- Nominal kontrak, invoice, dan pembayaran tercatat secara terpisah.
- Tagihan belum lunas dan jatuh tempo dapat diketahui.
- Pengeluaran operasional HomeLab dapat dicatat.
- Riwayat perubahan data penting tersedia.

## 2. Scope

### 2.1 In Scope

**Inventory Management**

- Physical Server Management
- Compute Instance Management
- Service Management
- Service Deployment
- Domain Registry
- Resource Allocation
- Inventory History
- Import/Export CSV

**Billing Management**

- Client & Vendor
- Billing Contract
- Contract Items
- Invoice & Vendor Bill
- Payment Management
- Expense Management
- Renewal Tracking
- Billing Dashboard
- Financial Reports

**Supporting Features**

- Authentication & Authorization
- Dashboard
- Audit Logs
- Master Data
- Application Settings

### 2.2 Out of Scope

- Monitoring CPU/RAM secara real-time
- Uptime monitoring dan heartbeat
- Alert downtime
- Integrasi otomatis Proxmox API
- Integrasi Docker API
- Remote restart/shutdown server
- Payment gateway
- Customer portal
- Multi-tenant SaaS
- Sistem akuntansi double-entry
- Penagihan otomatis melalui WhatsApp

## 3. User Roles

| Role | Akses |
|---|---|
| Owner/Admin | Seluruh fitur dan pengaturan |
| Operator | Inventory, service, resource, deployment |
| Finance | Kontrak, invoice, pembayaran, laporan keuangan |
| Viewer | Melihat informasi sesuai permission |

Pada implementasi awal, aplikasi dapat menggunakan satu akun Admin. Struktur authorization tetap disiapkan untuk penambahan pengguna.

## 4. Inventory Management

### 4.1 Physical Server

**ID: INV-001**

Mencatat mesin fisik yang dimiliki atau dikelola.

Data yang disimpan:

- Server Code dan Name
- Hostname
- Location
- Provider
- CPU Model
- Physical CPU Cores
- Total RAM
- Total Storage
- Operating System/Hypervisor
- Management IP
- Status
- Description/Notes

Status: `planned`, `active`, `inactive`, `retired`.

Setiap physical server dapat memiliki beberapa compute instances.

### 4.2 Compute Instance

**ID: INV-002**

Mencatat environment komputasi yang berjalan di atas physical server atau provider eksternal.

Supported instance types:

- Virtual Machine (VM)
- LXC Container
- Docker Container
- VPS
- Shared Hosting

Data yang disimpan:

- Instance Code
- Instance Name
- Instance Type
- Provider
- Physical Server (nullable)
- Parent Compute Instance (nullable)
- Hostname/IP Address
- Operating System
- External Reference ID
- Status
- Notes

Satu VM dapat menjalankan beberapa Docker container. VPS dari provider eksternal tidak wajib mempunyai physical server di inventory lokal.

Parent relationship tidak boleh membentuk circular dependency.

### 4.3 Resource Allocation

**ID: INV-003**

Mengelola alokasi resource compute instance.

Data:

- Compute Instance
- Allocated vCPU
- Allocated RAM (MB)
- Allocated Storage (GB)
- Effective From
- Effective To
- Changed By
- Notes

**Business Rules:**

1. Hanya satu resource allocation aktif per compute instance.
2. Setiap perubahan allocation menghasilkan histori.
3. Resource tidak wajib tersedia untuk semua jenis instance.
4. Resource yang belum diketahui disimpan sebagai `NULL`.
5. Physical capacity dan allocated resource ditampilkan terpisah.
6. Alokasi Docker di dalam VM tidak dijumlahkan ulang sebagai tambahan alokasi physical server.
7. CPU overcommit diperbolehkan dengan indikator peringatan.
8. Storage provisioned tidak dianggap sebagai disk usage aktual.

### 4.4 Service Management

**ID: INV-004**

Mencatat logical service atau aplikasi.

Service types:

- Website
- API
- Database
- Worker
- Internal Application
- Other

Data:

- Service Code
- Service Name
- Slug
- Service Type
- Description
- Owner/Client (optional)
- Status
- Started At
- Archived At
- Notes

Status:

`draft` → `running` → `stopped` → `archived`

Service internal seperti database dan log-center tetap dapat dicatat tanpa billing contract.

Service yang diarsipkan tidak boleh menghilangkan histori invoice dan pembayaran.

### 4.5 Service Deployment

**ID: INV-005**

Menghubungkan service dengan compute instance.

Fields:

- Service
- Compute Instance
- Environment (production/staging/development)
- Deployment Role
- Primary Deployment
- Deployed At
- Removed At
- Notes

**Business Rules:**

- Satu service dapat berada di beberapa compute instances.
- Satu compute instance dapat menjalankan beberapa services.
- Deployment aktif yang sama tidak boleh tercatat dua kali.
- Perubahan lokasi deployment harus mempertahankan histori penempatan sebelumnya.

### 4.6 Domain Management

**ID: INV-006**

Fields:

- Domain/FQDN
- Registrar
- Expiration Date
- Auto Renew
- Status/Notes
- Related Services

Satu domain dapat dihubungkan ke beberapa service jika diperlukan.

Expiration domain terpisah dari tanggal akhir kontrak hosting.

### 4.7 Inventory Search & Filter

**ID: INV-007**

Filtering berdasarkan:

- Physical Server
- Provider
- Compute Instance Type
- Service Type
- Service Status
- Client
- Domain
- Archived/Active

Mendukung search, sorting, pagination, dan ekspor CSV.

### 4.8 Inventory Import

**ID: INV-008**

Import melalui CSV dari Google Sheets.

Flow:

1. Upload CSV.
2. Mapping kolom.
3. Preview data.
4. Validasi nilai dan duplikasi.
5. Simpan ke staging import.
6. Review mapping server dan resource.
7. Konfirmasi import.
8. Simpan data final dan audit result.

`DELETED` pada Sheet diubah menjadi `archived`, bukan hard delete.

Core, RAM, dan Storage lama tidak otomatis dianggap alokasi VM yang valid. Tanggal serta Amount juga memerlukan verifikasi sebelum dimigrasikan sebagai kontrak.

## 5. Billing Management

### 5.1 Client & Vendor Management

**ID: BIL-001**

Party digunakan sebagai entitas pelanggan atau vendor.

Fields:

- Name
- Type: Client / Vendor / Both
- Email
- Phone
- Address
- Tax Information (optional)
- Notes
- Status

Satu party dapat memiliki beberapa kontrak.

### 5.2 Billing Contract

**ID: BIL-002**

Kontrak mengatur biaya dan pendapatan berulang maupun satu kali.

Contract Types:

- Income — layanan hosting yang ditagihkan kepada pelanggan.
- Expense — biaya yang harus dibayar kepada provider/vendor.

Fields:

- Contract Number
- Party
- Contract Direction
- Description
- Start Date
- End Date (optional)
- Billing Cycle
- Next Billing Date
- Status
- Notes

Supported billing cycles:

- Monthly
- Quarterly
- Yearly
- One-Time
- Custom

Status kontrak: `draft`, `active`, `paused`, `ended`, `cancelled`.

### 5.3 Contract Items

**ID: BIL-003**

Satu kontrak dapat berisi beberapa item.

Fields:

- Contract
- Description
- Quantity
- Unit Price
- Target Type
- Target Reference
- Effective From

Target dapat berupa:

- Physical Server
- Compute Instance
- Service
- Domain
- General Expense (tanpa target)

Contoh: kontrak hosting satu service sebesar Rp800.000/tahun.

### 5.4 Invoice Management

**ID: BIL-004**

Membuat dan mencatat tagihan pelanggan maupun bill vendor.

Fields:

- Invoice Number
- Invoice Type (Receivable / Payable)
- Party
- Billing Contract (optional)
- Issue Date
- Due Date
- Billing Period Start/End
- Invoice Items
- Subtotal
- Discount
- Tax
- Total Amount
- Lifecycle Status
- Notes

Invoice dapat dibuat dari kontrak atau dibuat ad-hoc.

Setiap invoice item menyimpan snapshot harga, quantity, discount, dan tax saat dokumen diterbitkan.

Kontrak yang mengalami perubahan harga tidak boleh mengubah invoice historis.

### 5.5 Payment Management

**ID: BIL-005**

Fields:

- Invoice
- Payment Date
- Amount
- Payment Method
- Reference Number
- Payment Proof (optional)
- Notes

Sistem mendukung pembayaran sebagian.

**Contoh:**

Invoice Rp800.000.

| Transaksi | Payment | Outstanding |
|---|---:|---:|
| Invoice issued | Rp0 | Rp800.000 |
| Payment 1 | Rp300.000 | Rp500.000 |
| Payment 2 | Rp500.000 | Rp0 |

Status pembayaran berubah dari `unpaid` menjadi `partial` lalu `paid`.

Payment tidak boleh melebihi outstanding. Koreksi payment yang sudah tercatat dilakukan melalui reversal/audit trail, bukan hard delete.

### 5.6 Expense Management

**ID: BIL-006**

Expense digunakan untuk biaya operasional infrastruktur, seperti:

- VPS Hosting
- Domain Registration
- Hardware
- Electricity
- Internet
- Backup Storage
- Other Operational Expense

Expense dapat berasal dari recurring expense contract atau vendor bill ad-hoc.

Biaya operasional dicatat sebagai payable. Pembayaran payable tercatat sebagai cash out.

### 5.7 Renewal Tracking

**ID: BIL-007**

Renewal list menampilkan:

- Service/Contract
- Client atau Vendor
- Renewal Date
- Estimated Amount
- Days Remaining
- Status

Filter:

- Expired
- 7 Days
- 14 Days
- 30 Days
- 60 Days

Sistem juga menampilkan domain yang mendekati expiration.

Renewal tidak otomatis menandakan invoice telah dibayar.

### 5.8 Billing Reports

**ID: BIL-008**

Laporan wajib:

- Total Invoiced Income
- Total Received Income
- Total Billed Expense
- Total Paid Expense
- Accounts Receivable
- Accounts Payable
- Net Cashflow
- Unpaid & Overdue Invoices
- Income by Service
- Expense by Provider
- Renewal Schedule

Laporan dapat difilter berdasarkan periode dan diekspor ke CSV.

## 6. Billing Business Rules

### 6.1 Perhitungan

`line_subtotal = quantity × unit_price`

`line_total = line_subtotal - discount + tax`

`invoice_total = SUM(line_total)`

`paid_total = SUM(valid payments)`

`outstanding = invoice_total - paid_total`

`cash_in = SUM(receivable payments berdasarkan paid_at)`

`cash_out = SUM(payable payments berdasarkan paid_at)`

`net_cashflow = cash_in - cash_out`

**Penting:** Nilai kontrak bukan pendapatan aktual. Invoice issued bukan cash received. Pengeluaran yang belum dibayar bukan cash out.

### 6.2 Billing Status

Lifecycle invoice:

- `draft`
- `issued`
- `void`

Payment status (derived):

- `unpaid`
- `partial`
- `paid`

Overdue adalah indikator terpisah ketika due date sudah lewat dan outstanding masih lebih dari nol.

### 6.3 Currency

Default currency: IDR.

Untuk pembayaran vendor dengan mata uang asing, v1.0 mencatat ekuivalen IDR menggunakan kurs manual beserta catatan sumber kurs.

Tidak ada konversi mata uang otomatis pada MVP.

## 7. Core User Flows

### 7.1 Menambahkan Service pada HomeLab

1. Buat physical server.
2. Buat VM/LXC.
3. Atur alokasi resource.
4. Buat service.
5. Hubungkan service dengan VM/LXC melalui deployment.
6. Tambahkan domain apabila diperlukan.
7. Hubungkan kontrak jika service bersifat komersial.

### 7.2 Menambahkan External Hosting

1. Buat provider eksternal.
2. Buat VPS atau Shared Hosting instance.
3. Tambahkan service.
4. Buat deployment.
5. Tambahkan expense contract kepada provider.
6. Tambahkan income contract jika layanan ditagihkan ke pelanggan.

### 7.3 Menerima Pembayaran Hosting

1. Buat kontrak income.
2. Generate invoice.
3. Issue invoice.
4. Catat payment.
5. Sistem menghitung remaining balance.
6. Invoice menjadi paid ketika outstanding nol.

### 7.4 Mencatat Biaya Server

1. Pilih vendor.
2. Buat expense contract atau vendor bill.
3. Masukkan biaya serta due date.
4. Catat payment setelah dibayarkan.
5. Cashflow dan outstanding payable diperbarui.

### 7.5 Memperpanjang Layanan

1. Buka renewal list.
2. Periksa kontrak yang mendekati jatuh tempo.
3. Buat invoice periode berikutnya bila diperlukan.
4. Catat pembayaran.
5. Perbarui jadwal renewal.
6. Simpan histori perubahan.

## 8. Application Pages

### Dashboard
- Inventory Summary
- Resource Allocation Summary
- Billing Summary
- Unpaid/Overdue Invoices
- Upcoming Renewals
- Recent Transactions

### Inventory
- Physical Servers
- Compute Instances
- Services
- Deployments
- Domains
- Import Inventory

### Billing
- Billing Overview
- Contracts
- Invoices
- Vendor Bills
- Payments
- Renewals

### Reports
- Inventory Report
- Resource Allocation Report
- Income Report
- Expense Report
- Cashflow
- AR/AP Aging

### Master Data
- Clients & Vendors
- Providers
- Categories

### Settings
- Users
- Roles & Permissions
- Audit Logs
- Application Settings

## 9. Database Design

### 9.1 Inventory Tables

| Table | Description |
|---|---|
| `providers` | Internal/external infrastructure providers |
| `physical_servers` | Physical hardware inventory |
| `compute_instances` | VM, LXC, Docker, VPS, shared hosting |
| `resource_allocation_history` | CPU/RAM/storage allocations and changes |
| `services` | Logical applications/services |
| `service_deployments` | Many-to-many service and instance mapping |
| `domains` | Domain inventory |
| `service_domain` | Domain to service relationship |

Core relationship:

`Physical Server → Compute Instance → Service Deployment ← Service`

Compute instances dapat mempunyai parent instance, contohnya Docker di dalam VM.

### 9.2 Billing Tables

| Table | Description |
|---|---|
| `parties` | Client/vendor master |
| `billing_contracts` | Recurring or one-time agreements |
| `contract_items` | Contract rates and billable items |
| `invoices` | Customer invoices and vendor bills |
| `invoice_items` | Snapshot of billed items |
| `payments` | Actual money movements |

Core relationship:

`Party → Billing Contract → Invoice → Payment`

Satu kontrak memiliki banyak items dan dapat menghasilkan banyak invoice.

### 9.3 Supporting Tables

- `users`
- `roles`
- `permissions`
- `audit_logs`
- `import_batches`
- `import_rows`

### 9.4 Data Integrity Requirements

- Unique service slug.
- Unique domain FQDN.
- Unique invoice number.
- Foreign key untuk relasi utama.
- Decimal untuk monetary values.
- Maksimal satu active allocation per instance.
- Pencegahan circular parent instance.
- Transaksi database untuk invoice issuance dan payment.
- Tidak melakukan hard delete pada financial records.
- Service archive tidak menghapus kontrak maupun invoice lama.

## 10. Dashboard Rules

Inventory dashboard menampilkan:

- Total physical servers
- Total compute instances
- Total running services
- Total archived services
- Total hardware capacity
- Total directly allocated VM/LXC resources

Resource allocation bukan metrik real-time.

Billing dashboard menampilkan:

- Contract value
- Invoice issued
- Income received
- Expense paid
- Outstanding receivables
- Outstanding payables
- Net cashflow
- Upcoming renewals

Setiap metrik harus mempunyai definisi perhitungan yang konsisten.

## 11. Data Migration

Data awal berasal dari Google Sheets dengan field:

`No, Service, Server, Core, RAM, Storage, Date Start, Date End, Amount, Status`

Mapping awal:

| Source | Destination |
|---|---|
| Service | `services.name` |
| Server | Provider / instance mapping |
| Core | Legacy resource estimate |
| RAM | Legacy resource estimate |
| Storage | Legacy resource estimate |
| Date Start | Legacy start date |
| Date End | Legacy end date |
| Amount | Legacy billing amount |
| Status | Service lifecycle |
| No | Import row reference |

**Import rules:**

- Nilai `-` dianggap tidak diketahui (`NULL`), bukan selalu nol.
- Amount Rp0 tidak menghasilkan pendapatan.
- Nilai Amount belum dianggap pembayaran yang telah diterima.
- Date End belum dianggap domain expiration.
- Service dengan status DELETED tetap dipertahankan sebagai Archived.
- Data perlu diverifikasi sebelum membuat billing contract.
- Total resource harus dihitung ulang setelah pemetaan ke VM sebenarnya.

## 12. Nonfunctional Requirements

### Security
- Login authentication
- Role-based access control
- Laravel Policy authorization
- CSRF protection
- Input validation
- Password hashing
- Rate limiting
- Audit logging
- Secure file upload

### Performance
- Server-side pagination
- Indexed database queries
- Eager loading untuk menghindari N+1
- Dashboard query yang efisien

### Deployment
- Docker Compose
- Nginx/reverse proxy
- MySQL persistent storage
- Laravel scheduler
- HTTPS/secure private access

### Backup
- Daily database backup
- Off-site backup
- Backup retention
- Tested restore procedure

### Data Standards
- Timezone Asia/Jakarta
- Monetary precision menggunakan decimal
- Format mata uang Rupiah
- Konsistensi date dan timestamp

## 13. Technical Architecture

Arsitektur menggunakan Laravel Modular Monolith.

Modules:

1. Identity & Authorization
2. Inventory
3. Billing
4. Reporting
5. Shared Infrastructure

Pemisahan tanggung jawab:

- Filament Resources/Pages: UI
- Livewire: Interactive Components
- Application Actions/Use Cases: Business workflows
- Domain Services: Domain rules
- Eloquent Models: Persistence
- Policies: Access control

Setiap operasi finansial kritis menggunakan database transaction.

## 14. Implementation Roadmap

| Phase | Features | Exit Criteria |
|---|---|---|
| Phase 1 — Foundation & Inventory | Auth, server, instance, service, deployment, allocations | Inventory end-to-end berjalan |
| Phase 2 — Billing | Party, contract, invoice, vendor bill, payment | Billing end-to-end berjalan |
| Phase 3 — Reporting & Migration | Import CSV, dashboard, reports, domains, renewal | Data lama dapat direkonsiliasi |
| Phase 4 — Hardening | Audit, permissions, testing, backup, deployment | Siap dipakai secara internal |

## 15. QA Acceptance Criteria

| Scenario | Expected Result |
|---|---|
| VM + Docker pada satu host | Tidak ada double count resource |
| VPS eksternal | Tidak wajib mempunyai physical parent |
| Satu service pada dua VM | Dua deployment valid |
| Dua service pada satu VM | Alokasi instance tetap dihitung sekali |
| Update alokasi VM | Histori tersimpan |
| Archive service | Financial history tetap ada |
| Import data DELETED | Menjadi Archived |
| Contract Rp800.000/year | Invoice sesuai tarif saat diterbitkan |
| Pembayaran sebagian | Outstanding berkurang |
| Pembayaran melebihi sisa tagihan | Ditolak |
| Invoice terlambat | Overdue tampil |
| Vendor expense dibayar | Cash out bertambah |
| Edit harga kontrak | Invoice lama tidak berubah |
| Operator mengedit invoice | Ditolak oleh backend |
| Data CSV duplikat | Ditandai sebelum import |
| Domain mendekati expiry | Muncul di renewal list |
| Cashflow report | Berdasarkan tanggal pembayaran |
| Void invoice belum dibayar | Tidak dihitung sebagai outstanding aktif |

## 16. Definition of Done

Hostfolio v1.0 dianggap selesai jika:

1. Inventory physical server, instance, service, dan deployment berjalan.
2. Resource allocation konsisten dan tidak dihitung ganda.
3. Billing dapat mencatat income dan expense.
4. Kontrak dapat menghasilkan invoice.
5. Payment parsial dan outstanding berjalan.
6. Renewal dapat ditelusuri.
7. Data Google Sheets dapat dimigrasikan melalui proses review.
8. Laporan inventory dan billing tersedia.
9. Role dan permission diterapkan di backend.
10. Audit log tersedia untuk tindakan penting.
11. Backup dan restore database berhasil diuji.
12. Aplikasi berhasil di-deploy pada HomeServer.

## 17. Asumsi dan Keputusan Produk

- Aplikasi hanya digunakan oleh satu organisasi.
- Bisa mengelola banyak client dan vendor.
- Service dapat memiliki billing maupun tidak.
- Service tidak harus mempunyai domain.
- Satu service dapat berada pada beberapa VM.
- Billing tidak mengubah status server/service.
- Invoice tidak otomatis dianggap telah dibayar.
- Data legacy memerlukan verifikasi sebelum menjadi kontrak.
- Semua monitoring real-time dan downtime alert berada di luar scope v1.0.

**Product Outcome:** Hostfolio menjadi pusat pengelolaan inventory infrastruktur dan billing hosting yang terstruktur, dapat diaudit, dan siap dikembangkan secara bertahap tanpa mencampurkan urusan monitoring ke dalam MVP.