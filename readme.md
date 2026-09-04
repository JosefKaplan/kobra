# Kobra – Nette Framework

Robustní základ pro webové aplikace postavený na Nette frameworku s Doctrine ORM.
Aplikace poskytuje core funkcionalitu pro správu uživatelů, registraci a přihlašování.

---

## Požadavky

| Požadavek | Verze |
|-----------|-------|
| PHP       | ≥ 8.2 |
| MariaDB   | ≥ 10.6 (doporučeno 10.11 LTS); MySQL 8 rovněž funguje |
| Composer  | ≥ 2.x |
| Node.js   | ≥ 18 *(nutné pro sestavení frontend assets)* |

---

## Lokální spuštění (pro vývojáře)

### 1. Klonování a instalace závislostí

```bash
git clone <URL_REPOZITÁŘE>
cd <nazev-slozky>
composer install
npm install
npm run build   # sestaví CSS/JS do www/assets/ (složka není v gitu)
```

### 2. Nastavení databáze

Připojení se konfiguruje v `config/common.neon` (sekce `parameters > database`).
Výchozí nastavení:

```yaml
parameters:
    database:
        host: localhost
        name: kobra
        user: root
        password: 'root'
        serverVersion: '10.11.11-MariaDB'   # musí odpovídat reálné verzi serveru (SELECT VERSION())
```

> Pokud vaše databáze běží s jiným heslem, portem nebo verzí, upravte tyto hodnoty
> (nejlépe v `config/local.neon` – viz `config/local.neon.example`).
> Pro lokální vývoj proti MySQL 8 nastavte `serverVersion: '8.0'`.

**macOS + Homebrew, když chceš vyvíjet proti reálné MariaDB vedle nativního `mysql`:**
`brew services start mariadb@10.11` nepoužívat — formule má natvrdo stejný datadir/port
jako formule `mysql` (kolize dat). Místo toho `bin/mariadb-dev start|stop|status` — spustí
Homebrew MariaDB 10.11 na vlastním datadiru a portu `33061`, nezávisle na `mysql`
(obojí může běžet zároveň, není třeba nic vypínat). Pak v `config/local.neon`:
`host: 127.0.0.1`, `port: 33061`, `serverVersion: '10.11.19-MariaDB'` (ověř `SELECT VERSION();`).

#### a) Pomocí Doctrine Migrations (doporučeno)

```bash
# Vytvoření prázdné databáze (pokud ještě neexistuje) – klient `mysql` funguje i pro MariaDB
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS kobra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Spuštění migrací (včetně vytvoření tabulek a vložení výchozích dat)
php bin/console migrations:migrate --no-interaction
```

### 3. Spuštění lokálního serveru

```bash
php -S localhost:8000 -t www
```

Aplikace se otevře na adrese **http://localhost:8000**.

---

## Nasazení na server (produkce)

### 1. Nahrání souborů

Zkopírujte celý projekt na server (např. přes SFTP, Git deploy, CI/CD).

### 2. Instalace závislostí

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build   # vygeneruje www/assets/ (lze sestavit lokálně a nahrát hotové)
```

### 3. Nastavení databáze

Upravte konfiguraci v `config/common.neon` (nebo vytvořte `config/local.neon`
s přepisem databázových parametrů):

```yaml
parameters:
    database:
        host: <db-server>
        name: kobra
        user: <db-uzivatel>
        password: '<db-heslo>'
        serverVersion: '10.11.11-MariaDB'   # reálná verze DB serveru
```

Spusťte migrace:

```bash
php bin/console migrations:migrate --no-interaction
```

### 4. Konfigurace webserveru

#### Apache (`.htaccess` je součástí projektu)

Nastavte `DocumentRoot` na složku `www/`:

```apache
<VirtualHost *:80>
    ServerName vase-domena.cz
    DocumentRoot /cesta/k/projektu/www

    <Directory /cesta/k/projektu/www>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name vase-domena.cz;
    root /cesta/k/projektu/www;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Oprávnění

Ujistěte se, že webserver má právo zápisu do složek:

```bash
chmod -R 775 temp/ log/
```

> `temp/sessions/` je v repu (jen `.gitkeep`, obsah ignorován) – Nette tuhle složku
> sama nevytvoří, PHP session handler jen selže hláškou "Failed to read session data",
> pokud neexistuje nebo do ní není zápis. Po klonu/kopii repa proto vždy zkontroluj,
> že `temp/sessions/` existuje a je zapisovatelná (řeší i `chmod` výše).

### 6. Produkční režim

Pro produkci doporučujeme v souboru `app/Bootstrap.php` vypnout Tracy debugger
(nebo ho omezit jen na vybrané IP adresy):

```php
// $this->configurator->setDebugMode('secret@23.75.345.200');
$this->configurator->setDebugMode(false);
```

---

## Doctrine ORM & Migrations

Projekt používá **Doctrine ORM** přes balíčky [Nettrine](https://github.com/nettrine).
Entity se nacházejí v `app/Entity/`, migrace v `migrations/`.

### Dostupné CLI příkazy

```bash
# Zobrazení stavu migrací
php bin/console migrations:status

# Spuštění všech nových migrací
php bin/console migrations:migrate

# Vygenerování nové migrace z rozdílu entity ↔ databáze
php bin/console migrations:diff

# Vygenerování prázdné migrační třídy
php bin/console migrations:generate

# Validace mapování entit
php bin/console orm:validate-schema

# Zobrazení mapovaných entit
php bin/console orm:info

# Zobrazení všech dostupných příkazů
php bin/console list
```

### Workflow pro změny v DB schématu

1. Upravte entity v `app/Entity/`
2. Vygenerujte migraci: `php bin/console migrations:diff`
3. Zkontrolujte vygenerovaný soubor v `migrations/`
4. Spusťte migraci: `php bin/console migrations:migrate`

---

## Výchozí uživatelské účty

Po spuštění migrací jsou k dispozici:

| Uživatel | Heslo     | Role  |
|----------|-----------|-------|
| `admin`  | `Admin123` | admin |

---

## Technologie

- **Nette Application** (modulární architektura: Front & Admin)
- **Doctrine ORM** (Nettrine DBAL + ORM + Migrations)
- **Nette Database** (zachováno pro zpětnou kompatibilitu s datagridem)
- **Nette Security** (sessions uloženy v `/temp/sessions`)
- **Bootstrap 5** (včetně Bootstrap Icons)
- **Contributte Datagrid**

---

## Struktura projektu

```
├── app/
│   ├── Core/             # Router, Authenticator
│   ├── Entity/           # Doctrine entity třídy
│   ├── Helpers/          # Pomocné třídy (FormHelper)
│   ├── Models/           # Business logika (UserManager, LoginLogManager)
│   ├── Modules/
│   │   ├── Admin/        # Administrátorský modul
│   │   └── Front/        # Veřejný modul (login, registrace)
│   └── Bootstrap.php
├── bin/
│   └── console           # CLI entry point pro Doctrine příkazy
├── config/
│   ├── common.neon       # Hlavní konfigurace
│   └── services.neon     # Registrace služeb
├── migrations/           # Doctrine migrační soubory
├── www/                  # Web root (index.php)
├── temp/                 # Cache, sessions
└── log/                  # Tracy logy
```
