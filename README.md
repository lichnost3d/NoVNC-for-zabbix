# NoVNC-for-zabbix
A wrapper for embedding noVNC in zabbix (Обёртка для встраивания NoVNC в Zabbix)

Обрётка для встраивания NoVNC в Zabbix.
Добавляет в контекстное меню узлов на карте сети, пункт "Подключиться по VNC".

Разрабатывалось под Zabbix 7.4 (С более старыми может не работать по api)


Ставим NoVNC
apt -y install novnc python3-websockify

генерим сертификат
openssl req -new -x509 -nodes -newkey ec:<(openssl ecparam -name secp384r1) -keyout novnc.pem -out novnc.pem -days 3650 

Добавьте файлы "novnc.php" и "novnc_cleanup.php"
в папку c файлами фронта  /usr/share/zabbix/ui/

пропишите в начале файла "novnc.php" параметры подключения к zabbix и пароль от VNC серверов к которым будете подключаться

Замените файл "menupopup.js"
в папке /usr/share/zabbix/ui/js/

Добавьте в cron для очистки:
# Очистка старых noVNC сессий каждые 30 минут
*/30 * * * * /usr/bin/php /usr/share/zabbix/ui/js/novnc_cleanup.php
