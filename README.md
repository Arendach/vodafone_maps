# Установка

### Clone
``` 
git clone ssh://git@github.com/Arendach/vodafone_maps.git .
```

### Composer 
```
composer install
```

# Основні моменти

> Перейти в корінь проекту, в форму в поле загрузити файли які згенерував МапІнфо
> , натиснути кноку обробити, файл сам загрузиться. Після цього додати його до Zip архіву. В архіві має бути 
> тільки один файл з розширенням .kml(той самий xml). Зберегти архів не в .zip розширенні а в розшиненні .kmz 
> але в zip форматі. Схема муторна но належить Гуглу). 
> 
> Кольори це окрема пісня, там не hex формат а якийсь свій, якщо треба буде поміняти то можна тут згенерувати - http://www.zonums.com/gmaps/kml_color/
> 
> Стилі задаються в свойствах VodafoneController, там я лишив коментар.
> 
> Скрипт до того всього убирає лишні пробіли, що економить до процентів 20 на розмірі файлу. Так як гугл пропускає тільки 
> файли які розміром до 5 мб. Стискати архів немає сенсу, всеодно не пропустить, він рахує розмір розпакованого файлу.
> 
> Скрипт працює з файлами генерованими в програмі MapInfo, скачати з торента можна тут - https://only-soft.org/viewtopic.php?t=103532 
> Купити програму не получиться, по причині вона в україні не розповсюджується а там де розповсюджується то тільки 
> в виді дисків і за космічну суму(актуально на 2020 рік).
> 
> Не знаю хто буде дальше оновляти карти но по будь яким питанням писати в ТГ - 0964456851. Чим зможу тим поможу :)
