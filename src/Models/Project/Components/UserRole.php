<?php
/**
 * Created for lokilizer
 * Date: 2025-02-06 00:51
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\Lokilizer\Models\Project\Components;

use DiBify\DiBify\Model\Reference;
use PrinsFrank\Standards\Language\LanguageAlpha2;
use Stringable;
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\Permission;
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\PermissionException;
use XAKEPEHOK\Lokilizer\Models\Project\Components\Role\Role;
use XAKEPEHOK\Lokilizer\Models\User\User;

readonly class UserRole implements Stringable
{

    public Reference $user;
    public Role $role;
    public array $languages;

    public function __construct(
        User $user,
        Role $role,
        LanguageAlpha2 ...$languages
    )
    {
        $this->user = Reference::to($user);
        $this->role = $role;
        $this->languages = $languages;
    }

    // --- Оборачиваем вызов getModel в try-catch ---
    public function getUser(): ?User // Теперь возвращаем ?User
    {
        try {
            $userModel = $this->user->getModel();
            // Если DiBify возвращает ModelInterface, возвращаем его
            return $userModel;
        } catch (\TypeError $e) {
            // Если getModel выбрасывает TypeError из-за возврата null (что может происходить внутренне в DiBify),
            // перехватываем его и возвращаем null.
            error_log("Warning: UserRole reference failed to resolve user ID " . $this->user->id()->get() . ". Error: " . $e->getMessage()); // Опционально: логирование
            return null;
        } catch (\Exception $e) {
            // Перехватываем и другие возможные исключения, которые могут возникнуть при разрешении ссылки.
            error_log("Warning: UserRole reference failed to resolve user ID " . $this->user->id()->get() . ". Error: " . $e->getMessage()); // Опционально: логирование
            return null;
        }
    }
    // --- /ИЗМЕНЕНО ---

    public function can(?Permission $permission, ?LanguageAlpha2 $language = null): bool
    {
        // ВАЖНО: Изменить логику can, чтобы она учитывала, что getUser() может вернуть null
        // Если пользователь не найден, возможно, имеет смысл запретить все действия.
        // Или, если логика роли не зависит от конкретного пользователя, можно проверять только $this->role->can($permission).
        // Пока оставим как есть, но используем $this->getUser() и проверим null.
        if ($this->getUser() === null) {
            // Если пользователь не найден, можно считать, что доступа нет.
            // Или, если роль сама по себе определяет права, использовать только её.
            // Вариант 1: Запретить всё.
            // return false;
            // Вариант 2: Проверить только роль (без учёта языков, т.к. они связаны с пользователем в этом UserRole).
            if ($language) {
                 // Если язык указан, но пользователь не найден, доступа нет.
                 return false;
            }
            return $this->role->can($permission);
        }

        if ($language) {
            if (!in_array($language, $this->languages) && !$this->role->can(Permission::MANAGE_LANGUAGES)) {
                return false;
            }

            if ($permission === null) {
                return true;
            }
        }

        return $this->role->can($permission);
    }

    /**
     * @param Permission|null $permission
     * @param LanguageAlpha2|null $language
     * @return void
     * @throws PermissionException
     */
    public function guard(?Permission $permission, ?LanguageAlpha2 $language = null): void
    {
        // Также нужно обновить guard, чтобы он учитывал null.
        if ($this->getUser() === null) {
            // Если пользователь не найден
            // логично выбросить исключение, так как проверка доступа невозможна.
            throw new PermissionException("Cannot check permissions: user data unavailable (deleted).");
        }

        if ($language) {
            if (!in_array($language, $this->languages) && !$this->role->can(Permission::MANAGE_LANGUAGES)) {
                throw new PermissionException("No permission for language: {$language->name}");
            }

            if ($permission === null) {
                return;
            }
        }

        $this->role->guard($permission);
    }

    public function __toString(): string
    {
        // В __toString тоже нужно быть осторожным
        $user = $this->getUser();
        if ($user) {
            return $user->getName() . ' (' . $this->user->id()->get() . ')'; // Имя и ID
        } else {
            return 'Deleted User (' . $this->user->id()->get() . ')'; // Обозначение удалённого
        }
    }
}