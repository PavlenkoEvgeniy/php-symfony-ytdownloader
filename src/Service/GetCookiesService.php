<?php

namespace App\Service;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverExpectedCondition;

class GetCookiesService
{
    public function getCookies(string $youtubeLogin, string $youtubePassword, string $path): void
    {
        // 1. Подключение к Selenium (локально на порту 4444)
        $host         = 'http://selenium:4444'; 
        $capabilities = DesiredCapabilities::chrome();
        $driver       = RemoteWebDriver::create($host, $capabilities);

        try {
            // 2. Открываем YouTube
            $driver->get('https://www.youtube.com');

            // 3. Кликаем "Войти" (кнопка может меняться)
            $loginButton = $driver->findElement(
                WebDriverBy::cssSelector('a[href="https://accounts.google.com/ServiceLogin?service=youtube"]')
            );
            $loginButton->click();

            // 4. Ждем появления формы входа Google
            $driver->wait()->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(
                    WebDriverBy::cssSelector('input[type="email"]')
                )
            );

            // 5. Вводим email
            $emailField = $driver->findElement(WebDriverBy::cssSelector('input[type="email"]'));
            $emailField->sendKeys($youtubeLogin);

            // 6. Кликаем "Далее"
            $nextButton = $driver->findElement(WebDriverBy::cssSelector('#identifierNext button'));
            $nextButton->click();

            // 7. Ждем появления поля пароля
            $driver->wait()->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(
                    WebDriverBy::cssSelector('input[type="password"]')
                )
            );

            // 8. Вводим пароль
            $passwordField = $driver->findElement(WebDriverBy::cssSelector('input[type="password"]'));
            $passwordField->sendKeys($youtubePassword);

            // 9. Кликаем "Далее"
            $passwordNextButton = $driver->findElement(WebDriverBy::cssSelector('#passwordNext button'));
            $passwordNextButton->click();

            // 10. Ждем, пока страница YouTube загрузится (проверяем аватар)
            $driver->wait()->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::cssSelector('img[alt="Avatar"]')
                )
            );

            // 11. (Опционально) Сохраняем куки для yt-dlp
            $cookies = $driver->manage()->getCookies();
            file_put_contents($path . '/' . 'youtube_cookies.json', json_encode($cookies));
        } catch (\Exception $e) {
            throw new \Exception('Error at get cookies');
        } finally {
            // Закрываем браузер
            $driver->quit();
        }
    }
}
