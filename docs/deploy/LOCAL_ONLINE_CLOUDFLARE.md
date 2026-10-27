# Menjalankan Tensura dari Komputer Lokal melalui Cloudflare

Panduan ini menggunakan **Docker Desktop dengan WSL 2, Nginx, PHP-FPM, PostgreSQL 18, Cloudflare Tunnel, dan Cloudflare Access**.

Cloudflare Access adalah lapisan autentikasi tambahan yang opsional. Untuk memakai Tunnel saja dengan login Tensura, ikuti pilihan tanpa Access pada langkah 9–11. Domain tetap diperlukan untuk named tunnel dengan hostname publik tetap.

Disesuaikan dengan hasil pemeriksaan proyek dan dokumentasi resmi pada **27 Oktober 2026**. Konfigurasi contoh belum diuji pada komputer Windows tujuan.

## Informasi aplikasi dan komputer server

### Komputer server

| Bagian | Spesifikasi |
| --- | --- |
| Perangkat | Acer Aspire C24-1800 |
| Sistem | Windows 11 Home Single Language, build 26200, 64-bit |
| CPU | Intel Core i3-1305U, 5 core, 6 logical processor |
| RAM | 8 GB terpasang, sekitar 3,1 GB tersedia saat idle |
| Penyimpanan | NVMe 512 GB; C: tersisa sekitar 56 GB, D: sekitar 224 GB, NTFS |
| Jaringan | Ethernet 100 Mbps |
| Pengguna | Tim internal, sekitar 3–7 orang |

Komputer ini layak dicoba untuk penggunaan ringan. **RAM merupakan batas utama**, terutama saat beberapa pengguna membuka surat bersamaan dengan konversi PDF. Peningkatan ke 16 GB akan membantu jika pemakaian meningkat. Jumlah akun bukan jaminan kapasitas pengguna serentak; ukur beban nyata setelah deployment.

### Hasil pemeriksaan proyek

| Bagian | Konfigurasi |
| --- | --- |
| Laravel | 13.38.0 |
| Frontend | React 19, Inertia 3, Vite Plus |
| Package manager | pnpm, `pnpm-lock.yaml`, dan katalog dependency |
| PHP | Gunakan 8.4, termasuk `pdo_pgsql` dan `pcntl` |
| Database | PostgreSQL 18 |
| Queue surat | Connection `letters`, queue `letters` |
| PDF | LibreOffice, Fontconfig, dan font |
| Status operasi surat | SSE dan PostgreSQL `LISTEN/NOTIFY` |
| Scheduler | Recovery setiap menit; cleanup setiap jam |
| Penyimpanan | Dokumen, templat, dan font di `storage/app` |
| Akun bawaan | `UserSeeder` memakai password `password`; hindari saat deployment |

Node.js hanya digunakan saat build aset. Server tidak menjalankan Vite dev server atau SSR. Database, cache, session, dan antrean mengikuti konfigurasi database proyek tanpa tambahan Redis.

Tools PHP, Composer, dan Node.js pada Windows tidak menentukan versi runtime container. PostgreSQL Client tidak perlu dipasang di Windows karena utilitasnya tersedia di container database.

## Arsitektur

```text
Browser anggota tim
        │ HTTPS
        ▼
Cloudflare Access — verifikasi identitas/email
        │
        ▼
Cloudflare Tunnel
        │ koneksi keluar dari komputer server
        ▼
cloudflared → Nginx:8080 → PHP-FPM
                              │
                         PostgreSQL:5432

Worker letters dan scheduler memakai database
serta volume storage yang sama dengan aplikasi.
```

Seluruh perintah Windows dijalankan melalui **PowerShell**, dari direktori proyek, kecuali dinyatakan lain. Ganti contoh domain, email, dan password dengan nilai Anda sendiri.

## 1. Pasang WSL 2 dan Docker Desktop

Windows 11 Home menjalankan container Linux menggunakan backend WSL 2. Docker Desktop mengelola lingkungan Linux internalnya sendiri. Anda tidak perlu memasang Ubuntu, menjalankan `apt` secara manual, atau mengelola service dalam distro terpisah.

Referensi: [Docker Desktop WSL 2](https://docs.docker.com/desktop/features/wsl/).

1. Buka **Task Manager → Performance → CPU**.
2. Pastikan **Virtualization: Enabled**. Jika belum, aktifkan virtualisasi Intel di BIOS/UEFI.
3. Buka **PowerShell sebagai Administrator**.
4. Jalankan:

```powershell
wsl --install --no-distribution
```

5. Restart Windows jika diminta.
6. Setelah restart, jalankan:

```powershell
wsl --update
wsl --set-default-version 2
wsl --version
```

`--no-distribution` memasang WSL tanpa distro pengguna. Docker saat pemeriksaan mensyaratkan WSL minimal 2.1.5; gunakan versi terbaru yang tersedia.

Referensi: [Perintah WSL](https://learn.microsoft.com/en-us/windows/wsl/basic-commands), [instalasi Docker Desktop Windows](https://docs.docker.com/desktop/setup/install/windows-install/).

7. Unduh **Docker Desktop for Windows — x86_64/AMD64** dari halaman resmi di atas.
8. Saat instalasi, pilih backend **WSL 2** jika ditawarkan.
9. Jalankan **Linux containers**.
10. Buka Docker Desktop dan tunggu engine siap.
11. Periksa:

```powershell
docker version
docker compose version
docker run --rm hello-world
```

`docker version` harus menampilkan bagian **Client** dan **Server**. Docker Compose sudah termasuk; gunakan `docker compose`.

## 2. Batasi RAM dan pindahkan data Docker ke D:

Buat direktori:

```powershell
New-Item -ItemType Directory -Force D:\DockerData, D:\WSLSwap
```

Buka konfigurasi WSL:

```powershell
notepad "$env:USERPROFILE\.wslconfig"
```

Jika file sudah ada, gabungkan pengaturannya. Gunakan batas awal berikut:

```ini
[wsl2]
memory=3GB
processors=4
swap=3GB
swapFile=D:\\WSLSwap\\swap.vhdx

[experimental]
autoMemoryReclaim=gradual
```

- `memory=3GB` adalah batas maksimum RAM VM WSL, bukan RAM yang langsung dipesan.
- `processors=4` menyisakan ruang pemrosesan untuk Windows.
- Swap membantu menghadapi lonjakan, tetapi jauh lebih lambat daripada RAM.
- Konfigurasi berlaku untuk seluruh lingkungan WSL 2 pada akun tersebut.

Simpan dengan nama persis **`.wslconfig`**, bukan `.wslconfig.txt`.

Referensi: [Konfigurasi WSL](https://learn.microsoft.com/en-us/windows/wsl/wsl-config).

Untuk menerapkannya:

1. Keluar dari Docker Desktop menggunakan **Quit**.
2. Jalankan:

```powershell
wsl --shutdown
```

3. Buka kembali Docker Desktop.
4. Buka **Settings → Resources → Advanced**.
5. Ubah **Disk image location** menjadi `D:\DockerData`.
6. Ikuti proses pemindahan dan restart.
7. Pastikan lokasi baru tampil sebelum build image.
8. Aktifkan **Settings → General → Start Docker Desktop when you sign in to your computer**.

Disk virtual ini menyimpan image, container, named volume, dan cache build. Memindahkan kode ke D: saja belum memindahkan data Docker.

Batas RAM diatur melalui `.wslconfig`; lokasi disk diatur melalui Docker Desktop. Jangan memindahkan atau menghapus distro internal Docker secara manual.

## 3. Dapatkan domain dan aktifkan DNS Cloudflare

Jalur utama panduan memakai domain milik Anda:

```text
Domain:   organisasi-anda.com
Aplikasi: tensura.organisasi-anda.com
```

Domain perlu didaftarkan dan diperpanjang secara berkala. Anda dapat membelinya melalui Cloudflare Registrar atau registrar lain.

### Jika membeli melalui Cloudflare Registrar

1. Buat akun Cloudflare dan verifikasi email.
2. Cari domain melalui menu pendaftaran domain.
3. Periksa biaya pendaftaran serta perpanjangannya.
4. Selesaikan pembayaran dan verifikasi kontak pendaftar.
5. Domain otomatis memakai nameserver Cloudflare.

Referensi: [Pendaftaran domain Cloudflare](https://developers.cloudflare.com/registrar/get-started/register-domain/).

### Jika membeli dari registrar lain

1. Tambahkan domain melalui dashboard Cloudflare.
2. Pilih paket DNS **Free**.
3. Catat nameserver yang diberikan Cloudflare.
4. Ganti nameserver di panel registrar dengan nameserver tersebut.
5. Tunggu status domain menjadi **Active**.

Jika domain sudah memiliki layanan email, pertahankan record email saat mengimpor DNS.

Referensi: [Menambahkan domain ke Cloudflare](https://developers.cloudflare.com/dns/zone-setups/full-setup/setup/).

Anda tidak perlu IP publik statis atau port forwarding router. Hostname aplikasi akan diarahkan ke Tunnel. Alternatif tanpa domain dibahas pada langkah 14.

## 4. Siapkan proyek dan environment deployment

Gunakan direktori:

```text
D:\Server\Tensura
```

Salin source terbaru atau clone repository. Jika ada perubahan yang belum masuk Git, pastikan perubahan tersebut ikut dibawa.

```powershell
Set-Location D:\Server\Tensura

New-Item -ItemType Directory -Force docker, secrets, backups

Copy-Item .env.example .env
```

Jalankan `Copy-Item` hanya ketika `.env` deployment belum ada.

Untuk pengujian HTTP lokal awal, edit nilai berikut pada `.env`:

```dotenv
APP_NAME=Tensura
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost:8080

APP_TIMEZONE=Asia/Makassar
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

INERTIA_SSR_ENABLED=false

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=tensura
DB_USERNAME=tensura
DB_PASSWORD=GANTI_DENGAN_PASSWORD_DATABASE_YANG_KUAT

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

LOG_CHANNEL=stderr
LOG_LEVEL=warning

MAIL_MAILER=log

VITE_APP_NAME="${APP_NAME}"
```

Gunakan password database acak yang panjang. `SESSION_SECURE_COOKIE=false` hanya untuk pengujian HTTP lokal; ubah sebelum publikasi.

Penyesuaian terhadap development:

- Host database menjadi `db` dan port container menjadi `5432`.
- Port `5433` pada `mise.toml` adalah milik database development lokal.
- Node dan Vite dev server tidak dijalankan terus-menerus.
- SSR dinonaktifkan untuk menghemat RAM.
- Connection `letters` sudah didefinisikan tersendiri; worker harus menyebutkannya secara eksplisit.

Buat `secrets/postgres-password.txt` berisi satu password administrator PostgreSQL, **berbeda dari `DB_PASSWORD`**. Simpan satu baris, UTF-8 tanpa BOM.

Tambahkan ke `.gitignore`:

```gitignore
/secrets/
/backups/
```

Batasi akses folder `secrets`, `.env`, dan backup melalui izin NTFS kepada akun pengelola yang diperlukan.

## 5. Siapkan Dockerfile dan konfigurasi server

Pada tahap ini, buat **enam file konfigurasi** di folder proyek Windows. Anda belum perlu akun Cloudflare atau domain. Tahap ini selesai setelah file dibuat, diisi, dan disimpan; perintah build Docker dijalankan pada tahap 7.

### 5.1. Buka folder proyek di VS Code

1. Buka VS Code.
2. Pilih **File → Open Folder**.
3. Pilih folder proyek, misalnya `D:\Server\Tensura`.
4. Pastikan panel Explorer menampilkan `artisan`, `composer.json`, dan `package.json`. Itulah root proyek, tempat Anda membuat file berikut.

### 5.2. Buat enam file

Pada panel Explorer VS Code, klik kanan folder proyek → **New File**, lalu buat:

- `Dockerfile`
- `.dockerignore`

Keduanya berada sejajar dengan `artisan`. Nama `Dockerfile` tidak memakai ekstensi; pastikan bukan `Dockerfile.txt`. Nama `.dockerignore` diawali titik dan tidak diakhiri `.txt`.

Jika folder `docker` belum ada, klik kanan folder proyek → **New Folder**, lalu beri nama `docker`. Klik kanan folder tersebut → **New File** untuk membuat empat file berikut:

- `entrypoint.sh`
- `php.ini`
- `fpm.conf`
- `nginx.conf`

Struktur yang harus terlihat:

```text
D:\Server\Tensura\
├── artisan                  ← sudah ada
├── composer.json            ← sudah ada
├── package.json             ← sudah ada
├── .env                     ← dari tahap 4
├── Dockerfile               ← buat pada tahap ini
├── .dockerignore            ← buat pada tahap ini
└── docker\
    ├── entrypoint.sh        ← buat pada tahap ini
    ├── php.ini              ← buat pada tahap ini
    ├── fpm.conf             ← buat pada tahap ini
    └── nginx.conf           ← buat pada tahap ini
```

Folder dan file proyek lainnya tetap ada; gambar hanya menampilkan bagian yang relevan. `secrets/postgres-password.txt` dibuat pada tahap 4. `compose.yaml` baru dibuat pada tahap 6, sedangkan token Tunnel baru diisi setelah Anda membuat Tunnel pada tahap 10.

### 5.3. Isi setiap file

Buka file yang namanya tercantum di atas setiap blok kode, lalu salin **seluruh isi blok kode** ke file tersebut. Jangan menyalin judul, penjelasan, atau tanda tiga backtick pembuka dan penutup blok. Simpan dengan `Ctrl + S`.

Contoh: bagian `docker/entrypoint.sh` berarti isi file `entrypoint.sh` di dalam folder `docker`. Isi file tersebut adalah teks konfigurasi; jangan menjalankan bloknya langsung di PowerShell.

| File | Kegunaan |
| --- | --- |
| `.dockerignore` | Menentukan berkas yang tidak dimasukkan ke proses build |
| `Dockerfile` | Instruksi menyiapkan PHP, LibreOffice, dependency, dan aset frontend |
| `docker/entrypoint.sh` | Perintah awal ketika container PHP dijalankan |
| `docker/php.ini` | Pengaturan PHP |
| `docker/fpm.conf` | Pengaturan proses PHP-FPM |
| `docker/nginx.conf` | Pengaturan web server Nginx |

### 5.3.1. Isi file `.dockerignore`

```dockerignore
.git
.env
.env.*
.pgdata
.codegraph
.agents
.codex
.aws
.claude
.ai
node_modules
vendor
storage
bootstrap/cache/*.php
bootstrap/ssr
public/build
public/hot
public/storage
resources/js/actions
resources/js/routes
resources/js/wayfinder
secrets
backups
*.dump
*.sql
codex-session-*.md
```

Ini mencegah database development, rahasia, cache lama, dan dependency Windows masuk image.

### 5.3.2. Isi file `Dockerfile`

```dockerfile
# syntax=docker/dockerfile:1

FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libreoffice-writer \
        fontconfig \
        fonts-liberation \
        fonts-crosextra-carlito \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j1 \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-tensura.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-tensura.conf

WORKDIR /var/www/html

FROM php-base AS build

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:24-bookworm-slim /usr/local/ /usr/local/

ENV APP_ENV=production \
    APP_DEBUG=false \
    INERTIA_SSR_ENABLED=false \
    NODE_OPTIONS=--max-old-space-size=1536

COPY . .

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && COMPOSER_MEMORY_LIMIT=-1 composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --optimize-autoloader \
    && composer check-platform-reqs --no-dev

RUN npm install --global pnpm@12.15.0 \
    && pnpm install --frozen-lockfile \
    && pnpm run build \
    && ln -s /var/www/html/storage/app/public public/storage

FROM php-base AS runtime

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/tensura-entrypoint

RUN chmod +x /usr/local/bin/tensura-entrypoint

USER www-data

ENTRYPOINT ["/usr/local/bin/tensura-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:stable-alpine AS web

WORKDIR /var/www/html

COPY --from=build /var/www/html/public /var/www/html/public
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 8080
```

Versi pnpm mengikuti lingkungan proyek saat pemeriksaan; dependency aplikasi mengikuti lockfile.

Stage build memerlukan PHP karena plugin Wayfinder pada `vite.config.ts` menjalankan `php artisan wayfinder:generate`. Stage Node murni tanpa PHP dan Composer dependencies dapat gagal pada proyek ini.

Runtime tidak membawa Node atau pnpm. LibreOffice dan Fontconfig tersedia dalam container PHP untuk pemeriksaan font serta konversi PDF. Ekstensi memakai helper image PHP resmi.

Referensi: [Image PHP](https://hub.docker.com/_/php).

### 5.3.3. Isi file `docker/entrypoint.sh`

```sh
#!/bin/sh
set -eu

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

exec "$@"
```

Cache konfigurasi dibuat saat container berjalan setelah menerima environment deployment.

### 5.3.4. Isi file `docker/php.ini`

```ini
memory_limit=256M
upload_max_filesize=20M
post_max_size=25M
max_execution_time=90

date.timezone=Asia/Makassar

expose_php=Off
display_errors=Off
log_errors=On
error_log=/proc/self/fd/2

opcache.enable=1
opcache.memory_consumption=64
opcache.validate_timestamps=0

output_buffering=Off
zlib.output_compression=Off
```

### 5.3.5. Isi file `docker/fpm.conf`

```ini
[www]
listen = 9000

pm = ondemand
pm.max_children = 10
pm.process_idle_timeout = 10s
pm.max_requests = 200

request_terminate_timeout = 110s

clear_env = no
catch_workers_output = yes
```

Batas 10 proses memberi ruang untuk koneksi SSE dan permintaan biasa, tetapi tetap perlu diukur. Satu koneksi SSE aktif menahan satu proses PHP-FPM selama stream berjalan.

### 5.3.6. Isi file `docker/nginx.conf`

```nginx
server {
    listen 8080;
    server_name _;

    root /var/www/html/public;
    index index.php;

    server_tokens off;
    client_max_body_size 25m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        include fastcgi_params;

        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param DOCUMENT_ROOT $document_root;
        fastcgi_param HTTP_PROXY "";

        fastcgi_buffering off;
        fastcgi_read_timeout 120s;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Root web hanya menunjuk ke `public`. Buffering FastCGI dinonaktifkan agar event SSE dapat dikirim segera.

Referensi: [Deployment Laravel](https://laravel.com/docs/13.x/deployment), [FastCGI Nginx](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html).

### 5.4. Atur format penyimpanan di VS Code

Untuk setiap file yang baru dibuat:

1. Buka file di editor.
2. Lihat status bar kanan bawah. Jika tertulis **CRLF**, klik tulisan tersebut lalu pilih **LF**.
3. Jika encoding tertulis **UTF-8 with BOM**, klik tulisan tersebut → **Save with Encoding → UTF-8**. Jika sudah **UTF-8**, biarkan.
4. Simpan dengan `Ctrl + S`.

Format LF sangat penting untuk `entrypoint.sh` agar dapat dijalankan dalam container Linux. Gunakan UTF-8 tanpa BOM untuk seluruh file tersebut.

### 5.5. Periksa sebelum lanjut

- Keenam file berada pada lokasi sesuai struktur di atas.
- Nama file tidak mendapat tambahan `.txt`.
- Setiap file berisi blok kode yang sesuai dan sudah disimpan.
- Format penyimpanan adalah LF dan UTF-8 tanpa BOM.

Setelah pemeriksaan ini, tahap 5 selesai. Lanjutkan ke tahap 6 untuk membuat `compose.yaml`; belum perlu menjalankan perintah Docker pada tahap 5.

## 6. Buat Compose

Isi `compose.yaml`:

```yaml
name: tensura

x-logging: &logging
  driver: json-file
  options:
    max-size: "10m"
    max-file: "3"

x-php: &php
  image: tensura-app:local
  env_file:
    - .env
  user: www-data
  init: true
  restart: unless-stopped
  stop_grace_period: 120s
  volumes:
    - app-storage:/var/www/html/storage
  depends_on:
    db:
      condition: service_healthy
  logging: *logging

services:
  db:
    image: postgres:18-bookworm
    restart: unless-stopped
    environment:
      POSTGRES_DB: postgres
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD_FILE: /run/secrets/postgres_password
      TZ: Asia/Makassar
    secrets:
      - postgres_password
    command:
      - postgres
      - -c
      - shared_buffers=64MB
      - -c
      - max_connections=40
      - -c
      - work_mem=4MB
    volumes:
      - pg-data:/var/lib/postgresql
    shm_size: 128m
    mem_limit: 384m
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres -d postgres"]
      interval: 5s
      timeout: 5s
      retries: 20
      start_period: 20s
    logging: *logging

  app:
    <<: *php
    build:
      context: .
      target: runtime
    command: ["php-fpm", "-F"]
    mem_limit: 1024m

  web:
    image: tensura-web:local
    build:
      context: .
      target: web
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:8080"
    volumes:
      - app-storage:/var/www/html/storage:ro
    depends_on:
      - app
    mem_limit: 64m
    logging: *logging

  worker:
    <<: *php
    command:
      - php
      - artisan
      - queue:work
      - letters
      - --queue=letters
      - --sleep=3
      - --tries=3
      - --timeout=90
      - --memory=192
      - --max-time=3600
      - --no-interaction
    mem_limit: 768m

  scheduler:
    <<: *php
    command:
      - php
      - artisan
      - schedule:work
      - --no-interaction
    mem_limit: 192m

  cloudflared:
    image: cloudflare/cloudflared:latest
    profiles: ["public"]
    restart: unless-stopped
    command:
      - tunnel
      - --no-autoupdate
      - run
      - --token-file
      - /run/secrets/tunnel_token
    secrets:
      - tunnel_token
    depends_on:
      - web
    mem_limit: 128m
    logging: *logging

volumes:
  pg-data:
    name: tensura-pg-data
  app-storage:
    name: tensura-app-storage

secrets:
  postgres_password:
    file: ./secrets/postgres-password.txt
  tunnel_token:
    file: ./secrets/tunnel-token.txt
```

Penjelasan:

- Database dan PHP-FPM tidak memiliki port host.
- Web terikat ke loopback Windows, sehingga tidak terbuka langsung ke LAN.
- `cloudflared` mengakses web melalui jaringan Docker.
- Seluruh proses PHP berbagi volume `storage`.
- Satu worker memproses konversi surat bergantian.
- `restart: unless-stopped` menghidupkan kembali container yang keluar selama engine berjalan.
- `--memory=192` memicu daur ulang worker PHP di antara pekerjaan; batas container juga mencakup proses LibreOffice.

Khusus image **PostgreSQL 18**, volume dipasang ke `/var/lib/postgresql`. Data berada di direktori versi-spesifik di dalamnya.

Referensi: [Image PostgreSQL](https://hub.docker.com/_/postgres).

`depends_on` dengan `service_healthy` menunggu database siap saat dijalankan melalui Compose. Setelah reboot engine, layanan tetap harus mampu pulih dari koneksi database yang belum siap.

Referensi: [Urutan startup Compose](https://docs.docker.com/compose/how-tos/startup-order/).

Untuk penggunaan berkelanjutan, catat dan pin digest image yang sudah berhasil diuji, terutama `cloudflared:latest`, agar pembaruan terkendali.

## 7. Build image dan inisialisasi database

Buat file token kosong terlebih dahulu:

```powershell
New-Item -ItemType File -Force .\secrets\tunnel-token.txt
```

Jangan ulangi perintah tersebut setelah token sudah diisi.

Periksa Compose tanpa menampilkan rahasia:

```powershell
docker compose config --quiet
```

Build berurutan:

```powershell
docker compose --parallel 1 build app web
```

Build pertama dapat cukup lama karena memasang ekstensi PHP, LibreOffice, dan dependency frontend. Tutup aplikasi berat selama proses ini.

### Buat APP_KEY untuk instalasi baru

Jalankan perintah berikut langsung di **PowerShell Windows**, menggunakan PHP yang sudah terpasang:

```powershell
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Perintah menghasilkan key acak 32 byte untuk cipher `AES-256-CBC` yang dipakai proyek. Salin seluruh hasilnya, termasuk awalan `base64:`, ke file **`D:\Server\Tensura\.env`**:

```dotenv
APP_KEY=base64:HASIL_DARI_PERINTAH_TADI
```

`HASIL_DARI_PERINTAH_TADI` adalah penanda contoh; gunakan hasil asli perintah. Simpan key sebagai rahasia dan jangan membuat ulang key setiap container dimulai.

**Jika memindahkan aplikasi beserta data lama, pertahankan `APP_KEY` lama.** Menggantinya dapat membuat data terenkripsi lama tidak terbaca.

### Jika key:generate gagal karena .env tidak ditemukan

Contoh error:

```text
file_get_contents(.../.env): Failed to open stream: No such file or directory
```

Pada deployment ini, `.env` berada di folder proyek Windows. `.dockerignore` mengecualikannya dari image, sedangkan `env_file` pada Compose meneruskan nilainya sebagai environment variable. `env_file` tidak membuat salinan file `.env` di dalam container.

`php artisan key:generate` tanpa `--show` mencoba membaca dan menulis `.env`, sehingga dapat gagal di dalam container ini. Gunakan perintah `php -r` di atas, lalu isi `APP_KEY` pada `.env` Windows. Tidak perlu memasukkan rahasia ke Dockerfile atau menyalin `.env` ke image.

Pada Laravel 13 yang diperiksa dalam proyek, opsi `key:generate --show` hanya menampilkan key dan tidak membaca atau menulis `.env`. Jika error tetap muncul dengan opsi tersebut, simpan perintah serta error lengkap untuk ditelusuri; jangan menganggap semua penggunaan `--show` membutuhkan file `.env`.

Jika container belum berjalan, lanjutkan inisialisasi di bawah setelah menyimpan `.env`. Jika layanan aplikasi sudah berjalan, terapkan perubahan environment dengan:

```powershell
docker compose up -d --force-recreate app web worker scheduler
```

Recreate membaca ulang `.env`, dan entrypoint membangun ulang cache konfigurasi. Restart biasa tidak memperbarui environment container yang sudah dibuat. Tidak perlu build ulang image jika hanya nilai `.env` yang berubah.

### Lanjutkan inisialisasi volume dan database

Siapkan izin volume:

```powershell
docker compose run --rm --no-deps --user root --entrypoint sh app -c 'mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs && chown -R www-data:www-data storage'
```

Jalankan database:

```powershell
docker compose up -d db
docker compose ps
```

Setelah database `healthy`, buat akun aplikasi:

```powershell
docker compose exec db createuser -U postgres --login --pwprompt --no-superuser --no-createdb --no-createrole tensura
```

Masukkan password yang sama dengan **`DB_PASSWORD`**, bukan password administrator PostgreSQL.

Buat database:

```powershell
docker compose exec db createdb -U postgres --owner=tensura tensura
```

Akun aplikasi memiliki database untuk migration tanpa menjadi superuser PostgreSQL.

Referensi: [`createuser`](https://www.postgresql.org/docs/18/app-createuser.html).

### Pilih instalasi kosong atau pemindahan data

- **Instalasi kosong:** lanjutkan ke migration.
- **Memindahkan data lama:** restore dump ke database kosong terlebih dahulu, lalu jalankan migration.

Contoh restore dump yang dibawa dari komputer sumber:

```powershell
docker compose cp .\backups\tensura-sumber.dump db:/tmp/tensura-sumber.dump

docker compose exec -T db pg_restore -U postgres --no-owner --role=tensura --exit-on-error -d tensura /tmp/tensura-sumber.dump
```

Dump sumber dibuat memakai `pg_dump` yang kompatibel dengan PostgreSQL 18. PostgreSQL Client tidak perlu dipasang di Windows tujuan.

Bawa juga seluruh **`storage/app`** dari sumber. Jika salinannya berada di `D:\Migrasi\storage-app`:

```powershell
docker compose run --rm --no-deps --user root --entrypoint sh --volume "D:/Migrasi/storage-app:/restore:ro" app -c 'cp -a /restore/. storage/app/ && chown -R www-data:www-data storage'
```

Lakukan perpindahan ketika sumber berhenti menerima perubahan dan pekerjaan surat yang sedang berjalan sudah selesai. Jangan menyalin `.pgdata` development sebagai pengganti dump.

### Jalankan migration

```powershell
docker compose run --rm app php artisan migrate --force --no-interaction
```

Versi proyek saat pemeriksaan memiliki migration retirement terpisah. Periksa:

```powershell
docker compose run --rm app php artisan letters:retire-counters --check --no-interaction
```

Jika berhasil:

```powershell
docker compose run --rm app php artisan letters:retire-counters --no-interaction
```

Jika database lama melaporkan klasifikasi belum siap, siapkan periode angka lamanya sesuai pesan aplikasi. Jangan memaksa penghapusan kolom. Perubahan skema retirement tidak dapat di-rollback biasa.

## 8. Siapkan akun dan jalankan layanan lokal

Pada database baru, isi master awal secara spesifik:

```powershell
docker compose run --rm app php artisan db:seed --class=JobTitleSeeder --force --no-interaction

docker compose run --rm app php artisan db:seed --class=ReferenceGroupSeeder --force --no-interaction

docker compose run --rm app php artisan db:seed --class=ReferenceItemSeeder --force --no-interaction
```

Lewati seeding jika sudah memulihkan data yang diperlukan.

**Jangan menjalankan `DatabaseSeeder` atau `UserSeeder` apa adanya.** `UserSeeder` dapat membuat atau memperbarui akun bawaan dengan password `password`.

Untuk membuat akun pengelola pada database baru:

```powershell
docker compose run --rm app php artisan tinker
```

Masukkan dalam Tinker:

```php
$password = \Laravel\Prompts\password('Password akun pengelola');

\App\Models\MasterData\User::create([
    'name' => 'Pengelola Tensura',
    'username' => 'pengelola',
    'email' => 'pengelola@contoh.com',
    'password' => $password,
]);

unset($password);
```

Model sudah memiliki cast `hashed` untuk password. Ulangi dengan username dan email berbeda untuk anggota lain, lalu keluar:

```php
exit
```

Jika menggunakan database lama, pertahankan akun yang ada dan ganti password bawaan jika masih dipakai.

Jalankan layanan lokal:

```powershell
docker compose up -d app web worker scheduler

docker compose ps
docker compose logs --tail=100 app web worker scheduler
```

Buka pada komputer server:

```text
http://localhost:8080
```

Login Tensura menggunakan username dan password aplikasi.

Periksa scheduler dan kesehatan surat:

```powershell
docker compose exec app php artisan schedule:list --no-interaction

docker compose exec app php artisan letters:health --no-interaction
```

Jika heartbeat scheduler belum muncul, tunggu melewati satu pergantian menit lalu ulangi.

Uji:

- Dashboard dan data.
- Upload templat DOCX.
- Preview surat.
- Pembuatan dan unduh PDF.
- Perubahan status tanpa refresh manual.
- Avatar dan berkas lain yang diperlukan.

Jika font belum tersedia, unggah melalui pengelolaan font aplikasi sesuai lisensinya. Font Windows host tidak otomatis tersedia dalam container Linux.

Timeout worker **90 detik** mengikuti proyek dan lebih pendek daripada `retry_after` connection `letters`, yaitu **120 detik**. Pertahankan hubungan tersebut untuk mengurangi risiko pekerjaan diproses bersamaan setelah reservation berakhir.

Referensi: [Timeout queue Laravel](https://laravel.com/docs/13.x/queues#job-expirations-and-timeouts).

## 9. Pilih Tunnel saja atau Tunnel dengan Cloudflare Access

### Pilihan A: Tunnel saja, memakai login Tensura

Cloudflare Tunnel dapat memublikasikan aplikasi tanpa Cloudflare Access. Pengunjung langsung membuka halaman login Tensura, tanpa langkah PIN email Cloudflare. Akun Cloudflare dan domain pada langkah 3 tetap diperlukan untuk named tunnel dengan hostname publik tetap.

Untuk pilihan ini:

1. Lewati pembuatan identity provider, aplikasi Access, dan policy email pada pilihan B.
2. Lanjutkan ke langkah 10 untuk membuat Tunnel dan route publik.
3. Biarkan **Protect with Access** nonaktif pada route Tunnel.
4. Jika sebelumnya sudah membuat aplikasi Access untuk hostname yang sama, hapus aplikasi Access tersebut setelah memastikan Anda memang ingin melepas pembatasan email. Mematikan Protect with Access saja tidak menghapus policy Access pada sisi Cloudflare.
5. Pada pengujian langkah 11, pastikan browser incognito langsung melihat login Tensura dan halaman yang memerlukan autentikasi tetap meminta login.

Dengan Tunnel saja, siapa pun di Internet dapat menjangkau halaman login dan resource publik aplikasi. Login dan otorisasi Tensura menjadi pembatas utama untuk data yang dilindungi. Gunakan password unik yang kuat, hindari akun bawaan, dan pastikan pembatasan percobaan login tersedia sebelum penggunaan publik. Panduan ini tidak menambahkan pembatasan percobaan login pada kode aplikasi.

Konfigurasi Docker, HTTPS, worker, scheduler, dan dukungan SSE tetap sama. Untuk tim yang menginginkan pembatasan berdasarkan daftar email sebelum mencapai aplikasi, gunakan pilihan B.

Referensi: [Publikasi aplikasi melalui Tunnel](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/routing-to-tunnel/).

### Pilihan B: Tunnel dengan Cloudflare Access

Ikuti bagian ini jika ingin menambahkan pemeriksaan email sebelum pengunjung mencapai Tensura. Buat Access sebelum memublikasikan route Tunnel.

Buat organisasi **Cloudflare Zero Trust/Cloudflare One** pada akun yang sama. Pilih paket Free yang sesuai. Saat pemeriksaan, Cloudflare menyediakan penggunaan gratis hingga 50 pengguna.

Referensi: [Paket Cloudflare One](https://blog.cloudflare.com/modernize-agile-sase/).

### Aktifkan login email

1. Buka **Zero Trust → Integrations → Identity providers**.
2. Pilih **Add new identity provider**.
3. Pilih **One-time PIN**.

Pengiriman PIN dilakukan Cloudflare. `MAIL_MAILER=log` pada Laravel tidak menghalanginya.

Referensi: [One-time PIN](https://developers.cloudflare.com/cloudflare-one/integrations/identity-providers/one-time-pin/).

### Buat aplikasi Access

1. Buka **Zero Trust → Access controls → Applications**.
2. Pilih **Create new application**.
3. Pilih **Self-hosted and private**.
4. Pilih **Add public hostname**.
5. Gunakan `tensura.organisasi-anda.com`.
6. Lindungi seluruh hostname; biarkan pembatasan path kosong.
7. Beri nama `Tensura Internal`.
8. Pilih durasi sesi, misalnya 8 jam.
9. Pilih One-time PIN sebagai metode login.

Buat policy:

| Pengaturan | Nilai |
| --- | --- |
| Nama | Tim Tensura |
| Action | Allow |
| Include selector | Emails |
| Nilai | Email lengkap masing-masing anggota tim |

Masukkan alamat email 3–7 orang secara spesifik. Hindari seluruh `gmail.com`, `outlook.com`, atau rule `Everyone`.

Access menggunakan **deny by default**. Orang yang tidak memenuhi policy Allow ditolak. Tidak perlu policy Block Everyone, dan jangan tambahkan policy Bypass untuk aplikasi ini.

Simpan dan catat **Application Audience/AUD** jika diperlukan untuk Tunnel.

Cloudflare menyarankan Access dibuat sebelum route Tunnel agar hostname tidak sempat terbuka tanpa perlindungan.

Referensi: [Aplikasi Access publik](https://developers.cloudflare.com/cloudflare-one/access-controls/applications/http-apps/self-hosted-public-app/).

Anggota tim melewati dua login:

```text
Cloudflare Access: email dan PIN
          ↓
Tensura: username dan password
```

Proyek saat ini belum mengintegrasikan autentikasi Access sebagai SSO Laravel.

## 10. Konfigurasikan HTTPS dan named tunnel

### Trusted proxy Laravel

Tambahkan di dalam closure `withMiddleware` yang sudah ada pada `bootstrap/app.php`:

```php
$middleware->trustProxies(
    at: '*',
    headers: Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PROTO,
);
```

File tersebut sudah mengimpor `Illuminate\Http\Request` saat pemeriksaan. Pertahankan middleware lainnya.

Laravel perlu mengenali HTTPS pengunjung meskipun koneksi internal cloudflared ke Nginx memakai HTTP. `at: '*'` bergantung pada topologi panduan ini: origin hanya dijangkau melalui jaringan Docker dan loopback Windows. Jangan membuka origin langsung ke LAN atau Internet dengan konfigurasi kepercayaan tersebut.

Referensi: [Trusted proxies Laravel](https://laravel.com/docs/13.x/requests#configuring-trusted-proxies).

Ubah `.env`:

```dotenv
APP_URL=https://tensura.organisasi-anda.com
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null
```

Build ulang dan recreate layanan:

```powershell
docker compose --parallel 1 build app web

docker compose up -d --force-recreate app web worker scheduler
```

Selanjutnya gunakan hostname HTTPS untuk login. Cookie secure tidak dikirim melalui HTTP lokal biasa.

### Buat Tunnel

1. Buka dashboard Cloudflare → **Networking → Tunnels**.
2. Pilih **Create a tunnel**.
3. Pilih connector `cloudflared` jika diminta.
4. Beri nama `tensura-acer`.
5. Pilih petunjuk instalasi Docker.
6. Salin token Tunnel dari perintah yang ditampilkan.
7. Simpan hanya nilai token ke `D:\Server\Tensura\secrets\tunnel-token.txt`, satu baris UTF-8 tanpa BOM.

Token tersebut bukan API key akun atau Tunnel UUID.

```powershell
docker compose --profile public up -d cloudflared

docker compose logs --tail=100 cloudflared
```

`--token-file` didukung untuk remotely managed tunnel dan menghindari penulisan token langsung dalam Compose.

Referensi: [Parameter token Tunnel](https://developers.cloudflare.com/tunnel/reference/run-parameters/).

### Tambahkan route publik

Tunggu connector **Healthy**, lalu tambahkan **Published application route**:

| Pengaturan | Nilai |
| --- | --- |
| Subdomain | tensura |
| Domain | Domain Anda |
| Path | Kosong |
| Service type | HTTP |
| Service URL | web:8080 |

Jika kolom mengharapkan URL lengkap, gunakan `http://web:8080`.

**Jangan gunakan `localhost:8080` sebagai origin cloudflared dalam Docker.** Alamat itu menunjuk container cloudflared sendiri.

Sesuaikan pengaturan origin dengan pilihan pada langkah 9:

- **Tunnel saja:** biarkan **Protect with Access** nonaktif. Tidak perlu memilih aplikasi Access atau mengisi AUD. Pastikan tidak ada aplikasi Access yang masih melindungi hostname ini pada sisi Cloudflare.
- **Tunnel dengan Access:** aktifkan **Protect with Access**. Pilih organisasi dan aplikasi yang sesuai; isikan AUD jika diminta. Dengan demikian cloudflared memvalidasi token Access sebelum meneruskan permintaan.

Referensi: [Protect with Access](https://developers.cloudflare.com/tunnel/reference/origin-parameters/).

Simpan route. Dashboard mengatur DNS hostname ke Tunnel. Selesaikan konflik jika ada record lama dengan nama sama.

Browser memakai HTTPS, Tunnel terenkripsi, dan HTTP origin berada di jaringan Docker lokal. Topologi ini tidak memerlukan port forwarding atau sertifikat publik pada Nginx.

Referensi: [Pembuatan Tunnel](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/get-started/create-remote-tunnel/).

## 11. Uji akses Internet

Gunakan ponsel dengan data seluler dan buka:

```text
https://tensura.organisasi-anda.com
```

| Pengujian | Hasil yang diharapkan |
| --- | --- |
| Browser incognito, Tunnel saja | Langsung tampil halaman login Tensura |
| Halaman yang memerlukan login, Tunnel saja | Pengunjung tanpa sesi diarahkan ke login Tensura |
| Browser incognito, dengan Access | Halaman Cloudflare Access |
| Email anggota tim, dengan Access | Autentikasi berhasil |
| Email di luar daftar, dengan Access | Akses ditolak |
| Setelah Access, jika digunakan | Halaman login Tensura |
| Login aplikasi | Dashboard dan fitur bekerja |
| Preview/PDF | Pekerjaan selesai dan berkas dapat diunduh |
| Status operasi | SSE bekerja tanpa refresh manual |
| Restart container | Data dan dokumen tetap ada |

Uji juga tautan langsung dokumen, aset frontend, dan fungsi yang memerlukan worker.

Untuk tahap awal, buat **Cache Rule → Bypass cache** bagi hostname Tensura agar aturan caching tambahan tidak menyimpan respons dinamis.

```powershell
docker compose ps

docker compose exec app php artisan letters:health --no-interaction

docker stats
```

Pantau RAM Windows melalui Task Manager. Batasi dahulu satu tab aktif per pengguna ketika menguji beban serentak.

## 12. Backup database dan dokumen

Named volume mempertahankan data ketika container dibuat ulang, tetapi bukan pengganti backup. Simpan salinan di luar komputer server.

Untuk backup sederhana yang konsisten antara database dan dokumen, lakukan pada waktu pemakaian rendah:

```powershell
docker compose exec app php artisan down --no-interaction

docker compose stop worker scheduler

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
```

Dump ke file dalam container, lalu salin keluar:

```powershell
docker compose exec -T db pg_dump -U postgres -d tensura -Fc -f "/tmp/tensura-$stamp.dump"

docker compose cp "db:/tmp/tensura-$stamp.dump" ".\backups\tensura-$stamp.dump"
```

Cara ini menghindari masalah pengalihan output biner pada versi PowerShell tertentu.

Backup `storage/app`:

```powershell
docker compose exec app tar -czf "/tmp/storage-$stamp.tgz" -C /var/www/html/storage app

docker compose cp "app:/tmp/storage-$stamp.tgz" ".\backups\storage-$stamp.tgz"
```

Hidupkan layanan:

```powershell
docker compose start worker scheduler

docker compose exec app php artisan up --no-interaction
```

Jika backup gagal, periksa penyebabnya dan pastikan aplikasi keluar dari maintenance setelah penanganan.

Backup mencakup:

- Dump database.
- `storage/app`, termasuk templat, font, dan dokumen resmi.
- `.env`, terutama `APP_KEY`, dalam penyimpanan rahasia yang terlindungi.
- Versi source dan konfigurasi deployment yang sesuai.

Format `pg_dump -Fc` dipulihkan memakai `pg_restore`. Dump satu database tidak menyertakan seluruh role cluster; buat kembali akun mengikuti langkah 7.

Referensi: [`pg_dump`](https://www.postgresql.org/docs/18/app-pgdump.html).

Uji restore ke database terpisah secara berkala. Setelah berhasil, atur backup rutin melalui Windows Task Scheduler saat Docker Desktop berjalan. Bersihkan file sementara `/tmp` setelah salinannya diverifikasi agar tidak menumpuk.

Hindari perintah berikut tanpa memahami dan membackup volume yang akan dihapus:

```text
docker compose down -v
docker system prune --volumes
```

## 13. Operasional harian, performa, dan pemecahan masalah

### Menjaga server tersedia

- Atur Windows **System → Power → Sleep: Never** saat tersambung listrik.
- Layar boleh mati; komputer harus tetap aktif.
- Kunci layar menggunakan `Win + L`.
- Ethernet, router, dan Internet harus tetap menyala.
- Jadwalkan restart Windows Update pada waktu yang disepakati.
- Setelah reboot, login Windows dan pastikan Docker Desktop berjalan.

Startup Docker Desktop bekerja saat pengguna sign in. `restart: unless-stopped` berlaku setelah engine hidup; ini tidak menjamin aplikasi tersedia sebelum login Windows.

Referensi: [Pengaturan Docker Desktop](https://docs.docker.com/desktop/settings-and-maintenance/settings/).

Menghidupkan seluruh stack:

```powershell
docker compose --profile public up -d
```

Menghentikan sementara:

```powershell
docker compose --profile public stop
```

Menutup PowerShell tidak menghentikan container yang berjalan dengan `-d`.

### Pemeriksaan utama

```powershell
docker compose ps

docker compose logs --tail=100 app web worker scheduler cloudflared

docker compose exec app php artisan queue:failed --no-interaction

docker compose exec app php artisan letters:health --no-interaction

docker stats

docker system df
```

### Masalah umum

| Gejala | Pemeriksaan dan tindakan |
| --- | --- |
| Docker gagal mulai | Periksa virtualisasi BIOS, versi WSL, update WSL, dan restart Windows |
| Tidak dapat menghubungi daemon | Tunggu engine Docker Desktop siap |
| Build kehabisan memori/exit 137 | Tutup aplikasi berat, build berurutan, hentikan stack saat build, atau build di komputer lain |
| `catalog:` tidak dikenali | Gunakan pnpm dan lockfile proyek; jangan mengganti dengan `npm install` |
| Wayfinder gagal saat build | Pastikan stage build memiliki PHP dan Composer dependencies |
| Vite manifest tidak ditemukan | Periksa build frontend dan `public/build/manifest.json`; build ulang kedua image |
| Browser mengakses port 5173 | Periksa `public/hot` atau HTML development lama |
| Database tidak terhubung | Periksa `DB_HOST=db`, port 5432, password role, dan health database |
| `key:generate` gagal membaca `.env` | Buat key melalui `php -r` pada langkah 7, simpan di `.env` Windows, lalu recreate layanan PHP jika sudah berjalan |
| Password baru PostgreSQL tidak berlaku | Environment inisialisasi berlaku pada volume kosong; ubah password role secara benar dan sesuaikan `.env` |
| Storage permission denied | Perbaiki ownership volume menjadi `www-data:www-data` |
| Queue surat diam | Worker harus menjalankan `queue:work letters --queue=letters` |
| Scheduler tidak sehat | Periksa container, `schedule:list`, dan heartbeat setelah satu menit |
| PDF gagal | Periksa LibreOffice, Fontconfig, font fallback, log worker, dan `letters:health` |
| Status surat tidak berubah | Periksa SSE, buffering Nginx, named tunnel, dan koneksi PostgreSQL langsung |
| HTTP/HTTPS bercampur | Periksa `APP_URL`, trusted proxy, dan recreate container |
| Login 419 | Periksa secure cookie, hostname, session database, dan hapus cookie lama |
| Cloudflare 1033 | Connector tidak tersambung; periksa Docker, Internet, token, dan log |
| Cloudflare 502 | Periksa origin `http://web:8080`, container web, dan PHP-FPM |
| PIN tidak datang | Periksa spam, email pada policy, serta One-time PIN |
| Access lolos tetapi login aplikasi gagal | Periksa akun Laravel; kedua autentikasi terpisah |

Jika firewall membatasi Tunnel, periksa akses **keluar** port **7844** untuk UDP/QUIC atau TCP/HTTP2. Jika UDP diblokir, coba menambahkan `--protocol http2` sebelum `run` pada command cloudflared. Pertahankan Windows Firewall aktif.

Referensi: [Tunnel dan firewall](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/configure-tunnels/tunnel-with-firewall/).

### Memperbarui aplikasi

1. Backup data.
2. Masuk maintenance dan hentikan worker serta scheduler.
3. Ambil source versi yang ingin dipasang.
4. Build ulang `app` dan `web`.
5. Jalankan migration yang diperlukan memakai image baru.
6. Recreate `app`, `web`, `worker`, dan `scheduler`.
7. Keluar maintenance dan ulangi pengujian.

Recreate web ketika app diganti agar upstream Nginx memakai alamat container yang berlaku. Setelah `.env` berubah, recreate layanan PHP untuk menerima environment baru.

### Contoh lengkap: mengubah satu file enum PHP

Contoh ini untuk perubahan kode PHP yang tidak memerlukan perubahan skema atau penyesuaian data database. Jika perubahan nilai enum memengaruhi data yang sudah tersimpan, siapkan penyesuaian data dan backup terlebih dahulu; build image tidak mengubah data database secara otomatis.

Pada konfigurasi ini, `COPY . .` dalam Dockerfile menyalin source ke image saat build. Mengubah file di Windows tidak otomatis mengubah file di container. Restart menjalankan ulang container dengan kode yang sama, sedangkan force-recreate tanpa build ulang masih memakai image lama.

Ikuti langkah berikut satu per satu. Contoh lokasi proyek adalah `D:\Server\Tensura` dan contoh file adalah `app\Enums\JobTitleGroupEnum.php`. Sesuaikan jika lokasi atau file Anda berbeda.

1. Buka Docker Desktop dan tunggu engine siap.
2. Buka PowerShell dan masuk ke folder proyek:

```powershell
Set-Location D:\Server\Tensura
```

3. Pastikan folder benar dengan memeriksa tiga file berikut:

```powershell
Get-ChildItem compose.yaml, Dockerfile, artisan
```

Ketiganya harus muncul. Jika tidak, periksa kembali lokasi folder proyek.

4. Buka file enum melalui VS Code, misalnya `D:\Server\Tensura\app\Enums\JobTitleGroupEnum.php`. Lakukan perubahan dan simpan menggunakan `Ctrl + S`.
5. Build ulang image aplikasi:

```powershell
docker compose build app
```

Tunggu sampai selesai. Jika muncul error, selesaikan error sebelum melanjutkan. Docker dapat memakai cache yang masih valid, tetapi pemasangan dependency Composer dan pnpm dapat dijalankan ulang karena susunan Dockerfile saat ini.

6. Setelah build berhasil, buat ulang layanan menggunakan image terbaru:

```powershell
docker compose up -d --no-build --force-recreate app worker scheduler web
```

| Bagian perintah | Arti |
| --- | --- |
| `up` | Menjalankan layanan |
| `-d` | Berjalan di latar belakang |
| `--no-build` | Memakai hasil build langkah sebelumnya |
| `--force-recreate` | Membuat ulang container layanan yang disebutkan |
| `app` | PHP yang menjalankan aplikasi |
| `worker` | Pemroses antrean surat dan PDF |
| `scheduler` | Pelaksana tugas terjadwal |
| `web` | Nginx yang menerima permintaan web |

Database dan dokumen tetap berada pada volume yang sama. Tidak perlu menjalankan `down`, menghapus volume, atau membuat database kembali. Pergantian container dapat memutus akses sebentar; lakukan ketika tim sedang tidak aktif.

Worker dan scheduler juga memakai image aplikasi, sehingga keduanya harus menerima perubahan kode. Recreate web menyegarkan koneksi Nginx ke container app. Container PHP baru memulai OPcache baru; ini penting karena konfigurasi memakai `opcache.validate_timestamps=0`.

7. Periksa status layanan:

```powershell
docker compose ps -a
```

`app`, `web`, `worker`, dan `scheduler` harus berjalan, bukan terus-menerus `Restarting` atau `Exited`.

8. Periksa log:

```powershell
docker compose logs --tail=50 app web worker scheduler
```

9. Pastikan perubahan file benar-benar masuk ke container:

```powershell
docker compose exec app cat app/Enums/JobTitleGroupEnum.php
```

Perintah tersebut hanya menampilkan file di dalam container. Cocokkan dengan perubahan Anda. Jika masih lama, pastikan file yang diedit berada pada folder proyek yang sama dengan tempat menjalankan build, serta pastikan build dan recreate berhasil.

10. Buka alamat aplikasi yang biasa dipakai, uji bagian yang berubah, dan periksa kesehatan surat:

```powershell
docker compose exec app php artisan letters:health --no-interaction
```

### Perintah singkat untuk pembaruan berikutnya

Setelah menyimpan perubahan PHP yang tidak memerlukan migration, jalankan:

```powershell
Set-Location D:\Server\Tensura
docker compose build app
```

**Hanya setelah build berhasil**, lanjutkan:

```powershell
docker compose up -d --no-build --force-recreate app worker scheduler web
docker compose ps -a
```

Jika mengubah frontend seperti halaman React atau CSS, gunakan build kedua image:

```powershell
docker compose --parallel 1 build app web
```

Setelah build berhasil, jalankan perintah recreate yang sama.

| Jenis perubahan | Tindakan |
| --- | --- |
| Enum, controller, atau kode PHP tanpa perubahan skema/data | Build `app`, lalu recreate `app`, `worker`, `scheduler`, dan `web` |
| Frontend atau hasil build aset | Build `app web`, lalu recreate keempat layanan tersebut |
| Nilai `.env` | Recreate layanan; tidak perlu build image |
| Perubahan skema atau nilai enum yang memerlukan penyesuaian data | Ikuti prosedur backup dan maintenance, jalankan migration atau penyesuaian data yang disiapkan, lalu recreate layanan |
| Proses macet tanpa perubahan kode atau environment | Restart layanan terkait dapat mencukupi |

### Performa pada RAM 8 GB

- Pertahankan satu worker dahulu.
- Hindari Redis, Horizon, SSR, dan Vite dev server tambahan selama belum diperlukan.
- Ukur beban saat impor Excel dan konversi PDF.
- Batas container tidak menjamin total RAM cukup: Windows, VM, engine, dan cache membutuhkan memori.
- Jika swap terus aktif atau container sering kehabisan memori, kurangi beban dan prioritaskan peningkatan RAM.
- Ethernet 100 Mbps adalah link lokal; kecepatan unggah Internet server menentukan pengalaman unduh pengguna.
- Pertahankan ruang kosong pada C: untuk kebutuhan Windows, serta pantau pertumbuhan disk Docker di D:.

## 14. Alternatif tanpa domain

| Pilihan | Domain sendiri | Bentuk akses | Kesesuaian |
| --- | --- | --- | --- |
| Named tunnel + Access hostname publik | Diperlukan untuk jalur panduan ini | Browser biasa, URL tetap | Pilihan utama |
| Quick Tunnel | Tidak diperlukan | URL sementara `trycloudflare.com` | Uji singkat; SSE tidak didukung |
| Jaringan privat Cloudflare One | Bisa tanpa domain publik sendiri | Umumnya client dan konfigurasi jaringan privat | Alternatif jika tim bersedia memasang client |

Cloudflare Access juga mendukung aplikasi jaringan privat. Namun, jalur aplikasi web publik dengan hostname tetap pada panduan ini memerlukan domain aktif di Cloudflare. Alamat organisasi `nama-tim.cloudflareaccess.com` digunakan untuk layanan Access/App Launcher dan tidak otomatis menjadi hostname publik origin Tensura.

Referensi: [Jenis aplikasi Access](https://developers.cloudflare.com/cloudflare-one/access-controls/applications/choose-application-type/).

### Quick Tunnel dengan pembatasan email

Dokumentasi terbaru menyediakan `--allowed-mail` untuk autentikasi PIN email tanpa domain sendiri.

Untuk percobaan singkat dengan web yang berjalan di Windows:

```powershell
docker run --rm cloudflare/cloudflared:latest tunnel --url http://host.docker.internal:8080 --allowed-mail "anggota1@contoh.com,anggota2@contoh.com"
```

Buka URL yang tercetak pada terminal. Gunakan data uji dan sesuaikan environment URL/cookie jika menguji login aplikasi.

Keterbatasan:

- Hostname berubah setiap membuat Quick Tunnel.
- Tidak memiliki jaminan uptime.
- Maksimal 200 permintaan aktif bersamaan.
- Tidak mendukung SSE.
- Perubahan daftar email memerlukan proses dimulai ulang.

Karena Tensura menggunakan SSE untuk status operasi surat, Quick Tunnel belum cocok untuk penggunaan lengkap tim pada versi proyek saat ini.

Referensi: [Quick Tunnel](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/).
