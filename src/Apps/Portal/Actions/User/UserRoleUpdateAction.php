<?php

namespace XAKEPEHOK\Lokilizer\Apps\Portal\Actions\User;

use DiBify\DiBify\Manager\ModelManager;
use DiBify\DiBify\Manager\Transaction;
use League\Plates\Engine;
use PhpDto\EmailAddress\Exception\InvalidEmailAddressException;
use PrinsFrank\Standards\Language\LanguageAlpha2;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;
use Throwable;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\ApiRuntimeException;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\RenderAction;
use XAKEPEHOK\Lokilizer\Apps\Portal\Components\RouteUri;
use XAKEPEHOK\Lokilizer\Components\Current;
use XAKEPEHOK\Lokilizer\Components\PublicExceptionInterface;
use XAKEPEHOK\Lokilizer\Models\Localization\Db\RecordRepo;
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\Permission;
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\Role;
use XAKEPEHOK\Lokilizer\Models\Project\Components\UserRole;
use XAKEPEHOK\Lokilizer\Models\User\User;
use function Sentry\captureException;

class UserRoleUpdateAction extends RenderAction
{

    public function __construct(
        Engine                        $engine,
        private readonly RecordRepo $recordRepo,
        private readonly ModelManager $modelManager,
    )
    {
        parent::__construct($engine);
    }

    public function __invoke(Request $request, Response $response): Response|ResponseInterface
    {
        Current::guard(Permission::MANAGE_USERS);

        $error = '';

        $project = Current::getProject();
        $userRole = null;
        $userIdAttribute = $request->getAttribute('id'); // Получаем ID из атрибута маршрута

        foreach ($project->getUsers() as $role) {
            if ($role->user->id()->isEqual($userIdAttribute)) {
                $userRole = $role;
                break; // Нашли, выходим
            }
        }

        if (!$userRole) {
            return $this->render($response, 'errors/not_found', [
                'request' => $request,
                'error' => 'User with passed id was not found in project',
            ]);
        }

        $user = $userRole->getUser(); // Используем обновлённый метод, который может вернуть null

        if ($user === null) {
            // Пользователь не найден, но UserRole существует
            // Обрабатываем POST-запрос на удаление
            if ($request->isPost()) {
                if ($request->getParsedBodyParam('delete') === '1') {
                    // --- Используем метод removeUserById --
                    $project = Current::getProject(); // Убедимся, что работаем с актуальным проектом
                    $userIdAttribute = $request->getAttribute('id'); // ID из URL

                    $userRemoved = $project->removeUserById($userIdAttribute); // <-- Вызов метода removeUserById

                    if ($userRemoved) {
                        $this->modelManager->commit(new Transaction([$project]));
                        // Перенаправляем на список пользователей
                        return $response->withRedirect((new RouteUri($request))('users'));
                    } else {
                        // Что-то пошло не так, UserRole не найден для удаления
                        $error = "Orphaned user role not found for deletion.";
                    }
                }
            }

            // Отображаем страницу с сообщением и кнопкой удаления
            return $this->render($response, 'user/user_role_update', [
                'request' => $request,
                'user' => null, // Передаём null
                'role' => $userRole->role->value, // Передаём роль из UserRole
                'languages' => [], // Передаём пустой массив или fetchLanguages, если нужно показать все возможные
                'selectedLanguages' => $userRole->languages, // Передаём языки из UserRole
                'error' => $error,
                'userNotFound' => true,
                'userId' => $userIdAttribute, // <-- Передаём ID для подтверждения в шаблоне
            ]);
        }

        // Старая логика для существующего пользователя
        $languages = $this->recordRepo->fetchLanguages(true);
        $selectedLanguages = $userRole->languages;
        if ($userRole->can(Permission::MANAGE_LANGUAGES)) {
            $selectedLanguages = $this->recordRepo->fetchLanguages(true);
        }

        if ($request->isPost()) {
            if ($_ENV['APP_ENV'] === 'dev') {
                $deleteValue = $request->getParsedBodyParam('delete');
                error_log("DEBUG UserRoleUpdateAction: Delete param value: " . var_export($deleteValue, true));
                error_log("DEBUG UserRoleUpdateAction: Delete param type: " . gettype($deleteValue));
            }
            if ($request->getParsedBodyParam('delete') === '1') {
                if ($_ENV['APP_ENV'] === 'dev') {
                    error_log("DEBUG UserRoleUpdateAction: Delete action detected, proceeding with deletion. User ID: " . $user?->id()?->get() ?? 'N/A');
                }
                $project->removeUser($user); // Передаём существующего пользователя
                $this->modelManager->commit(new Transaction([$project]));
                return $response->withRedirect((new RouteUri($request))('users'));
            }

            // --- ДОБАВИМ ОТЛАДКУ ---
            if ($_ENV['APP_ENV'] === 'dev') {
                $roleValueFromPost = $request->getParsedBodyParam('role');
                error_log("DEBUG UserRoleUpdateAction POST: Raw 'role' param: " . var_export($roleValueFromPost, true));
                error_log("DEBUG UserRoleUpdateAction POST: Is null: " . ($roleValueFromPost === null ? 'TRUE' : 'FALSE'));
                error_log("DEBUG UserRoleUpdateAction POST: Is empty string: " . ($roleValueFromPost === '' ? 'TRUE' : 'FALSE'));
                error_log("DEBUG UserRoleUpdateAction POST: Type: " . gettype($roleValueFromPost));
            }
            // --- /ДОБАВИМ ОТЛАДКУ ---

            try {
                // --- Преобразуем строку в int перед передачей в Role::tryFrom ---
                $roleValue = $request->getParsedBodyParam('role'); // Получаем без значения по умолчанию
                if ($roleValue === null || $roleValue === '') { // Проверяем null и пустую строку
                     error_log("DEBUG UserRoleUpdateAction: Role param is null or empty string, throwing exception.");
                     throw new ApiRuntimeException('Role is required');
                }
                $roleInt = (int) $roleValue; // Преобразуем строку в целое число
                $role = Role::tryFrom($roleInt); // Передаём int
                if (!$role) { // Проверяем, что Role::tryFrom вернул валидный случай
                     throw new ApiRuntimeException('Invalid role');
                }

                try {
                    $selectedLanguages = array_map(
                        fn (string $language) => LanguageAlpha2::from($language),
                        $request->getParsedBodyParam('selectedLanguages', [])
                    );
                } catch (Throwable) {
                    throw new ApiRuntimeException('Invalid language');
                }

                $selectedLanguages = array_filter(
                    $selectedLanguages,
                    fn (LanguageAlpha2 $language) => in_array($language, $languages)
                );

                if ($userRole->can(Permission::MANAGE_LANGUAGES)) {
                    $selectedLanguages = $this->recordRepo->fetchLanguages(true);
                }

                $role = new UserRole($user, $role, ...$selectedLanguages);
                $project->setUser($role);

                $this->modelManager->commit(new Transaction([$project]));
                return $response->withRedirect((new RouteUri($request))('users'));
            } catch (PublicExceptionInterface|InvalidEmailAddressException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $throwable) {
                if ($_ENV['APP_ENV'] === 'dev') {
                    $error = 'Internal ServerError: ' . $throwable->getMessage();
                } else {
                    $error = 'Internal Server Error';
                }
                captureException($throwable);
            }
        }

        return $this->render($response, 'user/user_role_update', [
            'request' => $request,
            'user' => $user,
            'role' => $request->getParsedBodyParam('role', $userRole->role->value),
            'languages' => $languages,
            'selectedLanguages' => $selectedLanguages,
            'error' => $error,
            'userNotFound' => false, // <-- Убедимся, что это false для нормального пользователя
        ]);
    }
}