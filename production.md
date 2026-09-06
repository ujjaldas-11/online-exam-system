# Production Build and Deployment Guide

Examify is designed for deployment on institutional Local Area Networks (LAN) and production web servers.
This guide details release packaging, web server configuration, SSL/TLS certificate installation, database encryption enforcement, and real-time process supervision.

---

## 1. Downloading the Release Package

Follow these steps to download the production build:

1. Open the repository on GitHub.
2. Click the **Releases** section on the right sidebar.
3. Select the latest version tag (for example, `v1.2.0`).
4. Download `examify-release.zip` or `examify-release.tar.gz`.
5. *(Optional)* Download `SHA256SUMS.txt` to verify archive integrity:
   ```bash
   sha256sum -c SHA256SUMS.txt
   ```
6. Extract the downloaded archive on your server.

---

## 2. Web Server Deployment

Follow these steps to deploy the extracted application:

1. Move all extracted files to your web root directory (for example, `/var/www/html/examify/` or `/opt/lampp/htdocs/examify/`).
2. Set appropriate file permissions (web server user must have read access; log/upload directories need write access):
   ```bash
   sudo chown -R www-data:www-data /var/www/html/examify/
   sudo chmod -R 755 /var/www/html/examify/
   ```
3. Create a `.env` file in the root directory:
   ```env
   APP_ENV=production
   APP_NAME=Examify
   APP_URL=https://examify.college.edu
   APP_TIMEZONE=Asia/Kolkata

   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=examify
   DB_USERNAME=examify_prod
   DB_PASSWORD=your_strong_production_password
   DB_CHARSET=utf8mb4
   DB_SSL=true
   DB_SSL_CA=/etc/ssl/certs/mysql-ca.pem

   SESSION_LIFETIME=1800
   CSRF_SECRET=generate_a_random_64_character_hex_string

   WS_ENABLED=true
   WS_HOST=0.0.0.0
   WS_PORT=8085
   WS_IPC_HOST=127.0.0.1
   WS_IPC_PORT=8086
   WS_PUBLIC_URL=wss://examify.college.edu/ws/
   ```
4. Initialize the database schema:
   ```bash
   php init-db.php --schema-only
   ```
5. Complete the initial Superadmin setup wizard at `https://examify.college.edu/admin/setup.php`.
6. Start the real-time WebSocket daemon (see Section 6 for systemd service setup):
   ```bash
   php bin/websocket-server.php &
   ```
   *(Alternatively, use the root shorthand `php server.php &`).*

---

## 3. ⚠️ CRITICAL WARNING: Consequences of Running Without SSL in Production

Deploying Examify with `APP_ENV=production` without valid HTTPS/SSL will cause severe security and operational failures:

### A. Complete Session Failure & Infinite Login Loop
- In `utils/session.php`, the system enforces `session.cookie_secure = true` whenever `APP_ENV=production` or HTTPS is detected.
- **What happens**: When accessed over plain HTTP (`http://`), modern web browsers **refuse to store or transmit cookies marked with the `Secure` flag**.
- **Impact**: Both students and administrators will be trapped in an infinite login loop. Submitting login credentials will appear to succeed, but the subsequent request will be missing the session cookie and immediately redirect back to the login page. The application becomes completely non-functional.

### B. Cleartext LAN Packet Sniffing (Credential & Answer Leakage)
- In college computer laboratories, student workstations share the same local network subnet, Ethernet switches, or wireless access points.
- Any student running packet capture tools (such as Wireshark, `tcpdump`, or performing ARP cache poisoning) can inspect unencrypted HTTP traffic.
- **Without SSL, the following are exposed across the LAN in plain text**:
  - Administrator and teacher login credentials.
  - Student passwords and examination access PINs.
  - Complete examination question banks and correct answers during teacher authoring.
  - Real-time candidate answers transmitted during auto-save requests.

### C. Trivial Session Hijacking
- Plain HTTP transmits session identifiers (`PHPSESSID`) in unencrypted request headers.
- Any unauthorized user on the LAN can copy an active session cookie and impersonate an examiner or a candidate without knowing their password.

### D. Browser Security Restrictions & Mixed Content Blocks
- Modern browsers restrict security-sensitive Web APIs (such as Fullscreen and Pointer Lock used by anti-cheat monitors) in insecure HTTP contexts.
- Modern browsers display invasive red "Not Secure" warnings to candidates.
- **WebSocket Failure**: If the webpage is loaded over HTTPS but attempts to connect to an unencrypted WebSocket (`ws://`), modern browsers will immediately terminate the connection due to **Mixed Active Content** blocking. The WebSocket connection **must** use `wss://` via an SSL reverse proxy.

---

## 4. Web Server SSL/TLS Certificate Setup Guide

### Scenario A: Air-Gapped / Isolated College LAN (Zero Internet)

In an isolated computer laboratory with no public internet connection, establish an internal Certificate Authority (CA) and issue a local SAN certificate.

#### Step 1: Create an Internal Root Certificate Authority (CA)
On the Examify server machine:
```bash
# Generate private key for the local CA
openssl genrsa -out local-ca.key 4096

# Generate self-signed Root CA certificate valid for 10 years
openssl req -x509 -new -nodes -key local-ca.key -sha256 -days 3650 \
  -subj "/C=IN/ST=State/L=City/O=College Exam Board/CN=Examify Local Root CA" \
  -out local-ca.crt
```

#### Step 2: Generate Web Server Certificate with Subject Alternative Names (SAN)
Create an OpenSSL extension config file (`san.cnf`):
```ini
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = IN
ST = State
L = City
O = College
CN = examify.local

[v3_req]
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = examify.local
DNS.2 = *.examify.local
IP.1 = 192.168.1.100   # Replace with your LAN server IP
```

Generate the server private key and sign the certificate with your Root CA:
```bash
# Generate server private key
openssl genrsa -out server.key 2048

# Generate Certificate Signing Request (CSR)
openssl req -new -key server.key -out server.csr -config san.cnf

# Sign the server certificate with your Root CA (valid for 3 years)
openssl x509 -req -in server.csr -CA local-ca.crt -CAkey local-ca.key \
  -CAcreateserial -out server.crt -days 1095 -sha256 -extfile san.cnf -extensions v3_req
```

#### Step 3: Distribute the Root CA to Lab Workstations
To eliminate browser warnings across the lab, install `local-ca.crt` once on student client machines:
- **Windows**: Run `certutil -addstore -f "ROOT" local-ca.crt` (or distribute via Active Directory GPO).
- **Linux**: Copy to `/usr/local/share/ca-certificates/` and run `sudo update-ca-certificates`.
- **macOS**: Run `sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain local-ca.crt`.

---

### Scenario B: Public or Campus-Wide Domain (With Internet Access)

If the server has a registered domain name and internet connectivity:
```bash
# For Apache
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d examify.college.edu

# For Nginx
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d examify.college.edu
```

---

### Scenario C: Web Server Configuration Examples

#### 1. Apache Configuration (`/etc/apache2/sites-available/examify.conf`)

Ensure required modules are enabled:
```bash
sudo a2enmod ssl headers rewrite proxy proxy_http proxy_wstunnel
```

```apache
<VirtualHost *:80>
    ServerName examify.college.edu
    ServerAlias examify.local 192.168.1.100

    # Force 301 Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName examify.college.edu
    ServerAlias examify.local 192.168.1.100
    DocumentRoot /var/www/html/examify

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/server.crt
    SSLCertificateKeyFile /etc/ssl/private/server.key
    SSLCACertificateFile /etc/ssl/certs/local-ca.crt

    # Modern TLS Security Protocols
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5:!3DES
    SSLHonorCipherOrder on

    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"

    # WebSocket Proxy for Real-Time Proctoring (WSS -> WS)
    ProxyPreserveHost On
    ProxyRequests Off
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule ^/ws/(.*) ws://127.0.0.1:8085/$1 [P,L]

    <Directory /var/www/html/examify>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### 2. Nginx Configuration (`/etc/nginx/sites-available/examify.conf`)

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name examify.college.edu examify.local;
    return 301 https://$host$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name examify.college.edu examify.local;

    root /var/www/html/examify;
    index index.php index.html;

    # SSL Certificates
    ssl_certificate /etc/ssl/certs/server.crt;
    ssl_certificate_key /etc/ssl/private/server.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;

    # PHP-FPM Handler
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Real-Time WebSocket Proxy (WSS -> WS)
    location /ws/ {
        proxy_pass http://127.0.0.1:8085;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }

    # Block sensitive files
    location ~ /\.(env|git|htaccess|sql) {
        deny all;
    }
}
```

---

## 5. Enforcing Database SSL (MySQL / MariaDB)

When the database runs on a separate machine, virtual host, or container across the LAN, **unencrypted database communication exposes student records, passwords, and live examination scores**.

Examify's database connection layer ([`config/database.php`](config/database.php)) includes built-in support for encrypted TLS connections and server certificate verification.

### Step 1: Verify and Configure SSL on MySQL Server
Check if your MySQL server supports SSL connections:
```sql
SHOW VARIABLES LIKE '%ssl%';
```
If `have_ssl` is `YES`, the server is ready. (MySQL 5.7+ and 8.0+ auto-generate self-signed CA and certificates in `/var/lib/mysql/` upon initialization).

### Step 2: Require SSL for the Examify Database User
Enforce SSL on the MySQL user account so that connections without encryption are rejected:
```sql
-- Force encrypted TLS connection
ALTER USER 'examify_prod'@'%' REQUIRE SSL;

-- (Optional) For high-security environments, require a specific Certificate Authority:
-- ALTER USER 'examify_prod'@'%' REQUIRE ISSUER '/CN=Examify MySQL CA';

FLUSH PRIVILEGES;
```

### Step 3: Configure Examify `.env`
Update your `.env` configuration file on the web server:

```env
# Enable SSL connection to database
DB_SSL=true

# (Recommended) Path to MySQL CA Certificate for server verification
DB_SSL_CA=/etc/ssl/certs/mysql-ca.pem
```

#### How Examify Enforces Database SSL
- When `DB_SSL_CA` points to a valid file, PDO sets:
  - `PDO::MYSQL_ATTR_SSL_CA = $sslCa`
  - `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT = true`
- If `DB_SSL=true` is set without a CA certificate, PDO establishes an encrypted TLS session with `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT = false`.

### Step 4: Verify Database Connection Encryption
Run this verification command from the application root to confirm that PDO is actively using an encrypted cipher:

```bash
php -r "require 'config/database.php'; \$row = \$pdo->query(\"SHOW STATUS LIKE 'Ssl_cipher'\")->fetch(); echo 'Database SSL Cipher: ' . (\$row['Value'] ?: 'NONE (UNENCRYPTED)') . PHP_EOL;"
```

**Expected Output (Encrypted)**:
```text
Database SSL Cipher: TLS_AES_256_GCM_SHA384
```
*(If output shows `NONE`, the connection is unencrypted and must be corrected before conducting examinations).*

---

## 6. Process Supervision for WebSocket Daemon (`systemd`)

For production reliability, manage the WebSocket daemon using `systemd` to provide automatic restarts on failure.

Create the service unit file:
```bash
sudo nano /etc/systemd/system/examify-websocket.service
```

Add the following configuration:
```ini
[Unit]
Description=Examify Real-Time WebSocket & IPC Daemon
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/examify
ExecStart=/usr/bin/php /var/www/html/examify/bin/websocket-server.php
Restart=always
RestartSec=3
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Enable and start the service:
```bash
sudo systemctl daemon-reload
sudo systemctl enable examify-websocket.service
sudo systemctl start examify-websocket.service
sudo systemctl status examify-websocket.service
```

---

## 7. Automated Release Optimizations

The release packaging workflow automatically applies these performance optimizations:

- **Static Asset Caching**: Setting `APP_ENV=production` sets browser caching headers (`max-age=2592000`).
- **Enforced SSL / HTTPS**: Production mode automatically forces 301 redirects to HTTPS, sets Strict-Transport-Security (HSTS) headers, and marks session cookies as Secure.
- **CSS Minification**: Combines and minifies all styles in `assets/css/` into single cached distribution bundles.
- **JavaScript Minification**: Minifies client scripts in `assets/js/` and `utils/` using `terser`.
- **Zero Runtime Dependencies**: Delivers a pure Vanilla PHP deployment requiring zero Composer or npm runtime packages.

---

## 8. Excluded Files and Directories

The production archive strictly excludes development, testing, and sensitive resources:

- Version control files (`.git/`, `.gitignore`, `.gitattributes`)
- Test suites and test questions (`tests/`)
- CI/CD workflows and issue templates (`.github/`)
- Linter configurations (`.mega-linter.yml`)
- Local secrets and local certificates (`.env`, `config/ca.crt`)
- Temporary scratch scripts and notes (`count_lines.py`, `changes.md`)
- Local error logs (`logs/*.log`, `report/`)
