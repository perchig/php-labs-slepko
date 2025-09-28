<?php
declare(strict_types=1);

const COOKIE_TTL = 1 * 24 * 3600; // время жизни пакета ( в нашем случае пакет с залогининым пользователем) 1 день
const COOKIE_PATH = '/';
const COOKIE_SECURE = false; // сертификата нет 
const COOKIE_HTTPONLY = true; // чтобы токен был доступен из JS
const COOKIE_SAMESITE = 'Strict'; // тестовый сайт нет переадресации
?>