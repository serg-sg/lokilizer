<?php
/** @var Template $this */
/** @var ServerRequest $request */

/** @var string $title */

use League\Plates\Template\Template;
use Slim\Http\ServerRequest;
use XAKEPEHOK\Lokilizer\Components\Current;
use XAKEPEHOK\Lokilizer\Components\Db\Storage\Mongo\MongoStorage;
use XAKEPEHOK\Lokilizer\Models\User\Components\Theme;

$theme = Current::hasUser() ? Current::getUser()->getTheme() : Theme::Dark;
?>
<!doctype html>
<html lang="en" data-bs-theme="<?=$theme->value?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title) ?> | <?=$this->e($_ENV['PROJECT_NAME'])?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="/style.css?3" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
            integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"
            integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy"
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script src="https://cdn.jsdelivr.net/npm/timeago@1.6.7/jquery.timeago.min.js"></script>

    <script src="/scripts.js?9"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-font-sans-serif: "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, "Noto Color Emoji", sans-serif;
        }
    </style>

</head>
<body>
<?php if ($_ENV['APP_ENV'] === 'dev'): ?>
    <div class="accordion top-0 start-0 end-0" id="dbQueries">
        <div class="accordion-item border-warning rounded-0">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#dbQueriesList">
                    Time: <?=round(microtime(true) - $request->getAttribute('startedAt', 0), 2)?>;
                    Db queries: <?= count(MongoStorage::$queries) ?>
                </button>
            </h2>
            <div id="dbQueriesList" class="accordion-collapse collapse" data-bs-parent="#dbQueries">
                <div class="accordion-body">
                    <ol>
                        <?php foreach (MongoStorage::$queries as $query): ?>
                            <li><code><?= $this->e($query) ?></code></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->section('content') ?>

<!-- Добавляем скрипт для обработки logout -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Находим ссылку "Logout" по id
    const logoutLink = document.getElementById('logout-link');

    // Проверяем, существует ли элемент (например, пользователь не вошёл)
    if (logoutLink) {
        // Добавляем обработчик события 'click'
        logoutLink.addEventListener('click', function(event) {
            // 1. Отменяем стандартное поведение (переход по ссылке)
            event.preventDefault();

            // 2. Получаем URL из атрибута href
            const logoutUrl = logoutLink.getAttribute('href');

            // 3. Выполняем fetch-запрос к URL logout
            fetch(logoutUrl, {
                    method: 'GET', // или 'POST', смотрим ваш маршрут в index.php. Там map GET/POST на /logout
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin' // Важно для передачи cookie
                })
                .then(response => {
                    // Проверяем, успешен ли ответ
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    // Парсим JSON-ответ
                    return response.json();
                })
                .then(data => {
                    // 4. Проверяем, есть ли URL для перенаправления в ответе
                    if (data.redirect) {
                        // 5. Выполняем перенаправление в ТЕКУЩЕЙ (той же) вкладке
                        window.location.href = data.redirect;
                    } else {
                        // Если URL не пришёл, можно перенаправить на главную или показать ошибку
                        window.location.href = '/'; // или другой URL по умолчанию
                    }
                })
                .catch(error => {
                    console.error('Ошибка при выходе:', error);
                    // В случае ошибки тоже можно перенаправить или показать сообщение
                    window.location.href = '/'; // или другой URL по умолчанию
                });
        });
    }
});
</script>

<script>
    // Убедимся, что DOM полностью загружен
    document.addEventListener('DOMContentLoaded', function() {
        // console.log('Accordion & Navbar script with rAF animation loaded. ENV:', '<?php echo $_ENV['APP_ENV'] ?? 'undefined'; ?>');

        // Находим аккордеон
        const accordion = document.getElementById('dbQueries');
        // if (!accordion) {
        //     console.log('Accordion not found (likely APP_ENV != "dev")');
        //     return; // Не показываем, если нет аккордеона
        // }

        // Находим navbar
        const navbar = document.querySelector('nav.navbar.fixed-top');
        // if (!navbar) {
        //     console.warn('Navbar with class "fixed-top" not found. No need to adjust.');
        //     return;
        // }

        // Находим блок, который раскрывается/закрывается
        const dbQueriesList = document.getElementById('dbQueriesList');
        // if (!dbQueriesList) {
        //     console.warn('dbQueriesList element not found. Dynamic height adjustment will not work.');
        //     return;
        // }

        // Храним высоту заголовка аккордеона (не меняется при раскрытии/закрытии)
        let accordionHeaderHeight = accordion.offsetHeight;

        // Функция для обновления видимости аккордеона
        function updateAccordionVisibility() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            // Скрываем аккордеон, когда прокручиваем больше, чем высота его заголовка
            if (scrollTop > accordionHeaderHeight) {
                accordion.style.display = 'none';
            } else {
                accordion.style.display = 'block';
            }
        }

        // Функция для получения *текущей* полной высоты аккордеона (включая раскрытый блок)
        // Ключевое изменение: просто возвращаем accordion.offsetHeight, так как Bootstrap
        // изменяет высоту родительского элемента аккордеона во время анимации.
        function getAccordionFullHeight() {
            // Всегда используем текущую высоту аккордеона
            // if (dbQueriesList.classList.contains('show')) {
            //     console.log('Accordion show. Accordion height:', accordion.offsetHeight, 'List height:', dbQueriesList.offsetHeight);
            // }
            // Если блок закрыт — возвращаем только текущую высоту самого аккордеона
            // console.log('Accordion collapsed. Accordion height:', accordion.offsetHeight);
            return accordion.offsetHeight;
        }

        // Функция для обновления позиции меню
        function updateNavbarPosition() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            // Пересчитываем текущую полную высоту аккордеона при каждом вызове
            const currentAccordionFullHeight = getAccordionFullHeight();

            // console.log('scrollTop =', scrollTop, 'currentAccordionFullHeight =', currentAccordionFullHeight);
            if (scrollTop > accordionHeaderHeight) { // Используем высоту заголовка для проверки видимости аккордеона
                // Аккордеон скрыт — меню приклеиваем к верху страницы
                navbar.style.top = '0';
            } else {
                // Аккордеон виден — меню приклеиваем к нижней границе аккордеона
                navbar.style.top = (currentAccordionFullHeight - scrollTop) + 'px';
            }
            // Выводим значение navbar.style.top в консоль
            // console.log('navbar.style.top =', navbar.style.top);
        }

        // Обработчик события, когда блок *заканчивает* раскрываться
        dbQueriesList.addEventListener('shown.bs.collapse', function () {
            // Пересчитываем высоту заголовка аккордеона (на всякий случай, если она изменилась)
            accordionHeaderHeight = accordion.offsetHeight;
            // Обновляем позицию меню
            updateNavbarPosition();
            // console.log('Accordion expanded. Header height:', accordionHeaderHeight);
        });

        // Обработчик события, когда блок *заканчивает* закрываться
        dbQueriesList.addEventListener('hidden.bs.collapse', function () {
            // Пересчитываем высоту заголовка аккордеона (на всякий случай, если она изменилась)
            accordionHeaderHeight = accordion.offsetHeight;
            // Обновляем позицию меню
            updateNavbarPosition();
            // console.log('Accordion collapsed. Header height:', accordionHeaderHeight);
        });

        let animationFrameId = null; // Храним ID для отмены

        // Функция для обновления позиции, вызываемая каждый кадр
        function animateNavbarPosition() {
            updateNavbarPosition(); // Обновляем позицию с актуальными данными
            animationFrameId = requestAnimationFrame(animateNavbarPosition); // Запрашиваем следующий кадр
        }

        // Функция для запуска анимации
        function startNavbarAnimation() {
            if (animationFrameId === null) { // Не запускаем, если уже запущено
                animationFrameId = requestAnimationFrame(animateNavbarPosition);
                // console.log('Navbar animation started.');
            }
        }

        // Функция для остановки анимации
        function stopNavbarAnimation() {
            if (animationFrameId !== null) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
                // console.log('Navbar animation stopped.');
            }
        }

        // Обработчик события, когда блок *начинает* раскрываться
        dbQueriesList.addEventListener('show.bs.collapse', function () {
            startNavbarAnimation(); // Начинаем анимацию
            // console.log('Accordion show started.');
        });

        // Обработчик события, когда блок *заканчивает* раскрываться
        dbQueriesList.addEventListener('shown.bs.collapse', function () {
            stopNavbarAnimation(); // Останавливаем анимацию
            updateNavbarPosition(); // Обновляем финальное положение (на всякий случай)
            // console.log('Accordion expanded. Full height:', getAccordionFullHeight());
        });

        // Обработчик события, когда блок *начинает* закрываться
        dbQueriesList.addEventListener('hide.bs.collapse', function () {
            startNavbarAnimation(); // Начинаем анимацию
            // console.log('Accordion hide started.');
        });

        // Обработчик события, когда блок *заканчивает* закрываться
        dbQueriesList.addEventListener('hidden.bs.collapse', function () {
            stopNavbarAnimation(); // Останавливаем анимацию
            updateNavbarPosition(); // Обновляем финальное положение (на всякий случай)
            // console.log('Accordion collapsed. Full height:', getAccordionFullHeight());
        });

        // Первый вызов при загрузке страницы
        updateNavbarPosition();

        // Обновляем при прокрутке
        window.addEventListener('scroll', updateNavbarPosition, { passive: true });

        // Обновляем при изменении размера окна (если аккордеон или меню меняют высоту)
        window.addEventListener('resize', function() {
            accordionHeaderHeight = accordion.offsetHeight; // Пересчитываем высоту заголовка
            updateNavbarPosition();
        }, { passive: true });
    });
</script>

</body>
</html>