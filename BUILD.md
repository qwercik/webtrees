# Przygotowanie środowiska dev do pracy

1. Uruchamiamy kontenery poleceniem `docker compose up -d`
2. Wchodzimy do kontenera `www`: `docker compose exec www bash`
3. Instalujemy zależności composera: `find -name composer.json -execdir composer install \;`
4. Instalujemy zależności npm: `npm install`
5. Przechodzimy przez kreator w przeglądarce (http://localhost)
6. W pliku `data/config.ini.php` zmieniamy wartość wpisu `rewrite_urls` na 1.


