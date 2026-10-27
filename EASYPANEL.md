# EasyPanel — HRABHILL

## Nowy serwis w EasyPanel

1. **Create App** → typ **App** (Docker).
2. **Source**: repozytorium tej strony (branch `main`).
3. **Build**: Dockerfile (ścieżka: `./Dockerfile`).
4. **Port**: `80` (kontener słucha na 80; EasyPanel wystawi domenę).
5. **Domain**: docelowa domena stajni (+ `www` z przekierowaniem, jeśli chcesz).
6. **SSL**: Certificate resolver `letsencrypt`, HTTPS włączone, docelowy **HTTP :80**.

Serwis `jazdy-konne` zostaje osobną aplikacją. Ta strona woła jego API po sieci wewnętrznej.

## Environment

Ustaw w panelu (nie commituj sekretów):

| Key | Przykład |
|-----|----------|
| `APP_ENV` | `prod` |
| `APP_SECRET` | długi losowy string |
| `APP_DEBUG` | `0` |
| `APP_HOSTNAME` | domena stajni |
| `DEFAULT_URI` | `https://twoja-domena` |
| `TRUSTED_PROXIES` | `REMOTE_ADDR` |
| `API_BASE_URL` | `https://web-portal.pl/api` |
| `API_KEY` | klucz domeny z CMS |
| `CDN_URL` | adres CDN ze zdjęciami wpisów |
| `JAZDY_API_URL` | wewnętrzny adres kalendarza, np. `http://nazwa-serwisu-jazdy:3000` |
| `JAZDY_API_KEY` | ten sam sekret co `PUBLIC_API_KEY` w jazdy-konne |

W serwisie jazdy-konne ustaw dodatkowo:

| Key | Przykład |
|-----|----------|
| `PUBLIC_API_KEY` | ten sam sekret co `JAZDY_API_KEY` (min. 16 znaków) |
| `PUBLIC_BOOKING_LEAD_HOURS` | `12` |
| `PUBLIC_HOURLY_RATE` | `0` — stawka osoby ze strony, dopóki nie poprawisz jej w panelu |

## Po deployu

1. DNS: A/CNAME domeny na IP / target EasyPanel.
2. Otwórz stronę główną, blog (`/artykuly`) i formularz terminów.
3. W CRM dodaj domenę i przypisz jej wpisy — bez klucza API blog jest pusty, strona stajni i tak działa.
4. W kalendarzu jazd konie powinny nazywać się Rychu, Ksenia, Fruzia i Drago. Świeża baza dostaje te nazwy sama. Istniejącej bazy seed nie nadpisuje.

## Lokalnie

```bash
composer install
php -S 127.0.0.1:8080 -t public public/index.php
```

Kalendarz lokalnie: `JAZDY_API_URL=http://127.0.0.1:3000` i ten sam klucz w obu projektach.
