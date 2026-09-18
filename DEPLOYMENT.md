# Nasazení aplikace (deployment guide)

Průvodce nasazením aplikace Kobra (Nette 3, PHP ≥ 8.2, MariaDB 10.6+) na produkční/testovací hosting.
Stav k červenci 2026 — ceny a verze PHP u hostingů si před rozhodnutím ověř, mění se.

---

## 1. Co aplikace ve skutečnosti potřebuje

| Požadavek | Detail | Poznámka |
|---|---|---|
| PHP ≥ 8.2 | + rozšíření `pdo_mysql`, `mbstring`, `tokenizer`, `json`, `iconv` | běžná výbava každého hostingu |
| MariaDB 10.6+ | databáze `kobra` (název lze změnit v configu); doporučeno 10.11 LTS | používá `pdo_mysql` a `mysql:` DSN – MariaDB mluví MySQL protokolem. Nastav `parameters.database.serverVersion` na reálnou verzi (`SELECT VERSION()`), např. `10.11.11-MariaDB` – suffix `-MariaDB` přepne Doctrine na MariaDBPlatform. MySQL 8 funguje taky (`serverVersion: '8.0'`). |
| Apache + mod_rewrite | `www/.htaccess` už je připravený (front controller pattern) | nginx jde taky, ale vyžaduje vlastní konfiguraci |
| Document root → `www/` | **jediný záludný bod na sdíleném hostingu** — viz krok 5.4 | kód aplikace (`app/`, `config/`, `vendor/`…) nesmí být veřejně dostupný |
| Zapisovatelné `temp/`, `log/` a `www/uploads/` | DI kontejner, cache Latte, Tracy logy, sessions, nahrané obrázky | `chmod 777` (sdílený hosting) nebo `www-data` vlastník (VPS). `temp/sessions/` i `www/uploads/` jsou v repu jen jako `.gitkeep` – Nette/git je samy nezaloží; bez zápisu do `temp/sessions/` shodí "Failed to read session data", bez zápisu do `www/uploads/` selže každý upload |
| Composer **lokálně** | `vendor/` (~44 MB) se buildí na tvém stroji a nahrává se | na serveru Composer potřeba není |
| Node.js **lokálně** | `npm run build` → `www/assets/` se nahrává hotové | na serveru Node potřeba není — Vite je jen build tool |
| CLI přístup k DB | migrace: `php bin/console migrations:migrate` | jde spustit i lokálně proti vzdálené DB (viz 5.5) — SSH není nutné |

Co aplikace **nepotřebuje**: Node na serveru, Redis, cron, e-mailový server (nette/mail je v závislostech, ale nic zatím neodesílá), upload souborů (CMS zatím nemá nahrávání obrázků — viz §7).

Závěr: je to klasická "LAMP" aplikace. Poběží na jakémkoli sdíleném hostingu s PHP 8.2+, žádný VPS není nutný.

---

## 2. TL;DR doporučení

1. **Testovací nasazení hned teď:** Porkbun Easy PHP má 15denní trial — nasadit podle kroku 5, nic to nestojí, ověříš si celý postup.
2. **Dlouhodobě:** Porkbun Easy PHP je za 12 USD/měs (~280 Kč) **drahý za to, co nabízí**. Český webhosting (Wedos, Forpsi, Webglobe…) umí totéž za 30–70 Kč/měs, s PHP 8.2/8.3 a českou podporou. Doména může klidně zůstat na Porkbunu — jen přesměruješ DNS.
3. **Docker: teď ne.** Na sdíleném hostingu ho nelze použít vůbec a pro aplikaci této velikosti nepřináší nic, co by vyvážilo režii. Dává smysl až s VPS (viz §3).

---

## 3. Dockerizovat?

**Na sdíleném hostingu (Porkbun i český) je Docker technicky nemožný** — nemáš tam root ani container runtime. Takže pro variantu A i B je odpověď automaticky ne.

Kdy by Docker dával smysl:

- ✅ na VPS, kde chceš reprodukovatelné prostředí (stejné PHP/MySQL lokálně i na serveru)
- ✅ až bude CI/CD pipeline (GitHub Actions buildí image, server jen pullne)
- ✅ až poběží víc aplikací na jednom stroji a chceš je izolovat

Co Docker **nevyřeší**: nic z aktuálních potřeb. Aplikace nemá exotické závislosti, build je triviální (composer + npm), stav drží jen MySQL. Dockerizace by znamenala psát Dockerfile + compose, řešit volumes pro `temp/`/`log/`, a výsledkem by bylo to samé, co dnes dělá `php -S localhost:8000`.

**Verdikt: odložit.** Pokud jednou přejdeš na VPS, přidej `Dockerfile` + `compose.yaml` (php-apache image + mysql:8) — je to práce na hodinu a dá se udělat kdykoli později.

---

## 4. Varianty a cenová analýza

| | A) Porkbun Easy PHP | B) Český webhosting | C) VPS |
|---|---|---|---|
| Cena / měsíc | 12 USD (~280 Kč); 1. rok 60 USD | 30–70 Kč | ~100–150 Kč (Hetzner ~4–5 €), DO 6 USD |
| Cena / rok | ~2 800 Kč (1. rok ~1 400) | ~400–850 Kč | ~1 200–1 700 Kč |
| PHP | 7.4 / 8.0 / **8.2** — 8.2 je jejich strop | 8.1–8.4 dle tarifu | jakékoli |
| MySQL / MariaDB | ano, i vzdálený přístup (Workbench) | ano (většinou MariaDB), vzdálený přístup dle tarifu | vlastní instalace |
| SSH | až po ověření účtu (screening) | většinou ne / za příplatek | ano, root |
| Docroot na `www/` | nekonfigurovatelný → `.htaccess` workaround (5.4) | často nastavitelný v administraci | plná kontrola |
| Správa serveru | žádná | žádná | **celá na tobě** (updaty, zálohy, firewall) |
| Vhodné pro | test hned teď (trial) | dlouhodobý provoz za nejnižší cenu | růst, Docker, více appek |

Poznámky:

- **A (Porkbun):** funguje, ale pozor — aplikace vyžaduje PHP ≥ 8.2 a to je zároveň *nejvyšší* verze, kterou Porkbun nabízí. Nulová rezerva do budoucna (až nějaká závislost bude chtít 8.3+, jsi zaseklý). Paměťový limit 128 MB na Starter plánu stačí.
- **B (český hosting):** nejlepší poměr cena/výkon pro tuhle aplikaci. Wedos NoLimit ~47 Kč/měs s DPH, Webglobe od ~29 Kč bez DPH, Forpsi podobně. Před objednávkou ověř: PHP ≥ 8.2, MariaDB 10.6+ (nebo MySQL 8), možnost nastavit docroot nebo aspoň `.htaccess` rewrite, vzdálený přístup k DB (kvůli migracím).
- **C (VPS):** kupuješ si svobodu za svůj čas. Pro jednu malou appku se nevyplatí, dokud tě sdílený hosting reálně neomezuje.

**Doména:** může zůstat na Porkbunu bez ohledu na hosting — u varianty B/C jen změníš A záznam / nameservery. Porkbun je jako *registrátor* výborný a levný; drahý je jen jejich *hosting*.

---

## 5. Krok za krokem: nasazení na sdílený hosting

Postup psaný pro Porkbun Easy PHP; pro český hosting je identický, jen bod 5.4 bývá jednodušší (docroot nastavíš v administraci).

### 5.1 Lokální build

```bash
# 1) Produkční vendor/ — bez dev balíčků (phpstan, tester…)
composer install --no-dev --optimize-autoloader

# 2) Assets — vytvoří www/assets/ (je v .gitignore, na server jde hotový build)
npm ci
npm run build
```

**Důležité — verze PHP:** pokud máš lokálně novější PHP než 8.2, přidej klíč `config.platform.php`
do stávajícího `composer.json` (je to jen fragment k vložení, ne samostatný JSON soubor):

```jsonc
{
    "config": {
        "platform": { "php": "8.2" }
    }
}
```

a spusť `composer update --lock`. Jinak Composer může vybrat verze balíčků vyžadující tvoje lokální PHP a na serveru s 8.2 aplikace spadne na platform-checku hned na první request.

Po buildu vrať dev závislosti pro lokální vývoj: `composer install`.

### 5.2 Produkční konfigurace

Vytvoř **lokálně** soubor `config/local.neon` (šablona: `config/local.neon.example`) s údaji
z administrace hostingu (sekce databáze — hostname, database, user, password, verze MariaDB):

```neon
parameters:
	database:
		host: db-hostname-z-administrace
		name: nazev_db
		user: db_user
		password: '***'
		serverVersion: '10.11.11-MariaDB'   # reálná verze – v administraci hostingu nebo SELECT VERSION()
```

Klíč je `name` (ne `dbname`). `serverVersion` musí sedět s reálnou verzí serveru, jinak
Doctrine zvolí špatnou platformu a migrace/`orm:validate-schema` se rozejdou se schématem.

Tento soubor je v `.gitignore` — na server ho nahraješ ručně přes SFTP, do gitu nikdy nepatří.

Debug mód řešit nemusíš: `Bootstrap` nevolá `setDebugMode()`, takže Nette ho automaticky zapíná jen pro localhost — na serveru poběží produkční režim (chyby jdou do `log/`, ne na obrazovku). Pro vzdálené debugování je v `Bootstrap.php` připravený zakomentovaný řádek `setDebugMode('secret@IP')`.

### 5.3 Upload přes SFTP

Nahraj na server (přihlašovací údaje SFTP dá administrace hostingu):

```
app/            ← celé
bin/            ← celé
config/         ← včetně ručně nahraného local.neon
migrations/
vendor/         ← lokální build z 5.1
www/            ← včetně vybuildovaného www/assets/
temp/           ← stačí prázdný adresář
log/            ← stačí prázdný adresář
```

**Nenahrávej:** `node_modules/`, `.git/`, `.idea/`, `phpstan.neon`, `package*.json`, `vite.config.ts`, `assets/` (zdrojáky Sass/JS — na serveru jsou k ničemu).

Nastav zapisovatelnost `temp/` a `log/` (přes SFTP klienta: práva 775, případně 777 pokud PHP běží pod jiným uživatelem).

### 5.4 Document root → `www/`

Nette vyžaduje, aby veřejný byl **jen** adresář `www/`. Na Porkbunu docroot nastavit nejde, takže:

**Varianta i — hosting umí nastavit docroot** (většina českých): nastav ho na `www/` a hotovo.

**Varianta ii — docroot je fixní** (Porkbun): nahraj projekt do podadresáře (např. `kobra/`) a do kořene docrootu dej tento `.htaccess`:

```apache
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteRule ^$ kobra/www/ [L]
	RewriteRule (.*) kobra/www/$1 [L]
</IfModule>
```

`www/.htaccess` už blokuje přístup ke skrytým souborům; přesměrování zajistí, že se `app/`, `config/` atd. nikdy neservírují. Ověř po nasazení: `https://domena.cz/../config/common.neon` musí vrátit 403/404.

Na Porkbunu jde alternativně nasměrovat subdoménu přímo na adresář — pro testovací nasazení klidně použij `test.domena.cz` → `kobra/www`.

### 5.5 Databáze a migrace

Porkbun (i řada českých hostingů) povoluje vzdálený přístup k databázi, takže **SSH nepotřebuješ** — migrace spustíš z lokálu proti produkční DB:

```bash
# dočasně přepni config/local.neon na produkční DB údaje, pak:
rm -rf temp/cache/nette.configurator/   # bin/console jinak drží starý config!
php bin/console migrations:migrate --no-interaction
# … a vrať local.neon zpět na lokální DB (a znovu smaž cache)
```

Migrace zároveň naseeduje výchozí účet `admin`/`Admin123`.

Pokud hosting vzdálený přístup k DB nemá: exportuj lokální schéma (`mysqldump kobra > dump.sql` z čerstvě zmigrované lokální DB; `mysqldump` funguje i pro MariaDB) a naimportuj přes phpMyAdmin hostingu.

### 5.6 Ověření a zabezpečení — hned po nasazení

1. Otevři web — musí naběhnout homepage (fallback stránka, dokud v adminu nevybereš CMS homepage).
2. Přihlas se na `/auth/login` a **okamžitě změň heslo výchozího účtu** (`admin`/`Admin123` je veřejně známé z README/gitu).
3. Aktivuj SSL (Porkbun: free certifikát v administraci) a pak v `www/.htaccess` odkomentuj blok "Force usage of HTTPS".
4. Zkontroluj, že chyby nechodí na obrazovku (zkus neexistující URL — má přijít hezká 404, ne Tracy bluescreen). Případné chyby padají do `log/`.
5. Projdi admin: vytvoř stránku, přidej komponenty, ověř že se ukládají (test zápisu do DB i `temp/`).

### 5.7 Aktualizace webu (další nasazení)

1. lokálně: `composer install --no-dev && npm run build`
2. nahrát přes SFTP změněné soubory (`app/`, `www/assets/`, případně `vendor/`)
3. **smazat na serveru `temp/cache/`** (Nette v produkci nedetekuje změny šablon/configu)
4. pokud přibyla migrace: spustit ji postupem 5.5

Až tě tohle začne otravovat, je čas na deploy skript (rsync přes SSH) nebo GitHub Actions — ne dřív.

---

## 6. Co zůstane na GitHubu

Repo je **zdroj pravdy pro kód**, server je jen cíl buildů. Na GitHubu je všechno kromě toho, co už teď kryje `.gitignore`:

| Na GitHubu | Jen na serveru (nebo lokálně) |
|---|---|
| `app/`, `bin/`, `config/*.neon` (bez local), `migrations/`, `www/` (bez assets), `assets/` zdrojáky, `composer.json`+`lock`, `package.json`+`lock`, `vite.config.ts`, `CLAUDE.md`, `DEPLOYMENT.md` | `vendor/` (build), `www/assets/` (build), `config/local.neon` (tajemství!), obsah `temp/` a `log/` |

Zásady:

- **hesla a DB údaje nikdy do gitu** — jen `config/local.neon` na serveru; kdyby produkčních parametrů přibývalo, zvaž `common.neon` číst z env proměnných
- repo může být klidně privátní — na deployment to nemá vliv (nedeployuje se z GitHubu, ale z lokálního buildu)
- výchozí hesla v README jsou OK jen dokud platí bod 5.6/2

---

## 7. Obrázky (IMGs)

CMS umí nahrávání obrázků (drag & drop / výběr souboru u `hero`, `image` a `masonry` bloků,
`App\Helpers\ImageUploader`). Co to znamená pro nasazení:

- Uploady se ukládají do `www/uploads/` (veřejné, mimo git — `.gitignore` drží obsah venku, jen
  `.gitkeep` a `.htaccess` jsou trackované, stejně jako `temp/sessions/`). **Nikdy** ne do
  `www/assets/` — to maže/přepisuje Vite build.
- `www/uploads/` musí po nasazení existovat a být zapisovatelná (viz krok 5, `chmod`) — Nette ji
  sama nezaloží.
- Validace (typ podle skutečného obsahu souboru, ne přípony/MIME od klienta; max. 8 MB —
  `ImageUploader::MAX_SIZE`) běží server-side, ale **`php.ini` limity musí ten limit dovolit**:
  `upload_max_filesize` a `post_max_size` ≥ 8 MB. Na sdíleném hostingu bývá výchozí strop nižší
  (2–8 MB) a admin nemusí mít možnost ho zvednout — ověř to před spuštěním do provozu.
- Uploady jsou jediná binární data mimo DB → zálohovat spolu s dumpem DB (samotná DB tabulka
  `uploads` drží jen metadata/audit záznam, ne obsah souboru).
- Na sdíleném hostingu hlídat diskovou kvótu (Porkbun Starter: 10 GB).
- Smazání komponenty/položky, která na upload odkazovala, soubor ani jeho DB záznam nemaže —
  osiřelé soubory jsou zatím vědomý dluh (správa/čištění přijde s budoucí media knihovnou).

---

## 8. Rozšiřovat, nebo migrovat?

Sdílený hosting téhle aplikaci **stačí a dlouho stačit bude** — je to CMS s pár tabulkami, bez uploadů, bez fronty úloh, bez vysoké návštěvnosti. Signály, že je čas na VPS (a případně Docker):

- potřebuješ PHP verzi / rozšíření, které hosting nenabízí (na Porkbunu hrozí nejdřív — strop 8.2)
- přibude zpracování na pozadí (cron, fronty, e-maily ve velkém)
- víc projektů → jeden VPS je levnější než tři hostingy
- chceš CI/CD s automatickým deployem

Do té doby: **migrace mezi sdílenými hostingy je levná a bezbolestná** (přesně postup z §5 + přesměrování DNS), takže rozhodnutí "Porkbun vs. český hosting" není vendor lock-in. Klidně začni na Porkbun trialu a po 15 dnech se rozhodni podle ceny.
