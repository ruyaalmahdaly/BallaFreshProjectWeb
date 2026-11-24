-- ==========================================================================
-- BALLA' FRESH DATABASE MIGRATION SCRIPT FOR SUPABASE (POSTGRESQL)
-- Project URL: https://althnabbfrwbnmeqkrrd.supabase.co
-- Generated: 2026-11-22 14:37:58
-- Instruksi: Buka Dashboard Supabase -> SQL Editor -> Tempel isi file ini -> Klik Run
-- ==========================================================================

BEGIN;

-- --------------------------------------------------------------------------
-- 1. PostgreSQL Compatibility Helpers (MySQL Syntax Emulation)
-- --------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.if(condition boolean, true_val anyelement, false_val anyelement)
RETURNS anyelement AS $$
BEGIN
    IF condition THEN
        RETURN true_val;
    ELSE
        RETURN false_val;
    END IF;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION public.ifnull(anyelement, anyelement)
RETURNS anyelement AS $$
    SELECT COALESCE($1, $2);
$$ LANGUAGE sql IMMUTABLE;

-- --------------------------------------------------------------------------
-- 2. Tabel & Struktur Data
-- --------------------------------------------------------------------------

-- Table: admins
CREATE TABLE IF NOT EXISTS public."admins" (
    "id" SERIAL PRIMARY KEY,
    "name" VARCHAR(100) NOT NULL,
    "email" VARCHAR(150) NOT NULL,
    "password" VARCHAR(255) NOT NULL,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "is_approved" SMALLINT DEFAULT 0 NOT NULL,
    "is_owner" SMALLINT DEFAULT 0 NOT NULL,
    "role" VARCHAR(50) DEFAULT 'admin',
    "phone" VARCHAR(50),
    "last_active_at" TIMESTAMPTZ
);

-- Data for: admins (1 rows)
INSERT INTO public."admins" ("id", "name", "email", "password", "created_at", "is_approved", "is_owner") VALUES ('1', 'Ru''ya Shadiqa A', 'almahdalyruya@gmail.com', '$2y$10$N6nUGe87QHt.oouZ/qS/y.igm6eJtslxBAycn.nTSJSTAjOIFc4lu', '2026-10-25 14:06:11', '1', '1') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."admins"', 'id'), COALESCE((SELECT MAX(id) FROM public."admins"), 1));

-- Table: agency_services
CREATE TABLE IF NOT EXISTS public."agency_services" (
    "id" SERIAL PRIMARY KEY,
    "title" VARCHAR(190) NOT NULL,
    "description" TEXT,
    "price" NUMERIC(15,2) DEFAULT 0.00 NOT NULL,
    "image" VARCHAR(255),
    "is_active" SMALLINT DEFAULT 1 NOT NULL,
    "updated_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- (Table agency_services has 0 rows)

-- Table: agency_site_content
CREATE TABLE IF NOT EXISTS public."agency_site_content" (
    "id" INT NOT NULL,
    "hero_title" VARCHAR(255) DEFAULT '' NOT NULL,
    "hero_description" TEXT,
    "contact_email" VARCHAR(190),
    "contact_phone" VARCHAR(50),
    "hero_image" VARCHAR(255),
    "updated_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "hero_tag" VARCHAR(100) DEFAULT 'Digital Growth Partner',
    "about_tag" VARCHAR(100) DEFAULT 'Tentang Kami',
    "about_title" VARCHAR(255) DEFAULT 'Mendorong Potensi Hidroponik Rumahan Menuju Pasar Digital',
    "about_story" TEXT,
    "about_problem" TEXT,
    "about_solution" TEXT,
    "about_image" VARCHAR(255),
    "cta_title" VARCHAR(255) DEFAULT 'Mulai Langkah Digitalisasi Usaha Hidroponik Anda Hari Ini',
    "cta_description" TEXT,
    "cta_badge" VARCHAR(100) DEFAULT 'Akses Eksklusif Mitra',
    "contact_address" TEXT,
    "privacy_policy" TEXT,
    "terms_conditions" TEXT,
    "policy_updated_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id")
);

-- Data for: agency_site_content (1 rows)
INSERT INTO public."agency_site_content" ("id", "hero_title", "hero_description", "contact_email", "contact_phone", "hero_image", "updated_at", "hero_tag", "about_tag", "about_title", "about_story", "about_problem", "about_solution", "about_image", "cta_title", "cta_description", "cta_badge", "contact_address", "privacy_policy", "terms_conditions", "policy_updated_at") VALUES ('1', 'Lebih banyak peluang, #DariRumahAja.', 'Balla’ Fresh membantu petani dan UMKM hidroponik rumahan di Makassar mengembangkan pemasaran dan penjualan melalui solusi digital yang sesuai dengan kebutuhan usahanya.', 'ballafresh01@gmail.com', '081213725107', NULL, '2026-11-13 06:58:07', 'Digital Growth Partner Ini', 'Tentang Balla’ Fresh', 'Mendorong Kemandirian Petani & UMKM Hidroponik Rumahan', 'Balla’ Fresh berawal dari kepedulian terhadap para penggiat hidroponik rumahan di Makassar yang memiliki hasil panen berkualitas prima, namun seringkali kesulitan menjangkau pasar yang lebih luas. Berdiri sebagai agensi pertumbuhan digital, Balla’ Fresh mendedikasikan diri untuk mendampingi petani hidroponik lokal agar dapat bersaing di era digital dengan kemasan profesional, kanal pemasaran terpadu, dan identitas brand yang kuat.', 'Banyak petani hidroponik rumahan memproduksi sayuran segar berkualitas tinggi seperti selada, pakcoy, dan kale, namun terbentur kendala: ketiadaan branding kemasan yang memikat konsumen modern, proses pemesanan manual yang tidak terstruktur, serta keterbatasan wawasan strategi promosi digital. Akibatnya, penjualan hanya berputar di lingkaran tetangga terdekat dengan margin keuntungan yang rendah.', 'Balla’ Fresh hadir memberikan solusi terintegrasi dan modular: pembuatan identitas visual (logo & kemasan), setup katalog produk dan pemesanan digital berbasis web, hingga strategi promosi media sosial yang tepat sasaran untuk pasar rumah tangga dan pelaku kuliner sehat di perkotaan.', 'uploads/agency/about-5a3752151c32.png', 'Buka Seluruh Potensi Usaha Hidroponik Anda Sekarang', 'Masuk atau daftarkan akun kebun Anda untuk membuka katalog harga resmi, melakukan konsultasi pemetaan kebutuhan, serta memesan layanan digitalisasi Balla’ Fresh secara langsung.', 'Akses Eksklusif Mitra Balla’ Fresh', 'Makassar, Sulawesi Selatan, Indonesia', '### Kebijakan Privasi Balla’ Fresh

Terakhir diperbarui: 10 November 2026

Selamat datang di Balla’ Fresh. Kami berkomitmen untuk melindungi data pribadi dan privasi mitra usaha serta petani hidroponik yang menggunakan platform kami.

1. **Data yang Kami Kumpulkan**
Kami mengumpulkan informasi yang Anda berikan saat mendaftar, melengkapi profil, atau melakukan pemesanan layanan, termasuk nama lengkap, tanggal lahir, alamat email, nomor telepon, alamat fisik, nama usaha, bidang usaha, peran/jabatan dalam usaha, dan tautan media sosial/website usaha.

2. **Penggunaan Informasi**
Informasi yang dikumpulkan digunakan untuk:
- Menyediakan dan menyesuaikan layanan digitalisasi usaha hidroponik sesuai kebutuhan mitra.
- Memfasilitasi proses pemesanan, verifikasi identitas, dan konfirmasi pembayaran.
- Menghubungkan mitra dengan tim konsultan melalui ruang konsultasi resmi.
- Memberikan pembaruan status pengerjaan proyek dan informasi layanan terkait.

3. **Perlindungan dan Keamanan Data**
Kami menerapkan langkah-langkah keamanan teknis dan organisasi untuk melindungi data Anda dari akses, pengubahan, atau pengungkapan tanpa izin. Kata sandi akun Anda disimpan dalam bentuk terenkripsi satu arah (hash).

4. **Hak Pengguna**
Anda berhak untuk mengakses, memperbarui, atau memperbaiki informasi profil akun dan data usaha Anda kapan saja melalui halaman Profil Mitra.

5. **Kontak Kami**
Jika Anda memiliki pertanyaan mengenai Kebijakan Privasi ini, silakan hubungi tim kami melalui email **ballafresh01@gmail.com** atau WhatsApp resmi kami.', '### Syarat & Ketentuan Layanan Balla’ Fresh

Terakhir diperbarui: 10 November 2026

Harap membaca Syarat & Ketentuan ini dengan saksama sebelum memesan layanan atau menggunakan platform Balla’ Fresh.

1. **Ketentuan Umum Kemitraan**
Platform Balla’ Fresh diperuntukkan bagi penggiat, petani, dan pelaku UMKM hidroponik lokal yang membutuhkan pendampingan pemasaran dan pengembangan aset digital (branding, e-commerce, dan promosi media sosial).

2. **Akun dan Tanggung Jawab Pengguna**
Pengguna bertanggung jawab untuk menjaga kerahasiaan kredensial akun mereka. Setiap aktivitas pemesanan yang dilakukan melalui akun Anda dianggap sah dilakukan oleh Anda atau perwakilan resmi usaha Anda.

3. **Pemesanan dan Pembayaran Layanan**
- Pemesanan layanan yang diajukan akan ditinjau dan disetujui (ACC) oleh tim administrator sebelum proses pengerjaan dimulai.
- Pembayaran dapat dilakukan melalui transfer bank atau kanal resmi yang disediakan pada halaman checkout.
- Estimasi durasi pengerjaan dihitung sejak detail brief dan konfirmasi pembayaran disetujui.

4. **Konsultasi & Ruang Obrolan (Chat Room)**
Ruang konsultasi chat dapat digunakan setelah permintaan konsultasi disetujui (ACC) oleh admin. Segala bentuk komunikasi dalam ruang chat harus mematuhi etika profesional dan berfokus pada pengembangan usaha mitra.

5. **Hak Kekayaan Intelektual**
Setelah seluruh kewajiban pembayaran diselesaikan, hasil desain, aset digital, dan kode platform yang dipesan diserahkan sepenuhnya kepada mitra untuk operasional usaha mereka.

6. **Perubahan Ketentuan**
Balla’ Fresh berhak untuk memperbarui ketentuan ini dari waktu ke waktu. Pembaruan akan diinformasikan melalui dashboard dan halaman kebijakan resmi.', '2026-11-13 06:58:07') ON CONFLICT DO NOTHING;

-- Table: agency_team
CREATE TABLE IF NOT EXISTS public."agency_team" (
    "id" SERIAL PRIMARY KEY,
    "role_type" VARCHAR(50) DEFAULT 'member' NOT NULL,
    "name" VARCHAR(150) NOT NULL,
    "position" VARCHAR(150) NOT NULL,
    "photo" VARCHAR(255),
    "display_order" INT DEFAULT 1 NOT NULL,
    "updated_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Data for: agency_team (4 rows)
INSERT INTO public."agency_team" ("id", "role_type", "name", "position", "photo", "display_order", "updated_at") VALUES ('1', 'owner', 'Ru''ya Shadiqa A', 'Owner & Agency Lead', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80', '1', '2026-11-10 10:45:35') ON CONFLICT DO NOTHING;
INSERT INTO public."agency_team" ("id", "role_type", "name", "position", "photo", "display_order", "updated_at") VALUES ('2', 'member', 'Andi Fadhlur Rahman', 'Lead Brand & Visual Designer', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80', '2', '2026-11-10 10:45:35') ON CONFLICT DO NOTHING;
INSERT INTO public."agency_team" ("id", "role_type", "name", "position", "photo", "display_order", "updated_at") VALUES ('3', 'member', 'Nur Sernovita Bunga', 'Lead Marketing & Social Growth', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80', '3', '2026-11-10 10:45:35') ON CONFLICT DO NOTHING;
INSERT INTO public."agency_team" ("id", "role_type", "name", "position", "photo", "display_order", "updated_at") VALUES ('4', 'member', 'Muh. Ilham Pratama', 'Lead Web & Operations Specialist', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80', '4', '2026-11-10 10:45:35') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."agency_team"', 'id'), COALESCE((SELECT MAX(id) FROM public."agency_team"), 1));

-- Table: categories
CREATE TABLE IF NOT EXISTS public."categories" (
    "id" SERIAL PRIMARY KEY,
    "name" VARCHAR(100) NOT NULL,
    "description" TEXT,
    "image" VARCHAR(255),
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Data for: categories (3 rows)
INSERT INTO public."categories" ("id", "name", "description", "image", "created_at") VALUES ('1', 'Starter Branding', 'Layanan dasar untuk membangun identitas visual bisnis.', NULL, '2026-10-16 10:28:01') ON CONFLICT DO NOTHING;
INSERT INTO public."categories" ("id", "name", "description", "image", "created_at") VALUES ('2', 'E-Commerce Grow', 'Layanan untuk membantu pengembangan katalog dan penjualan digital.', NULL, '2026-10-16 10:28:01') ON CONFLICT DO NOTHING;
INSERT INTO public."categories" ("id", "name", "description", "image", "created_at") VALUES ('3', 'Full Marketing Scale', 'Layanan pemasaran digital untuk mendukung pertumbuhan bisnis.', NULL, '2026-10-16 10:28:01') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."categories"', 'id'), COALESCE((SELECT MAX(id) FROM public."categories"), 1));

-- Table: services
CREATE TABLE IF NOT EXISTS public."services" (
    "id" SERIAL PRIMARY KEY,
    "category_id" INT NOT NULL,
    "name" VARCHAR(150) NOT NULL,
    "description" TEXT,
    "features" TEXT,
    "price" NUMERIC(12,2) DEFAULT 0.00 NOT NULL,
    "image" VARCHAR(255),
    "estimated_days" INT DEFAULT 3 NOT NULL,
    "is_available" SMALLINT DEFAULT 1 NOT NULL,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updated_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Data for: services (3 rows)
INSERT INTO public."services" ("id", "category_id", "name", "description", "features", "price", "image", "estimated_days", "is_available", "created_at", "updated_at") VALUES ('1', '1', 'Paket Starter Branding', 'Layanan dasar untuk membantu membangun identitas visual usaha hidroponik.', 'Konsep visual|Logo utama & variasi|File siap pakai', '150000.00', 'https://images.unsplash.com/photo-1634942536790-2534a3b6a3a5?auto=format&fit=crop&w=900&q=80', '3', '1', '2026-10-16 10:33:36', '2026-11-07 15:58:07') ON CONFLICT DO NOTHING;
INSERT INTO public."services" ("id", "category_id", "name", "description", "features", "price", "image", "estimated_days", "is_available", "created_at", "updated_at") VALUES ('2', '2', 'Paket E-Commerce Grow', 'Layanan untuk membantu menata katalog produk dan mengembangkan penjualan digital.', 'Penataan katalog produk|Informasi dan deskripsi produk|Persiapan tampilan penjualan digital', '300000.00', 'https://images.unsplash.com/photo-1558655146-d09347e92766?auto=format&fit=crop&w=900&q=80', '5', '1', '2026-10-16 10:33:36', '2026-11-07 15:58:07') ON CONFLICT DO NOTHING;
INSERT INTO public."services" ("id", "category_id", "name", "description", "features", "price", "image", "estimated_days", "is_available", "created_at", "updated_at") VALUES ('3', '3', 'Paket Full Marketing Scale', 'Layanan pemasaran digital untuk mendukung pertumbuhan bisnis hidroponik.', 'Perencanaan konten pemasaran|Strategi media sosial|Rekomendasi promosi digital', '500000.00', 'https://images.unsplash.com/photo-1553877522-43269d4ea984?auto=format&fit=crop&w=900&q=80', '7', '1', '2026-10-16 10:33:36', '2026-11-07 15:58:07') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."services"', 'id'), COALESCE((SELECT MAX(id) FROM public."services"), 1));

-- Table: service_reviews
CREATE TABLE IF NOT EXISTS public."service_reviews" (
    "id" SERIAL PRIMARY KEY,
    "service_id" INT NOT NULL,
    "user_name" VARCHAR(100) NOT NULL,
    "business_name" VARCHAR(150),
    "rating" SMALLINT DEFAULT 5 NOT NULL,
    "review_text" TEXT NOT NULL,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "is_approved" SMALLINT DEFAULT 1
);

-- Data for: service_reviews (8 rows)
INSERT INTO public."service_reviews" ("id", "service_id", "user_name", "business_name", "rating", "review_text", "created_at", "is_approved") VALUES ('1', '1', 'Hendra Wijaya', 'Makassar Hydro Farm', '5', 'Desain logo dan palet warnanya sangat profesional dan merepresentasikan kesegaran sayuran hidroponik kami. Tim Balla Fresh komunikatif dan revisi dikerjakan cepat.', '2026-11-03 15:57:55', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."service_reviews" ("id", "service_id", "user_name", "business_name", "rating", "review_text", "created_at", "is_approved") VALUES ('2', '1', 'Siti Nurhaliza', 'Celebes Greens Antang', '5', 'Sangat puas dengan konsep visual dan file siap pakainya. Sekarang kemasan selada dan pakcoy kami terlihat premium di etalase swalayan lokal.', '2026-10-26 15:57:55', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."service_reviews" ("id", "service_id", "user_name", "business_name", "rating", "review_text", "created_at", "is_approved") VALUES ('3', '1', 'Fajar Ramadhan', 'FreshLeaf Daya', '4', 'Hasil pengerjaannya rapi, file vektor lengkap untuk stiker dan spanduk. Rekomendasi buat petani hidroponik pemula.', '2026-10-20 15:57:55', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."service_reviews" ("id", "service_id", "user_name", "business_name", "rating", "review_text", "created_at", "is_approved") VALUES ('4', '2', 'Andi Hermanto', 'Balla Sayur Toddopuli', '5', 'Penataan katalog produk digitalnya sangat membantu pelanggan kami memesan sayur mingguan. Sekarang orderan masuk lebih tertata rapi.', '2026-11-05 15:57:55', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."service_reviews" ("id", "service_id", "user_name", "business_name", "rating", "review_text", "created_at", "is_approved") VALUES ('5', '2', 'Dewi Lestari', 'Urban Hydro Panakkukang', '5', 'Deskripsi produk dan struktur transaksinya jelas. Pelanggan tidak lagi bolak-balik tanya harga dan ketersediaan stok.', '2026-10-29 15:57:55', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."service_reviews" ("id", "service_id", "user_name", "business_name", "rating", "review_text", "created_at", "is_approved") VALUES ('6', '2', 'Rahmat Syam', 'GreenHouse Maros', '5', 'Layanan ini sangat cocok untuk petani hidroponik yang ingin naik kelas ke penjualan digital. Terima kasih Balla Fresh!', '2026-10-23 15:57:55', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."service_reviews" ("id", "service_id", "user_name", "business_name", "rating", "review_text", "created_at", "is_approved") VALUES ('7', '3', 'Syamsuddin Malik', 'Sultan Hydroponic Gowa', '5', 'Strategi promosi dan konten media sosial yang disiapkan sangat efektif. Penjualan paket sayuran kami meningkat lebih dari 40% dalam sebulan.', '2026-11-02 15:57:55', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."service_reviews" ("id", "service_id", "user_name", "business_name", "rating", "review_text", "created_at", "is_approved") VALUES ('8', '3', 'Aulia Putri', 'Fresh Veggie Makassar', '5', 'Rekomendasi promosi digitalnya tepat sasaran untuk ibu-ibu dan pelaku resto sehat di Makassar. Sangat worth it.', '2026-10-24 15:57:55', '1') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."service_reviews"', 'id'), COALESCE((SELECT MAX(id) FROM public."service_reviews"), 1));

-- Table: articles
CREATE TABLE IF NOT EXISTS public."articles" (
    "id" SERIAL PRIMARY KEY,
    "title" VARCHAR(255) NOT NULL,
    "slug" VARCHAR(255) NOT NULL,
    "category" VARCHAR(100) DEFAULT 'Branding & Kemasan' NOT NULL,
    "excerpt" TEXT NOT NULL,
    "content" TEXT NOT NULL,
    "image" VARCHAR(255),
    "author_name" VARCHAR(100) DEFAULT 'Tim Balla’ Fresh' NOT NULL,
    "author_role" VARCHAR(100) DEFAULT 'Growth Specialist' NOT NULL,
    "author_avatar" VARCHAR(255),
    "read_time" VARCHAR(50) DEFAULT '5 min read' NOT NULL,
    "is_featured" SMALLINT DEFAULT 0 NOT NULL,
    "is_published" SMALLINT DEFAULT 1 NOT NULL,
    "views_count" INT DEFAULT 0 NOT NULL,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updated_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Data for: articles (4 rows)
INSERT INTO public."articles" ("id", "title", "slug", "category", "excerpt", "content", "image", "author_name", "author_role", "author_avatar", "read_time", "is_featured", "is_published", "views_count", "created_at", "updated_at") VALUES ('1', 'Strategi Branding & Desain Kemasan Sayur Hidroponik Tembus Swalayan Modern', 'strategi-branding-kemasan-hidroponik-swalayan', 'Branding & Kemasan', 'Pelajari bagaimana kemasan standing pouch berlabel anti-air dan identitas visual yang profesional mampu meningkatkan nilai jual selada dan kale hidroponik hingga 2x lipat.', '<h2>Mengapa Branding Sangat Krusial untuk Sayur Hidroponik?</h2>
<p>Sebagian besar petani hidroponik rumahan sering kali hanya berfokus pada teknik budidaya: menjaga kestabilan PPM nutrisi, mengontrol pH air, dan memastikan pencahayaan optimal. Namun, ketika panen melimpah, tantangan terbesar justru muncul pada tahap <strong>pemasaran dan persepsi nilai</strong> di mata konsumen.</p>

<p>Sayuran hidroponik memiliki keunggulan higienis, bebas pestisida kimia berbahaya, dan tekstur yang lebih renyah. Tanpa kemasan dan branding yang tepat, produk premium ini hanya akan dianggap sama seperti sayur curah biasa di pasar tradisional.</p>

<h3>1. Gunakan Label Kemasan Berdaya Tahan Air (Waterproof)</h3>
<p>Sayuran segar disimpan di dalam rak pendingin (chiller) dengan kelembapan tinggi. Menggunakan stiker kertas biasa akan membuat label cepat basah, mengelupas, dan tintanya luntur. Balla’ Fresh merekomendasikan bahan <em>Vinyl Matte</em> atau <em>BOPP Transparan</em> dengan laminasi dingin yang higienis.</p>

<ul>
<li><strong>Labeling Informasi Wajib:</strong> Tanggal panen segar, nama varietas (contoh: <em>Romaine Crisp</em>), berat bersih, dan petunjuk penyimpanan.</li>
<li><strong>Sertifikasi & Nilai Tambah:</strong> Cantumkan ikon "100% Bebas Pestisida", "Bebas Residu", dan QR Code ke profil kebun Anda.</li>
<li><strong>Desain Minimalis:</strong> Konsumen swalayan lebih menyukai kemasan semi-transparan sehingga kualitas kesegaran daun sayur terlihat jelas dari luar.</li>
</ul>

<h3>2. Membangun Identitas Merek yang Memiliki Cerita (Storytelling)</h3>
<p>Konsumen urban di kota besar seperti Makassar bersedia membayar lebih mahal ketika mereka mengetahui siapa petaninya dan bagaimana proses sayuran tersebut ditumbuhkan. Mengangkat narasi <em>"Dipanen pagi ini langsung dari kebun rumahan di kota Anda"</em> menciptakan ikatan emosional dan jaminan kesegaran maksimal.</p>

<blockquote>"Kemasan yang baik bukan sekadar pembungkus, melainkan wiraniaga diam (silent salesman) yang meyakinkan pembeli dalam 3 detik pertama di rak belanja."</blockquote>

<h3>3. Standar Barcode & Legalitas Usaha</h3>
<p>Untuk menembus supermarket dan rantai ritel modern, Anda perlu melengkapi kemasan dengan barcode EAN-13 resmi, izin edar PIRT/BAPOM (jika ada olahan lanjutan), serta nomor registrasi kontak B2B yang jelas.</p>

<p>Balla’ Fresh siap mendampingi UMKM hidroponik mulai dari perancangan identitas visual, mockup 3D kemasan, hingga persiapan berkas kemitraan ritel modern.</p>', 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=1000&q=80', 'Anilva Fitriani', 'Owner & Creative Director', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80', '6 min read', '1', '1', '354', '2026-11-10 12:44:38', '2026-11-22 12:57:28') ON CONFLICT DO NOTHING;
INSERT INTO public."articles" ("id", "title", "slug", "category", "excerpt", "content", "image", "author_name", "author_role", "author_avatar", "read_time", "is_featured", "is_published", "views_count", "created_at", "updated_at") VALUES ('2', 'Panduan Efisiensi Nutrisi AB Mix untuk Kebun Hidroponik Skala Rumah Tangga', 'panduan-efisiensi-nutrisi-ab-mix-rumahan', 'Budidaya & Teknis', 'Tips menghemat biaya operasional nutrisi cair tanpa mengurangi bobot timbangan dan kerenyahan daun sayur hidroponik Anda.', '<h2>Mengontrol Biaya Operasional Tanpa Mengorbankan Kualitas</h2>
<p>Nutrisi AB Mix adalah salah satu pos pengeluaran rutin terbesar bagi pelaku usaha hidroponik. Kesalahan dalam pencampuran atau pemborosan akibat drainase yang buruk dapat menggerus margin keuntungan bisnis.</p>

<h3>Kunci Stabilitas TDS dan Suhu Tandon</h3>
<p>Suhu tandon nutrisi di daerah tropis seperti Sulawesi Selatan dapat melonjak hingga di atas 30°C pada siang hari. Suhu yang terlalu panas menurunkan kadar oksigen terlarut (DO) dan memicu busuk akar (pythium).</p>

<ul>
<li>Gunakan insulasi styrofoam atau kubur tandon sebagian ke dalam tanah untuk meredam panas.</li>
<li>Cek PPM secara teratur di pagi hari sebelum penguapan air meningkat.</li>
<li>Rutin kalibrasi pH meter dan TDS meter minimal sebulan sekali.</li>
</ul>

<h3>Korelasi Nutrisi dengan Rasa Sayur</h3>
<p>Sayuran yang kekurangan nutrisi mikro seperti Kalsium dan Magnesium akan mudah mengalami <em>tip burn</em> (ujung daun terbakar). Sementara kelebihan Nitrogen dapat membuat rasa sayuran terasa pahit. Menguasai takaran presisi adalah fondasi reputasi produk Anda di hadapan pelanggan langganan.</p>', 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?auto=format&fit=crop&w=1000&q=80', 'Ru''ya Almahdaly', 'Lead Web & Operations', 'https://media.licdn.com/dms/image/v2/D5603AQHBC4FA2mka_Q/profile-displayphoto-shrink_100_100/B56ZZCnUKtHoAU-/0/1744874325791?e=1796256000&v=beta&t=j2DOpG0G-I5_r6lUCm0xyB0W64PGj7JXqKEOsfyGOSk', '5 min read', '0', '1', '191', '2026-11-10 12:44:38', '2026-11-16 01:56:55') ON CONFLICT DO NOTHING;
INSERT INTO public."articles" ("id", "title", "slug", "category", "excerpt", "content", "image", "author_name", "author_role", "author_avatar", "read_time", "is_featured", "is_published", "views_count", "created_at", "updated_at") VALUES ('3', 'Strategi Pemasaran Sayur Segar Langganan Melalui WhatsApp Business & Instagram', 'strategi-pemasaran-sayur-whatsapp-instagram', 'Pemasaran & Penjualan', 'Cara praktis mengubah pembeli satu kali menjadi pelanggan langganan mingguan (subscription model) dengan katalog digital interaktif.', '<h2>Mengapa Model Langganan (Subscription) Paling Menguntungkan?</h2>
<p>Banyak petani hidroponik bingung saat sayurannya siap panen serentak dalam 30 hari. Menjual secara mendadak sering kali memaksa petani menurunkan harga karena sayur memiliki masa simpan yang terbatas.</p>

<p>Solusi terbaik adalah mengunci pesanan sejak bibit disemai menggunakan <strong>Sistem Langganan Paket Mingguan (Veggie Box)</strong>.</p>

<h3>1. Memanfaatkan Katalog WhatsApp Business</h3>
<p>Buat paket bundel yang menarik untuk keluarga, misalnya: <em>Paket Sehat Salad (Selada Romaine + Butterhead + Tomat Cherry)</em> seharga Rp35.000/minggu. Cantumkan foto panen asli dengan lighting alami yang cerah.</p>

<h3>2. Edukasi Konten di Instagram Reels</h3>
<p>Konsumen urban sangat menyukai video proses panen yang bersih, proses pencucian higienis, dan resep salad lezat 5 menit. Jangan hanya mengunggah foto sayuran mentah; tampilkan produk akhir di atas piring makan keluarga.</p>

<h3>3. Rutinitas Pengiriman di Hari yang Tetap</h3>
<p>Tentukan hari kirim tetap, misalnya setiap Selasa dan Jumat pagi. Hal ini mempermudah rute kurir pengantaran dan menekan biaya logistik harian Anda.</p>', 'https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&w=1000&q=80', 'Sernovita Bunga', 'Lead Marketing & Social Growth', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&q=80', '7 min read', '0', '1', '277', '2026-11-10 12:44:39', '2026-11-20 13:42:09') ON CONFLICT DO NOTHING;
INSERT INTO public."articles" ("id", "title", "slug", "category", "excerpt", "content", "image", "author_name", "author_role", "author_avatar", "read_time", "is_featured", "is_published", "views_count", "created_at", "updated_at") VALUES ('4', 'Menghitung BEP dan Analisis Margin Keuntungan Usaha Sayur Hidroponik Rumahan', 'analisis-margin-dan-bep-hidroponik-rumahan', 'Manajemen Bisnis', 'Simulasi perhitungan modal instalasi pipa NFT 400 lubang tanam dan estimasi titik impas balik modal dalam 6-8 siklus panen.', '<h2>Mendirikan Kebun Hidroponik dengan Pendekatan Finansial yang Matang</h2>
<p>Banyak yang memulai hidroponik sekadar hobi, lalu terkejut saat skala usaha diperbesar namun kas keuangan tidak kunjung bertambah. Kuncinya adalah mencatat seluruh <strong>Biaya Investasi (CapEx)</strong> dan <strong>Biaya Operasional (OpEx)</strong> per lubang tanam.</p>

<h3>Komponen Biaya Tetap vs Biaya Variabel</h3>
<ul>
<li><strong>CapEx:</strong> Rangka baja ringan, pipa paralon food grade, pompa celup, tandon air, dan atap UV greenhouse.</li>
<li><strong>OpEx:</strong> Benih unggul, rockwool media semai, nutrisi AB Mix pekat, listrik pompa 24 jam, serta plastik packaging.</li>
</ul>

<h3>Menetapkan Harga Jual Berdasarkan Nilai Tambah</h3>
<p>Jangan menjual sayuran hidroponik dengan harga komoditas pasar becek. Ketika Anda telah mengemasnya dengan stiker merek yang rapi, menjual langsung ke konsumen akhir (B2C) dapat menghasilkan margin kotor antara 40% hingga 60%.</p>', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1000&q=80', 'Habibi Ibnu Haris', 'Lead Visual & Brand Designer', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80', '6 min read', '0', '1', '210', '2026-11-10 12:44:39', '2026-11-16 01:54:12') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."articles"', 'id'), COALESCE((SELECT MAX(id) FROM public."articles"), 1));

-- Table: customers
CREATE TABLE IF NOT EXISTS public."customers" (
    "id" SERIAL PRIMARY KEY,
    "name" VARCHAR(100) NOT NULL,
    "email" VARCHAR(150) NOT NULL,
    "phone" VARCHAR(20) NOT NULL,
    "address" TEXT,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Data for: customers (4 rows)
INSERT INTO public."customers" ("id", "name", "email", "phone", "address", "created_at") VALUES ('1', 'Ru''ya A', 'almahdalyruya@gmail.com', '0411865677', 'Jl Cisadane 1 no 09, Moncongloe, MAKASSAR, 90562', '2026-10-16 12:47:50') ON CONFLICT DO NOTHING;
INSERT INTO public."customers" ("id", "name", "email", "phone", "address", "created_at") VALUES ('2', 'Ru''ya Shadiqa A', 'almahdalyruya@gmail.com', '0411865677', 'JL.PENDIDIKAN 1 NO.27, TIDUNG, KEC.RAPPOCINI, KOTA MAKASSAR, SULAWESI SELATAN 90222', '2026-10-19 05:58:41') ON CONFLICT DO NOTHING;
INSERT INTO public."customers" ("id", "name", "email", "phone", "address", "created_at") VALUES ('3', 'Andi Hermanto', 'habibiibnu.h17@gmail.com', '081234567890', 'Jl. Pendidikan 1 No. 27, Makassar', '2026-11-07 19:44:33') ON CONFLICT DO NOTHING;
INSERT INTO public."customers" ("id", "name", "email", "phone", "address", "created_at") VALUES ('4', 'Sernovita Bunga', 'sernovitab@gmail.com', '085299887766', 'Makassar', '2026-11-07 19:44:59') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."customers"', 'id'), COALESCE((SELECT MAX(id) FROM public."customers"), 1));

-- Table: users
CREATE TABLE IF NOT EXISTS public."users" (
    "id" SERIAL PRIMARY KEY,
    "name" VARCHAR(201) NOT NULL,
    "phone" VARCHAR(24),
    "email" VARCHAR(150) NOT NULL,
    "password" VARCHAR(255) NOT NULL,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "profile_image" VARCHAR(255),
    "business_name" VARCHAR(150),
    "social_media" VARCHAR(255),
    "website_url" VARCHAR(255),
    "birth_date" DATE,
    "address" TEXT,
    "business_type" VARCHAR(100),
    "business_role" VARCHAR(100),
    "business_address" TEXT,
    "business_description" TEXT,
    "service_preferences" TEXT
);

-- Data for: users (3 rows)
INSERT INTO public."users" ("id", "name", "phone", "email", "password", "created_at", "profile_image", "business_name", "social_media", "website_url", "birth_date", "address", "business_type", "business_role", "business_address", "business_description", "service_preferences") VALUES ('1', 'Ru''ya A', NULL, 'almahdalyruya@gmail.com', '$2y$10$dlGDynuMOH10solmWhLev.inea.B1p.2oZsRY0XG0CLReOSvPyCAa', '2026-10-21 07:08:30', NULL, 'Hidroponik Berkah Tamalanrea', '@hidroponik.berkah', 'https://hidroponikberkah.id', '1998-08-15', 'Jl. Sultan Alauddin No. 108, Makassar', 'Pertanian & Perkebunan', 'Founder & Pengelola Utama', '', 'Spesialis selada butterhead dan kangkung hidroponik rumahan kualitas restoran.', 'Branding') ON CONFLICT DO NOTHING;
INSERT INTO public."users" ("id", "name", "phone", "email", "password", "created_at", "profile_image", "business_name", "social_media", "website_url", "birth_date", "address", "business_type", "business_role", "business_address", "business_description", "service_preferences") VALUES ('2', 'Andi Hermanto', NULL, 'habibiibnu.h17@gmail.com', '$2y$10$5MZdr5tWUIksdaWIYi4gAuqHLUEQqY06YmrMvterjFEn5Qcm3QPM.', '2026-10-26 05:35:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."users" ("id", "name", "phone", "email", "password", "created_at", "profile_image", "business_name", "social_media", "website_url", "birth_date", "address", "business_type", "business_role", "business_address", "business_description", "service_preferences") VALUES ('3', 'Sernovita Bunga', '081213725107', 'sernovitab@gmail.com', '$2y$10$oMpgE2Xjf6TyC.NnGHF9KuP1UD9IroRhIJ7CsZIEz6nUxI6r4ChbO', '2026-11-02 07:04:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL) ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."users"', 'id'), COALESCE((SELECT MAX(id) FROM public."users"), 1));

-- Table: user_addresses
CREATE TABLE IF NOT EXISTS public."user_addresses" (
    "id" SERIAL PRIMARY KEY,
    "user_id" INT NOT NULL,
    "city" VARCHAR(100) NOT NULL,
    "address_details" VARCHAR(255) NOT NULL,
    "subdistrict" VARCHAR(100) NOT NULL,
    "rt_rw" VARCHAR(30),
    "postal_code" VARCHAR(10) NOT NULL,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Data for: user_addresses (3 rows)
INSERT INTO public."user_addresses" ("id", "user_id", "city", "address_details", "subdistrict", "rt_rw", "postal_code", "created_at") VALUES ('1', '1', 'MAKASSAR', 'Jl Cisadane 1 no 09', 'Moncongloe', '-', '90562', '2026-10-21 07:08:30') ON CONFLICT DO NOTHING;
INSERT INTO public."user_addresses" ("id", "user_id", "city", "address_details", "subdistrict", "rt_rw", "postal_code", "created_at") VALUES ('2', '2', 'MAKASSAR', 'JL.PENDIDIKAN 1 NO.27, TIDUNG, KEC.RAPPOCINI, KOTA MAKASSAR, SULAWESI SELATAN 90222', 'Moncongloe', '-', '90562', '2026-10-26 05:35:29') ON CONFLICT DO NOTHING;
INSERT INTO public."user_addresses" ("id", "user_id", "city", "address_details", "subdistrict", "rt_rw", "postal_code", "created_at") VALUES ('3', '3', 'MAKASSAR', 'JL.PENDIDIKAN 1 NO.27, TIDUNG, KEC.RAPPOCINI, KOTA MAKASSAR, SULAWESI SELATAN 90222', 'Moncongloe', '-', '90562', '2026-11-02 07:04:23') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."user_addresses"', 'id'), COALESCE((SELECT MAX(id) FROM public."user_addresses"), 1));

-- Table: registration_pending
CREATE TABLE IF NOT EXISTS public."registration_pending" (
    "id" SERIAL PRIMARY KEY,
    "first_name" VARCHAR(100) NOT NULL,
    "last_name" VARCHAR(100) NOT NULL,
    "phone" VARCHAR(24) NOT NULL,
    "email" VARCHAR(150) NOT NULL,
    "password" VARCHAR(255) NOT NULL,
    "otp_hash" VARCHAR(255),
    "otp_expires_at" TIMESTAMPTZ,
    "otp_attempts" SMALLINT DEFAULT 0 NOT NULL,
    "verified_at" TIMESTAMPTZ,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Data for: registration_pending (1 rows)
INSERT INTO public."registration_pending" ("id", "first_name", "last_name", "phone", "email", "password", "otp_hash", "otp_expires_at", "otp_attempts", "verified_at", "created_at") VALUES ('18', 'Anilva', 'Fitriani', '', 'ruyashadiqah18@gmail.com', '$2y$10$vvrl2AkR.EqyvBqI4K.7yu4oVFEmzDAY/fPQETWSPvkKMjkZBeF5O', '$2y$10$OtekpM018whQfHMG5EHHlOlflAYdelUkGkIbzH.kG.XlQ92FlXF6S', '2026-11-01 14:26:30', '0', NULL, '2026-11-01 13:16:30') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."registration_pending"', 'id'), COALESCE((SELECT MAX(id) FROM public."registration_pending"), 1));

-- Table: orders
CREATE TABLE IF NOT EXISTS public."orders" (
    "id" SERIAL PRIMARY KEY,
    "order_number" VARCHAR(30) NOT NULL,
    "customer_id" INT NOT NULL,
    "total_amount" NUMERIC(12,2) DEFAULT 0.00 NOT NULL,
    "status" VARCHAR(50) DEFAULT 'menunggu_pembayaran' NOT NULL,
    "notes" TEXT,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updated_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "payment_method" VARCHAR(50) DEFAULT 'midtrans',
    "snap_token" VARCHAR(255),
    "midtrans_transaction_id" VARCHAR(100),
    "payment_type" VARCHAR(50),
    "payment_code" VARCHAR(100),
    "biller_code" VARCHAR(50),
    "qr_url" TEXT,
    "qr_string" TEXT,
    "deeplink_url" TEXT,
    "gross_amount" NUMERIC(12,2),
    "transaction_status" VARCHAR(50),
    "payment_status" VARCHAR(50) DEFAULT 'menunggu_pembayaran' NOT NULL,
    "transaction_time" TIMESTAMPTZ,
    "expiry_time" TIMESTAMPTZ,
    "settlement_time" TIMESTAMPTZ,
    "pdf_invoice" VARCHAR(255),
    "midtrans_response" TEXT,
    "business_name" VARCHAR(150),
    "social_media" VARCHAR(255),
    "website_url" VARCHAR(255),
    "user_id" INT
);

-- Data for: orders (16 rows)
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('1', 'BF-20261016144750-03BD24', '1', '300000.00', 'completed', 'butuh logo yg sesuai dengan merek brand', '2026-10-16 12:47:50', '2026-11-10 12:32:04', 'midtrans', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('2', 'BF-20261019075841-4E2BBF', '2', '150000.00', 'processing', '', '2026-10-19 05:58:41', '2026-11-16 03:27:48', 'midtrans', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('3', 'BF-20261107204433-502126', '3', '150000.00', 'pending', 'Nama Usaha: Makassar Hydro Farm
Sosial Media: https://instagram.com/mks_hydro
Website: https://makassarhydro.com
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)
Catatan Tambahan: Mohon logo warna hijau segar.', '2026-11-07 19:44:33', '2026-11-10 10:11:38', 'midtrans', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Makassar Hydro Farm', 'https://instagram.com/mks_hydro', 'https://makassarhydro.com', '2') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('4', 'BF-20261107204459-7E8D4E', '4', '300000.00', 'pending', 'Nama Usaha: Celebes Greens
Sosial Media: https://instagram.com/celebesgreens
Metode Pembayaran: Transfer Bank BCA (No. Rek: 7920-1829-33 a.n. PT Balla Fresh Digital)
Catatan Tambahan: Tolong percepat estimasi pengerjaan.', '2026-11-07 19:44:59', '2026-11-10 12:24:48', 'bca', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Celebes Greens', 'https://instagram.com/celebesgreens', '', '3') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('5', 'BF-20261116034244-991471', '1', '950000.00', 'pending', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://www.instagram.com/ruyasha_/
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)
Catatan Tambahan: -', '2026-11-16 02:42:45', '2026-11-16 02:46:05', 'midtrans', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '950000.00', NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://www.instagram.com/ruyasha_/', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('6', 'BF-20261116065732-9EBCBC', '1', '300000.00', 'pending', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-16 05:57:32', '2026-11-16 05:57:32', 'midtrans', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '300000.00', NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('7', 'BF-20261116070852-188758', '1', '300000.00', 'pending', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-16 06:08:52', '2026-11-16 06:08:52', 'midtrans', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '300000.00', NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('8', 'BF-20261116074846-38896F', '1', '150000.00', 'pending', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-16 06:48:47', '2026-11-16 06:48:47', 'midtrans', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '150000.00', NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('9', 'BF-20261116075926-05CD54', '1', '450000.00', 'pending', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-16 06:59:26', '2026-11-16 06:59:26', 'midtrans', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '450000.00', NULL, 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('10', 'BF-20261118032143-1146BF', '1', '300000.00', 'pending', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://www.instagram.com/ruyasha_/
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-18 02:21:43', '2026-11-18 02:21:43', 'midtrans', NULL, NULL, 'qris', NULL, NULL, NULL, NULL, NULL, '300000.00', 'pending', 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://www.instagram.com/ruyasha_/', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('11', 'BF-20261118032214-0C06ED', '1', '300000.00', 'pending', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-18 02:22:14', '2026-11-18 02:22:14', 'midtrans', NULL, NULL, 'qris', NULL, NULL, NULL, NULL, NULL, '300000.00', 'pending', 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('12', 'BF-20261118032308-E2F354', '1', '600000.00', 'pending', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: http://instagram.com/ruyasha_
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-18 02:23:08', '2026-11-18 02:23:08', 'midtrans', NULL, NULL, 'qris', NULL, NULL, NULL, NULL, NULL, '600000.00', 'pending', 'menunggu_pembayaran', NULL, NULL, NULL, NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'http://instagram.com/ruyasha_', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('13', 'BF-20261118044335-F06E85', '1', '600000.00', 'processing', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-18 03:43:36', '2026-11-18 03:44:13', 'midtrans', NULL, 'SANDBOX-20261118044413-356B7D', 'qris', NULL, NULL, NULL, NULL, NULL, '600000.00', 'settlement', 'settlement', NULL, NULL, '2026-11-18 04:44:13', NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('14', 'BF-20261120143627-F7F751', '1', '300000.00', 'processing', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)
Catatan Tambahan: buatkan yg sesuai dan bagus saja ya!', '2026-11-20 13:36:29', '2026-11-20 13:36:48', 'midtrans', NULL, 'SANDBOX-20261120143648-F5962F', 'bca_va', NULL, NULL, NULL, NULL, NULL, '300000.00', 'settlement', 'settlement', NULL, NULL, '2026-11-20 14:36:48', NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('15', 'BF-20261120152500-ACDB5A', '1', '300000.00', 'processing', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)', '2026-11-20 14:25:01', '2026-11-20 14:25:58', 'midtrans', NULL, 'SANDBOX-20261120152558-BEE27C', 'snap', NULL, NULL, NULL, NULL, NULL, '300000.00', 'settlement', 'settlement', NULL, NULL, '2026-11-20 15:25:58', NULL, NULL, 'Hidroponik Berkah Tamalanrea', 'https://github.com/hermantoXYZ/EcommerceBisdig/tree/main/app', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
INSERT INTO public."orders" ("id", "order_number", "customer_id", "total_amount", "status", "notes", "created_at", "updated_at", "payment_method", "snap_token", "midtrans_transaction_id", "payment_type", "payment_code", "biller_code", "qr_url", "qr_string", "deeplink_url", "gross_amount", "transaction_status", "payment_status", "transaction_time", "expiry_time", "settlement_time", "pdf_invoice", "midtrans_response", "business_name", "social_media", "website_url", "user_id") VALUES ('16', 'BF-20261122135125-B07B20', '1', '300000.00', 'processing', 'Nama Usaha: Hidroponik Berkah Tamalanrea
Sosial Media: https://www.instagram.com/ruyasha_/
Website: https://hidroponikberkah.id
Metode Pembayaran: Midtrans Payment Gateway (QRIS, GoPay, ShopeePay, OVO, Kartu Kredit)
Catatan Tambahan: kasi bagus hasil desainnya ya', '2026-11-22 12:51:25', '2026-11-22 12:51:43', 'midtrans', NULL, '2d14001f-3ef2-4a4e-9e3a-10a4e68fd280', 'gopay', NULL, NULL, 'https://api.sandbox.midtrans.com/v2/gopay/2d14001f-3ef2-4a4e-9e3a-10a4e68fd280/qr-code', '', 'https://simulator.sandbox.midtrans.com/v2/deeplink/detail?tref=A120261122125125Rxwl8a3XbiID&callbackUrl=http%3A%2F%2Flocalhost%2Fballa-fresh%2Fpayment_success.php%3Forder_id%3DBF-20261122135125-B07B20%26order_id%3DBF-20261122135125-B07B20', '300000.00', 'settlement', 'settlement', NULL, '2026-11-22 20:06:25', '2026-11-22 19:51:39', NULL, '{"status_code":"201","status_message":"Gopay transaction is created","transaction_id":"2d14001f-3ef2-4a4e-9e3a-10a4e68fd280","order_id":"BF-20261122135125-B07B20","merchant_id":"M144205216","gross_amount":"300000.00","currency":"IDR","payment_type":"gopay","transaction_time":"2026-11-22 19:51:25","transaction_status":"pending","fraud_status":"accept","actions":[{"name":"generate-qr-code","method":"GET","url":"https://api.sandbox.midtrans.com/v2/gopay/2d14001f-3ef2-4a4e-9e3a-10a4e68fd280/qr-code"},{"name":"generate-qr-code-v2","method":"GET","url":"https://merchants-app.sbx.midtrans.com/v4/qris/gopay/A120261122125125Rxwl8a3XbiID/qr-code"},{"name":"deeplink-redirect","method":"GET","url":"https://simulator.sandbox.midtrans.com/v2/deeplink/detail?tref=A120261122125125Rxwl8a3XbiID&callbackUrl=http%3A%2F%2Flocalhost%2Fballa-fresh%2Fpayment_success.php%3Forder_id%3DBF-20261122135125-B07B20%26order_id%3DBF-20261122135125-B07B20"},{"name":"get-status","method":"GET","url":"https://api.sandbox.midtrans.com/v2/2d14001f-3ef2-4a4e-9e3a-10a4e68fd280/status"},{"name":"cancel","method":"POST","url":"https://api.sandbox.midtrans.com/v2/2d14001f-3ef2-4a4e-9e3a-10a4e68fd280/cancel"}],"qr_string":"","expiry_time":"2026-11-22 20:06:25"}', 'Hidroponik Berkah Tamalanrea', 'https://www.instagram.com/ruyasha_/', 'https://hidroponikberkah.id', '1') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."orders"', 'id'), COALESCE((SELECT MAX(id) FROM public."orders"), 1));

-- Table: order_items
CREATE TABLE IF NOT EXISTS public."order_items" (
    "id" SERIAL PRIMARY KEY,
    "order_id" INT NOT NULL,
    "service_id" INT NOT NULL,
    "service_name" VARCHAR(150) NOT NULL,
    "quantity" INT DEFAULT 1 NOT NULL,
    "unit_price" NUMERIC(12,2) NOT NULL,
    "subtotal" NUMERIC(12,2) NOT NULL
);

-- Data for: order_items (18 rows)
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('1', '1', '1', 'Paket Starter Branding', '2', '150000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('2', '2', '1', 'Paket Starter Branding', '1', '150000.00', '150000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('3', '3', '1', 'Paket Starter Branding', '1', '150000.00', '150000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('4', '4', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('5', '5', '3', 'Paket Full Marketing Scale', '1', '500000.00', '500000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('6', '5', '1', 'Paket Starter Branding', '1', '150000.00', '150000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('7', '5', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('8', '6', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('9', '7', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('10', '8', '1', 'Paket Starter Branding', '1', '150000.00', '150000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('11', '9', '1', 'Paket Starter Branding', '3', '150000.00', '450000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('12', '10', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('13', '11', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('14', '12', '2', 'Paket E-Commerce Grow', '2', '300000.00', '600000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('15', '13', '2', 'Paket E-Commerce Grow', '2', '300000.00', '600000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('16', '14', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('17', '15', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
INSERT INTO public."order_items" ("id", "order_id", "service_id", "service_name", "quantity", "unit_price", "subtotal") VALUES ('18', '16', '2', 'Paket E-Commerce Grow', '1', '300000.00', '300000.00') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."order_items"', 'id'), COALESCE((SELECT MAX(id) FROM public."order_items"), 1));

-- Table: order_transactions
CREATE TABLE IF NOT EXISTS public."order_transactions" (
    "id" SERIAL PRIMARY KEY,
    "order_id" INT NOT NULL,
    "order_number" VARCHAR(50) NOT NULL,
    "midtrans_transaction_id" VARCHAR(100),
    "payment_type" VARCHAR(50),
    "gross_amount" NUMERIC(12,2) DEFAULT 0.00 NOT NULL,
    "transaction_status" VARCHAR(50) NOT NULL,
    "fraud_status" VARCHAR(50),
    "status_code" VARCHAR(10),
    "signature_key" VARCHAR(255),
    "raw_response" TEXT,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "settlement_time" TIMESTAMPTZ
);

-- Data for: order_transactions (16 rows)
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('1', '5', 'BF-20261116034244-991471', NULL, NULL, '950000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-16 02:42:45', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('2', '6', 'BF-20261116065732-9EBCBC', NULL, NULL, '300000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-16 05:57:32', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('3', '7', 'BF-20261116070852-188758', NULL, NULL, '300000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-16 06:08:52', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('4', '8', 'BF-20261116074846-38896F', NULL, NULL, '150000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-16 06:48:47', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('5', '9', 'BF-20261116075926-05CD54', NULL, NULL, '450000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-16 06:59:26', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('6', '10', 'BF-20261118032143-1146BF', NULL, 'qris', '300000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-18 02:21:43', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('7', '11', 'BF-20261118032214-0C06ED', NULL, 'qris', '300000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-18 02:22:14', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('8', '12', 'BF-20261118032308-E2F354', NULL, 'qris', '600000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-18 02:23:08', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('9', '13', 'BF-20261118044335-F06E85', NULL, 'qris', '600000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-18 03:43:36', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('10', '13', 'BF-20261118044335-F06E85', 'SANDBOX-20261118044413-356B7D', 'qris', '600000.00', 'settlement', NULL, NULL, NULL, '{"transaction_id":"SANDBOX-20261118044413-356B7D","transaction_status":"settlement","payment_type":"qris","settlement_time":"2026-11-18 04:44:13","simulator":"sandbox_manual"}', '2026-11-18 03:44:13', '2026-11-18 04:44:13') ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('11', '14', 'BF-20261120143627-F7F751', NULL, 'bca_va', '300000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-20 13:36:30', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('12', '14', 'BF-20261120143627-F7F751', 'SANDBOX-20261120143648-F5962F', 'bca_va', '300000.00', 'settlement', NULL, NULL, NULL, '{"transaction_id":"SANDBOX-20261120143648-F5962F","transaction_status":"settlement","payment_type":"bca_va","settlement_time":"2026-11-20 14:36:48","simulator":"sandbox_manual"}', '2026-11-20 13:36:48', '2026-11-20 14:36:48') ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('13', '15', 'BF-20261120152500-ACDB5A', NULL, 'snap', '300000.00', 'pending', NULL, NULL, NULL, NULL, '2026-11-20 14:25:01', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('14', '15', 'BF-20261120152500-ACDB5A', 'SANDBOX-20261120152558-BEE27C', 'snap', '300000.00', 'settlement', NULL, NULL, NULL, '{"transaction_id":"SANDBOX-20261120152558-BEE27C","transaction_status":"settlement","payment_type":"snap","settlement_time":"2026-11-20 15:25:58","simulator":"sandbox_manual"}', '2026-11-20 14:25:58', '2026-11-20 15:25:58') ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('15', '16', 'BF-20261122135125-B07B20', '2d14001f-3ef2-4a4e-9e3a-10a4e68fd280', 'gopay', '300000.00', 'pending', NULL, NULL, NULL, '{"status_code":"201","status_message":"Gopay transaction is created","transaction_id":"2d14001f-3ef2-4a4e-9e3a-10a4e68fd280","order_id":"BF-20261122135125-B07B20","merchant_id":"M144205216","gross_amount":"300000.00","currency":"IDR","payment_type":"gopay","transaction_time":"2026-11-22 19:51:25","transaction_status":"pending","fraud_status":"accept","actions":[{"name":"generate-qr-code","method":"GET","url":"https://api.sandbox.midtrans.com/v2/gopay/2d14001f-3ef2-4a4e-9e3a-10a4e68fd280/qr-code"},{"name":"generate-qr-code-v2","method":"GET","url":"https://merchants-app.sbx.midtrans.com/v4/qris/gopay/A120261122125125Rxwl8a3XbiID/qr-code"},{"name":"deeplink-redirect","method":"GET","url":"https://simulator.sandbox.midtrans.com/v2/deeplink/detail?tref=A120261122125125Rxwl8a3XbiID&callbackUrl=http%3A%2F%2Flocalhost%2Fballa-fresh%2Fpayment_success.php%3Forder_id%3DBF-20261122135125-B07B20%26order_id%3DBF-20261122135125-B07B20"},{"name":"get-status","method":"GET","url":"https://api.sandbox.midtrans.com/v2/2d14001f-3ef2-4a4e-9e3a-10a4e68fd280/status"},{"name":"cancel","method":"POST","url":"https://api.sandbox.midtrans.com/v2/2d14001f-3ef2-4a4e-9e3a-10a4e68fd280/cancel"}],"qr_string":"","expiry_time":"2026-11-22 20:06:25"}', '2026-11-22 12:51:25', NULL) ON CONFLICT DO NOTHING;
INSERT INTO public."order_transactions" ("id", "order_id", "order_number", "midtrans_transaction_id", "payment_type", "gross_amount", "transaction_status", "fraud_status", "status_code", "signature_key", "raw_response", "created_at", "settlement_time") VALUES ('16', '16', 'BF-20261122135125-B07B20', '2d14001f-3ef2-4a4e-9e3a-10a4e68fd280', 'gopay', '300000.00', 'settlement', NULL, NULL, NULL, '{"status_code":"200","transaction_id":"2d14001f-3ef2-4a4e-9e3a-10a4e68fd280","gross_amount":"300000.00","currency":"IDR","order_id":"BF-20261122135125-B07B20","payment_type":"gopay","signature_key":"f2f45ffa54c38bcde133d5f80e6c0620414321aa8269499a3c4acb5e3079e022ff6378b7715b9b6407b423165c3df824ea908ce411923059f1ccd4236c7fa4d4","transaction_status":"settlement","fraud_status":"accept","status_message":"Success, transaction is found","merchant_id":"M144205216","transaction_time":"2026-11-22 19:51:25","settlement_time":"2026-11-22 19:51:39","expiry_time":"2026-11-22 20:06:25"}', '2026-11-22 12:51:43', '2026-11-22 19:51:39') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."order_transactions"', 'id'), COALESCE((SELECT MAX(id) FROM public."order_transactions"), 1));

-- Table: consultations
CREATE TABLE IF NOT EXISTS public."consultations" (
    "id" SERIAL PRIMARY KEY,
    "user_id" INT,
    "client_name" VARCHAR(100) NOT NULL,
    "business_name" VARCHAR(150) NOT NULL,
    "phone" VARCHAR(30),
    "email" VARCHAR(150),
    "category" VARCHAR(100),
    "notes" TEXT,
    "status" VARCHAR(50) DEFAULT 'pending' NOT NULL,
    "admin_notes" TEXT,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "approved_at" TIMESTAMPTZ
);

-- Data for: consultations (2 rows)
INSERT INTO public."consultations" ("id", "user_id", "client_name", "business_name", "phone", "email", "category", "notes", "status", "admin_notes", "created_at", "approved_at") VALUES ('1', '1', 'Ru''ya A', 'Hidroponik Berkah Tamalanrea', '081234567890', 'ruya@example.com', 'Desain Kemasan', 'Kami membutuhkan desain kemasan botol dan stiker label tahan air untuk produk selada.', 'approved', NULL, '2026-11-10 11:59:43', '2026-11-10 11:59:43') ON CONFLICT DO NOTHING;
INSERT INTO public."consultations" ("id", "user_id", "client_name", "business_name", "phone", "email", "category", "notes", "status", "admin_notes", "created_at", "approved_at") VALUES ('3', '3', 'Sernovita Bunga', 'Hidroponik Berkah Tamalanrea', '081213725107', 'sernovitab@gmail.com', 'E-Commerce Grow', 'Halo kak usaha saya sedang terkendala di penjualan digitalnya,saya merasa usaha saya butuh untuk lebih di kembangkan,kendalanya adalah saat ini saya sudah memiliki tim namun pengetahuan akan hal ini masi kurang', 'pending', NULL, '2026-11-16 03:26:09', NULL) ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."consultations"', 'id'), COALESCE((SELECT MAX(id) FROM public."consultations"), 1));

-- Table: consultation_messages
CREATE TABLE IF NOT EXISTS public."consultation_messages" (
    "id" SERIAL PRIMARY KEY,
    "consultation_id" INT NOT NULL,
    "sender_type" VARCHAR(50) NOT NULL,
    "sender_id" INT NOT NULL,
    "sender_name" VARCHAR(100) NOT NULL,
    "message" TEXT NOT NULL,
    "created_at" TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Data for: consultation_messages (7 rows)
INSERT INTO public."consultation_messages" ("id", "consultation_id", "sender_type", "sender_id", "sender_name", "message", "created_at") VALUES ('1', '1', 'user', '1', 'Ru''ya A', 'Halo tim Balla Fresh, apakah kemasan botol bisa selesai dalam 5 hari kerja?', '2026-11-10 11:59:43') ON CONFLICT DO NOTHING;
INSERT INTO public."consultation_messages" ("id", "consultation_id", "sender_type", "sender_id", "sender_name", "message", "created_at") VALUES ('2', '1', 'admin', '1', 'Admin Lead', 'Halo Kak Ru''ya, tentu bisa! Tim kami akan menyiapkan 3 opsi mockup kemasan dan label anti-air. Mohon siapkan ukuran dimensi botolnya.', '2026-11-10 11:59:43') ON CONFLICT DO NOTHING;
INSERT INTO public."consultation_messages" ("id", "consultation_id", "sender_type", "sender_id", "sender_name", "message", "created_at") VALUES ('3', '1', 'admin', '1', 'Ru''ya Shadiqa A', 'Halo kak selamat siang', '2026-11-10 12:09:41') ON CONFLICT DO NOTHING;
INSERT INTO public."consultation_messages" ("id", "consultation_id", "sender_type", "sender_id", "sender_name", "message", "created_at") VALUES ('4', '1', 'user', '1', 'Ru''ya A', 'halo kak iya selamat siang,bagaimana progressnya kak,ada yg perlu di diskusikan?', '2026-11-10 12:10:25') ON CONFLICT DO NOTHING;
INSERT INTO public."consultation_messages" ("id", "consultation_id", "sender_type", "sender_id", "sender_name", "message", "created_at") VALUES ('5', '1', 'admin', '1', 'Ru''ya Shadiqa A', 'iya kak,untuk progressnya sudah bisa dilihat ya', '2026-11-13 06:50:27') ON CONFLICT DO NOTHING;
INSERT INTO public."consultation_messages" ("id", "consultation_id", "sender_type", "sender_id", "sender_name", "message", "created_at") VALUES ('6', '1', 'user', '1', 'Ru''ya A', '[Lampiran] dkv klp 1 (1).png
URL: http://localhost/balla-fresh/uploads/chat_attachments/chat-1-6e34786ad57669b56c29bdb4.png', '2026-11-13 07:31:43') ON CONFLICT DO NOTHING;
INSERT INTO public."consultation_messages" ("id", "consultation_id", "sender_type", "sender_id", "sender_name", "message", "created_at") VALUES ('8', '3', 'user', '3', 'Sernovita Bunga', 'Permintaan konsultasi diajukan dari halaman beranda:
Topik: E-Commerce Grow
Kebutuhan: Halo kak usaha saya sedang terkendala di penjualan digitalnya,saya merasa usaha saya butuh untuk lebih di kembangkan,kendalanya adalah saat ini saya sudah memiliki tim namun pengetahuan akan hal ini masi kurang', '2026-11-16 03:26:09') ON CONFLICT DO NOTHING;
SELECT setval(pg_get_serial_sequence('public."consultation_messages"', 'id'), COALESCE((SELECT MAX(id) FROM public."consultation_messages"), 1));

-- --------------------------------------------------------------------------
-- 3. Enable RLS or Public Access Policy for Supabase
-- --------------------------------------------------------------------------
ALTER TABLE public."admins" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."admins";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."admins" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."agency_services" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."agency_services";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."agency_services" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."agency_site_content" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."agency_site_content";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."agency_site_content" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."agency_team" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."agency_team";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."agency_team" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."categories" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."categories";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."categories" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."services" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."services";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."services" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."service_reviews" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."service_reviews";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."service_reviews" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."articles" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."articles";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."articles" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."customers" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."customers";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."customers" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."users" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."users";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."users" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."user_addresses" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."user_addresses";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."user_addresses" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."registration_pending" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."registration_pending";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."registration_pending" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."orders" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."orders";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."orders" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."order_items" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."order_items";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."order_items" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."order_transactions" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."order_transactions";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."order_transactions" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."consultations" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."consultations";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."consultations" FOR ALL USING (true) WITH CHECK (true);
ALTER TABLE public."consultation_messages" ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS "Allow full access for service_role and authenticated" ON public."consultation_messages";
CREATE POLICY "Allow full access for service_role and authenticated" ON public."consultation_messages" FOR ALL USING (true) WITH CHECK (true);

COMMIT;

-- Grant full privileges to all roles (mencegah error permission denied pada Supabase)
GRANT USAGE ON SCHEMA public TO postgres, anon, authenticated, service_role;
GRANT ALL ON ALL TABLES IN SCHEMA public TO postgres, anon, authenticated, service_role;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO postgres, anon, authenticated, service_role;
GRANT ALL ON ALL ROUTINES IN SCHEMA public TO postgres, anon, authenticated, service_role;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO postgres, anon, authenticated, service_role;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO postgres, anon, authenticated, service_role;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON ROUTINES TO postgres, anon, authenticated, service_role;

