# HRABHILL

Strona szkoły jazdy konnej: one-page o stajni, blog z CMS (ten sam silnik co web-portal) i zapis na wolne okna z kalendarza jazd.

## Co jest na stronie

- Szkoła dla dzieci i dorosłych, konie Rychu, Ksenia, Fruzia i Drago, owca Bibi
- Instagram [koniewsrodbrzoz](https://www.instagram.com/koniewsrodbrzoz/) i [bibi_owieczka_](https://www.instagram.com/bibi_owieczka_/)
- Partner: [Hrabikon](https://hrabikon.pl/)
- Hosting i opieka IT: [Blackpage](https://blackpage.pl/)
- Blog: `/artykuly`, wpis `/artykuly/artykul/{slug}`, kategoria `/artykuly/{slug}`
- Formularz terminów woła `jazdy-konne` (`GET /api/public/slots`, `POST /api/public/reservations`)

Na stronie są zdjęcia szkoły, Kseni, Rychu i Bibi. Brzozy w sekcji o szkole to kadr nastrojowy (SeppVei, CC0).

## Lokalnie

```bash
composer install
php -S 127.0.0.1:8080 -t public public/index.php
```

Zmienne: `.env`. Klucz CMS i klucz kalendarza zostaw puste, dopóki nie podłączysz usług — strona wstanie, blog i formularz pokażą spokojny komunikat.

Deploy: `EASYPANEL.md`.
